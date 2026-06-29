<?php

/**
 * Query analyzer service.
 *
 * Captures executed SQL via Laravel's `DB::listen()` hook, normalizes
 * each statement so logically identical queries collapse to one
 * signature, and tracks frequency + execution time per signature.
 * Surfaces the captured data to callers (the N+1 detector, the
 * dashboard, monitoring listeners) without storing anything on its
 * own — persistence lives in the slow-query repository and the
 * monitoring pipeline, both of which subscribe to the analyzer's
 * `analyze()` output.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Database;

use ArtisanPackUI\Performance\Events\SlowQueryDetected;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Query analyzer service class.
 *
 *
 * @since      1.0.0
 */
class QueryAnalyzer
{
    /**
     * Whether the analyzer is currently listening to `DB::listen()`.
     *
     * @since 1.0.0
     */
    protected bool $listening = false;

    /**
     * Per-request log of executed queries.
     *
     * Each entry is an associative array with `sql`, `bindings`,
     * `time_ms`, `connection`, and `normalized` keys.
     *
     * @since 1.0.0
     *
     * @var array<int, array{sql: string, bindings: array<int, mixed>, time_ms: float, connection: string, normalized: string}>
     */
    protected array $log = [];

    /**
     * Frequency counter keyed by normalized signature.
     *
     * @since 1.0.0
     *
     * @var array<string, int>
     */
    protected array $counts = [];

    /**
     * Cumulative execution time keyed by normalized signature.
     *
     * @since 1.0.0
     *
     * @var array<string, float>
     */
    protected array $timings = [];

    /**
     * Subscribes the analyzer to Laravel's `DB::listen()` hook.
     *
     * Idempotent — repeat calls within the same request short-circuit
     * to avoid stacking listeners that would double-count every query.
     * The listener closure re-reads `features.query_optimization` on
     * every fire so flipping the flag off at runtime stops the work
     * even though the listener stays subscribed (DB::listen has no
     * unsubscribe API in stable Laravel).
     *
     * @since 1.0.0
     */
    public function enableQueryLogging(): void
    {
        if ( $this->listening ) {
            return;
        }

        $this->listening = true;

        DB::listen( function ( QueryExecuted $event ): void {
            if ( ! (bool) config( 'artisanpack.performance.features.query_optimization', false ) ) {
                return;
            }

            $this->record( $event );
        } );
    }

    /**
     * Reports whether the analyzer is currently listening.
     *
     * @since 1.0.0
     */
    public function isListening(): bool
    {
        return $this->listening;
    }

    /**
     * Records a single query execution.
     *
     * The recorded payload is what `getLoggedQueries()` returns and
     * what the N+1 detector consumes. The method is public so other
     * package code (and tests) can feed synthesized events without
     * having to round-trip through `DB::listen()`.
     *
     * @since 1.0.0
     *
     * @param  QueryExecuted  $event  The Laravel query event.
     */
    public function record( QueryExecuted $event ): void
    {
        $analysis = $this->analyzeQuery( $event->sql, $event->bindings, (float) $event->time );

        $this->log[] = [
            'sql'        => $event->sql,
            'bindings'   => $event->bindings,
            'time_ms'    => $analysis['time_ms'],
            'connection' => $event->connectionName,
            'normalized' => $analysis['normalized'],
        ];

        $signature                   = $analysis['normalized'];
        $this->counts[ $signature ]  = ( $this->counts[ $signature ] ?? 0 ) + 1;
        $this->timings[ $signature ] = ( $this->timings[ $signature ] ?? 0.0 ) + $analysis['time_ms'];

        $this->dispatchSlowQueryEvent( $event, $analysis['time_ms'] );
    }

    /**
     * Analyzes a single SQL statement.
     *
     * Returns the normalized signature, suggestion hints, and a passed
     * time-in-ms echo so call sites can use one shape regardless of
     * whether the caller hand-fed the time. Callers can route the
     * output into log channels or persistence at their boundary.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  The raw SQL statement.
     * @param  array<int, mixed>  $bindings  Query bindings.
     * @param  float  $timeMs  Execution time in milliseconds.
     *
     * @return array{time_ms: float, normalized: string, suggestions: array<int, string>}
     */
    public function analyzeQuery( string $sql, array $bindings = [], float $timeMs = 0.0 ): array
    {
        $normalized  = $this->normalize( $sql );
        $suggestions = $this->suggestionsFor( $normalized, $timeMs );

        return [
            'time_ms'     => $timeMs,
            'normalized'  => $normalized,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Normalizes a SQL statement to its signature form.
     *
     * The normalization rules collapse:
     * - Single-quoted string literals (`'foo'`) → `?`
     * - Numeric literals → `?`
     * - `IN (?, ?, ?)` lists with any positive count → `IN (?)`
     * - Repeated whitespace → single spaces
     *
     * Double-quoted tokens are intentionally LEFT ALONE. Standard SQL
     * (and PostgreSQL specifically) uses double quotes for IDENTIFIERS
     * — collapsing `"users"` to `?` would normalize every Postgres
     * query into the same signature and break N+1 detection, slow-query
     * attribution, and counters wholesale. MySQL apps in non-default
     * `ANSI_QUOTES` mode use single quotes for strings; double quotes
     * also denote identifiers there. The few apps that run MySQL with
     * `ANSI_QUOTES` flipped (where double quotes do denote strings) are
     * a small minority and still get correct grouping for the dominant
     * single-quote case.
     *
     * Result: every `SELECT * FROM posts WHERE user_id = 1` collapses to
     * `select * from posts where user_id = ?`, so the analyzer can
     * group identical-shape queries.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  The raw SQL statement.
     */
    public function normalize( string $sql ): string
    {
        $normalized = strtolower( trim( $sql ) );

        // Strip single-quoted string literals first so the numeric
        // replacement below doesn't claw at digits inside string content.
        $normalized = (string) preg_replace( "/'(?:[^'\\\\]|\\\\.)*'/", '?', $normalized );

        // Replace numeric literals (integers and decimals). The negative
        // lookbehind keeps us from clobbering identifiers like
        // `column_1` or `t2.id`.
        $normalized = (string) preg_replace( '/(?<![a-z0-9_])-?\d+(\.\d+)?/i', '?', $normalized );

        // Collapse IN-lists so `IN (?, ?, ?, ?)` and `IN (?, ?)` share
        // a signature — the count varies by request, the shape doesn't.
        $normalized = (string) preg_replace( '/in\s*\(\s*(\?\s*,\s*)*\?\s*\)/i', 'in (?)', $normalized );

        // Final whitespace collapse so newlines / tabs / runs of spaces
        // all collapse to single spaces.
        $normalized = (string) preg_replace( '/\s+/', ' ', $normalized );

        return trim( $normalized );
    }

    /**
     * Returns every query recorded since logging was enabled.
     *
     * @since 1.0.0
     *
     * @return array<int, array{sql: string, bindings: array<int, mixed>, time_ms: float, connection: string, normalized: string}>
     */
    public function getLoggedQueries(): array
    {
        return $this->log;
    }

    /**
     * Returns the frequency counter keyed by normalized signature.
     *
     * @since 1.0.0
     *
     * @return array<string, int>
     */
    public function getQueryCounts(): array
    {
        return $this->counts;
    }

    /**
     * Returns the cumulative execution time keyed by normalized signature, in milliseconds.
     *
     * @since 1.0.0
     *
     * @return array<string, float>
     */
    public function getQueryTimings(): array
    {
        return $this->timings;
    }

    /**
     * Clears the analyzer's in-memory log and counters.
     *
     * Octane workers / long-running test suites call this between
     * requests so per-request analysis doesn't leak across requests.
     *
     * @since 1.0.0
     */
    public function reset(): void
    {
        $this->log     = [];
        $this->counts  = [];
        $this->timings = [];
    }

    /**
     * Returns the signatures executed more than `$threshold` times.
     *
     * @since 1.0.0
     *
     * @param  int  $threshold  Minimum execution count to include.
     *
     * @return array<string, int>
     */
    public function repeatedSignatures( int $threshold ): array
    {
        if ( $threshold < 1 ) {
            return $this->counts;
        }

        return array_filter(
            $this->counts,
            static fn ( int $count ): bool => $count >= $threshold,
        );
    }

    /**
     * Dispatches `SlowQueryDetected` when the threshold is exceeded.
     *
     * The slow-query threshold is the only place the analyzer fires
     * an event itself — N+1 detection lives in a dedicated service so
     * applications can disable one feature without losing the other.
     *
     * @since 1.0.0
     *
     * @param  QueryExecuted  $event  The Laravel query event.
     * @param  float  $timeMs  Execution time in milliseconds.
     */
    protected function dispatchSlowQueryEvent( QueryExecuted $event, float $timeMs ): void
    {
        if ( ! (bool) config( 'artisanpack.performance.database.slow_query_logging.enabled', false ) ) {
            return;
        }

        $threshold = (float) config( 'artisanpack.performance.database.slow_query_logging.threshold_ms', 100 );

        if ( $timeMs < $threshold ) {
            return;
        }

        Event::dispatch( new SlowQueryDetected(
            query: $event->sql,
            timeMs: $timeMs,
            trace: [],
            bindings: $event->bindings,
        ) );
    }

    /**
     * Returns suggestion hints for a normalized query.
     *
     * The hints are intentionally generic — actionable enough for a
     * developer to dig in without pretending to be a query planner.
     *
     * @since 1.0.0
     *
     * @param  string  $normalized  The normalized query signature.
     * @param  float  $timeMs  Execution time in milliseconds.
     *
     * @return array<int, string>
     */
    protected function suggestionsFor( string $normalized, float $timeMs ): array
    {
        $hints = [];

        if ( str_contains( $normalized, 'select *' ) ) {
            $hints[] = 'Avoid SELECT * — list explicit columns to reduce row size and enable covering indexes.';
        }

        if ( 1 === preg_match( '/where\s+\w+\s+like\s+\?\s*$/i', $normalized ) ) {
            $hints[] = 'A LIKE filter with a leading wildcard prevents index use — consider full-text search.';
        }

        if ( $timeMs >= 500.0 ) {
            $hints[] = 'Query exceeded 500ms — review the query plan with EXPLAIN.';
        }

        return $hints;
    }
}
