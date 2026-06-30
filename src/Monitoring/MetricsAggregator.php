<?php

/**
 * Metrics aggregator.
 *
 * Folds raw `performance_raw_metrics` rows into the per-day percentile
 * summaries that back the dashboard. Aggregation groups by date, route,
 * metric name, device type, and connection type so callers can slice
 * the dashboard by every dimension they receive from the browser.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Monitoring;

use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Models\RawMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Metrics aggregator class.
 *
 *
 * @since      1.0.0
 */
class MetricsAggregator
{
    /**
     * Percentiles the aggregator computes for each bucket.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    public const PERCENTILES = [ 50, 75, 90, 99 ];

    /**
     * Number of raw samples loaded per chunk during aggregation.
     *
     * Sized to keep the bucketing pass under ~5MB of resident memory
     * for typical samples (~1KB hydrated rows) while still amortizing
     * query round-trip cost across a meaningful batch.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const CHUNK_SIZE = 5000;

    /**
     * Aggregates the raw samples for the given calendar date.
     *
     * Returns the number of `performance_metrics` rows written or
     * updated. The implementation reads every raw sample for the date,
     * buckets them by `(route, metric, device, connection)`, computes
     * the configured percentiles, and upserts a single row per bucket.
     * Existing rows for the same bucket are overwritten so the
     * aggregator is idempotent — re-running for the same date produces
     * the same totals regardless of how many times it has run before.
     *
     * @since 1.0.0
     *
     * @param  Carbon|string|null  $date  Calendar date (defaults to today).
     */
    public function aggregate( Carbon|string|null $date = null ): int
    {
        $target = $this->resolveDate( $date );

        // Iterate the raw samples in chunks (via the primary key, which
        // is the most reliable cursor under concurrent inserts) so the
        // aggregator's memory footprint stays flat regardless of daily
        // sample volume. A naive `->get()` would hydrate every model
        // for the day at once and OOM at production RUM scale.
        $buckets = [];
        $hasAny  = false;

        RawMetric::query()
            ->whereBetween( 'recorded_at', [
                $target->copy()->startOfDay(),
                $target->copy()->endOfDay(),
            ] )
            ->select( [ 'id', 'name', 'value', 'route', 'device_type', 'connection_type' ] )
            ->orderBy( 'id' )
            ->chunkById( self::CHUNK_SIZE, function ( $chunk ) use ( &$buckets, &$hasAny ): void {
                foreach ( $chunk as $sample ) {
                    $hasAny = true;
                    $key    = $this->packBucket(
                        $sample->route,
                        $sample->name,
                        $sample->device_type,
                        $sample->connection_type,
                    );

                    $buckets[ $key ][] = (float) $sample->value;
                }
            } );

        if ( ! $hasAny ) {
            return 0;
        }

        $written = 0;

        foreach ( $buckets as $bucket => $values ) {
            [ $route, $metric, $device, $connection ] = $this->unpackBucket( $bucket );

            $percentiles = $this->computePercentiles( $values );

            $values_payload = [
                'p50'          => $percentiles[50],
                'p75'          => $percentiles[75],
                'p90'          => $percentiles[90],
                'p99'          => $percentiles[99],
                'sample_count' => count( $values ),
            ];

            $this->upsertBucket( $target->toDateString(), $route, $metric, $device, $connection, $values_payload );

            $written++;
        }

        return $written;
    }

    /**
     * Computes the configured percentiles for the given sample list.
     *
     * Uses the "nearest-rank" method (NIST handbook §6.16.2): given a
     * sorted list of `N` samples and a percentile `p` in `[0, 100]`, the
     * value at zero-indexed position `ceil(p * N / 100) - 1` (clamped
     * to `[0, N-1]`) is returned. The method is exact for the percentile
     * boundary samples and stable under ties — the chief alternative
     * (linear interpolation) would invent values that did not actually
     * occur, which is undesirable for a metric like LCP that's already
     * coarse-grained.
     *
     * @since 1.0.0
     *
     * @param  array<int, float>  $values  Sample values for a single bucket.
     *
     * @return array<int, float>
     */
    public function computePercentiles( array $values ): array
    {
        $sorted = $values;
        sort( $sorted, SORT_NUMERIC );
        $count = count( $sorted );

        if ( 0 === $count ) {
            return array_fill_keys( self::PERCENTILES, 0.0 );
        }

        $out = [];

        foreach ( self::PERCENTILES as $percentile ) {
            $rank = (int) ceil( ( $percentile / 100 ) * $count ) - 1;

            if ( $rank < 0 ) {
                $rank = 0;
            }

            if ( $rank >= $count ) {
                $rank = $count - 1;
            }

            $out[ $percentile ] = (float) $sorted[ $rank ];
        }

        return $out;
    }

    /**
     * Upserts a single bucket, handling NULL key columns explicitly.
     *
     * Eloquent's `updateOrCreate` passes the key columns through
     * `where($key, '=', $value)`, which produces `WHERE column = NULL`
     * for nullable key values — never matching. That bug would cause
     * the aggregator to insert a fresh row on every re-run (breaking
     * idempotency), so the lookup is performed manually here with the
     * `whereNull()` branch where required.
     *
     * @since 1.0.0
     *
     * @param  string  $date  Calendar date string.
     * @param  string|null  $route  Route name.
     * @param  string  $metric  Metric name.
     * @param  string|null  $device  Device type.
     * @param  string|null  $connection  Connection type.
     * @param  array<string, mixed>  $values  Values to write.
     */
    protected function upsertBucket(
        string $date,
        ?string $route,
        string $metric,
        ?string $device,
        ?string $connection,
        array $values,
    ): void {
        // The SELECT-then-INSERT/UPDATE path needs to be atomic per
        // logical bucket: two aggregator runs hitting the same bucket
        // would both see no row, both insert, and produce duplicates
        // that poison the dashboard's AVG/SUM rollups. Wrapping in a
        // transaction with `lockForUpdate()` serializes concurrent
        // upserts on the row's index range under MySQL/Postgres; on
        // stores without row locks (SQLite) the transaction at least
        // makes the pair atomic from each writer's perspective.
        //
        // The `date` column is cast as a Carbon date by the model, so
        // the value stored by SQLite includes the `00:00:00` time
        // component. `whereDate()` normalizes both sides of the
        // comparison to the date portion so the lookup matches the
        // row this aggregator wrote on a prior pass — keeping the
        // operation idempotent.
        DB::transaction( function () use ( $date, $route, $metric, $device, $connection, $values ): void {
            $query = PerformanceMetric::query()
                ->whereDate( 'date', $date )
                ->where( 'metric', $metric );

            $query = null === $route ? $query->whereNull( 'route' ) : $query->where( 'route', $route );
            $query = null === $device ? $query->whereNull( 'device_type' ) : $query->where( 'device_type', $device );
            $query = null === $connection ? $query->whereNull( 'connection_type' ) : $query->where( 'connection_type', $connection );

            $existing = $query->lockForUpdate()->first();

            if ( null !== $existing ) {
                $existing->fill( $values )->save();

                return;
            }

            PerformanceMetric::create( array_merge( $values, [
                'date'            => $date,
                'route'           => $route,
                'metric'          => $metric,
                'device_type'     => $device,
                'connection_type' => $connection,
            ] ) );
        } );
    }

    /**
     * Packs the bucket dimensions into a separator-delimited string.
     *
     * The chosen separator (`"\x1F"`, the ASCII Unit Separator) is
     * rejected at the API ingest boundary by
     * `MetricsApiController::store()`'s `$noControlChars` validation
     * rule, so the four-component round trip stays unambiguous
     * without escaping. The rejection — not the choice of separator
     * alone — is what guarantees correctness here; relaxing the
     * controller's validation would re-open the bucket-poisoning
     * vector.
     *
     * @since 1.0.0
     *
     * @param  string|null  $route  Route name (nullable when the client did not resolve one).
     * @param  string  $metric  Metric name.
     * @param  string|null  $device  Device type.
     * @param  string|null  $connection  Connection type.
     */
    protected function packBucket( ?string $route, string $metric, ?string $device, ?string $connection ): string
    {
        return implode( "\x1F", [
            $route ?? '',
            $metric,
            $device ?? '',
            $connection ?? '',
        ] );
    }

    /**
     * Reverses `packBucket()` into a 4-tuple of dimensions.
     *
     * Empty components are restored to `null` so the upsert key matches
     * the original column types (which are nullable).
     *
     * @since 1.0.0
     *
     * @param  string  $bucket  Packed bucket string.
     *
     * @return array{0: string|null, 1: string, 2: string|null, 3: string|null}
     */
    protected function unpackBucket( string $bucket ): array
    {
        $parts = explode( "\x1F", $bucket, 4 );

        return [
            '' === $parts[0] ? null : $parts[0],
            $parts[1] ?? '',
            '' === ( $parts[2] ?? '' ) ? null : $parts[2],
            '' === ( $parts[3] ?? '' ) ? null : $parts[3],
        ];
    }

    /**
     * Normalizes the date argument to a Carbon instance at the day boundary.
     *
     * @since 1.0.0
     *
     * @param  Carbon|string|null  $date  The date argument.
     */
    protected function resolveDate( Carbon|string|null $date ): Carbon
    {
        if ( $date instanceof Carbon ) {
            return $date->copy()->startOfDay();
        }

        if ( is_string( $date ) && '' !== $date ) {
            return Carbon::parse( $date )->startOfDay();
        }

        return Carbon::today();
    }
}
