<?php

/**
 * `perf:aggregate-metrics` artisan command.
 *
 * Folds the raw Core Web Vitals samples for a calendar date into the
 * `performance_metrics` percentile summaries via `MetricsAggregator`.
 * Intended to run on a scheduled hourly trigger (re-running per hour
 * is safe because the aggregator is idempotent for any given date).
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Console\Commands;

use ArtisanPackUI\Performance\Monitoring\MetricsAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Aggregate metrics command class.
 *
 *
 * @since      1.0.0
 */
class AggregateMetricsCommand extends Command
{
    /**
     * The console command signature.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'perf:aggregate-metrics
		{--date= : The calendar date to aggregate (YYYY-MM-DD). Defaults to today.}
		{--backfill= : Backfill the last N days inclusive of today.}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Aggregate raw performance metrics into per-day percentile summaries.';

    /**
     * Executes the command.
     *
     * @since 1.0.0
     *
     * @param  MetricsAggregator  $aggregator  Resolved aggregator service.
     */
    public function handle( MetricsAggregator $aggregator ): int
    {
        $dates = $this->resolveDates();

        if ( empty( $dates ) ) {
            $this->error( __( 'No dates resolved for aggregation.' ) );

            return self::FAILURE;
        }

        $total = 0;

        foreach ( $dates as $date ) {
            try {
                $written = $aggregator->aggregate( $date );
            } catch ( Throwable $e ) {
                $this->error( __( 'Failed to aggregate :date: :message', [
                    'date'    => $date->toDateString(),
                    'message' => $e->getMessage(),
                ] ) );

                return self::FAILURE;
            }

            $total += $written;

            $this->line( __( ':date — :count buckets written.', [
                'date'  => $date->toDateString(),
                'count' => $written,
            ] ) );
        }

        $this->info( __( 'Aggregation complete. :total bucket(s) written across :days day(s).', [
            'total' => $total,
            'days'  => count( $dates ),
        ] ) );

        return self::SUCCESS;
    }

    /**
     * Builds the list of dates the command should aggregate.
     *
     * @since 1.0.0
     *
     * @return array<int, Carbon>
     */
    protected function resolveDates(): array
    {
        $explicit = (string) ( $this->option( 'date' ) ?? '' );
        $backfill = (int) ( $this->option( 'backfill' ) ?? 0 );

        if ( '' !== $explicit ) {
            return [ Carbon::parse( $explicit )->startOfDay() ];
        }

        if ( $backfill > 0 ) {
            $dates = [];
            $today = Carbon::today();

            for ( $offset = 0; $offset < $backfill; $offset++ ) {
                $dates[] = $today->copy()->subDays( $offset );
            }

            return $dates;
        }

        return [ Carbon::today() ];
    }
}
