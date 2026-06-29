<?php

/**
 * Slow query logger.
 *
 * Captures queries whose execution time exceeds the configured
 * `database.slow_query_logging.threshold_ms` and persists them to two
 * sinks: the configured log channel (always, when enabled) and the
 * `performance_slow_queries` table (when `store_in_database` is on).
 * The logger walks `debug_backtrace()` to attach the application file
 * and line that issued the query, skipping framework + package frames
 * so the recorded location is the call site a developer can act on.
 *
 * Retention is enforced via `purgeExpired()` — call sites (a scheduled
 * task, the bundled console command) call it on whatever cadence
 * matches their `retention_days` setting; the logger itself does not
 * schedule the purge so applications can wire it into any task runner.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Database;

use ArtisanPackUI\Performance\Events\SlowQueryDetected;
use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * Slow query logger class.
 *
 *
 * @since      1.0.0
 */
class SlowQueryLogger
{
    /**
     * The QueryAnalyzer used to normalize captured SQL.
     *
     * @since 1.0.0
     */
    protected QueryAnalyzer $analyzer;

    /**
     * Whether the logger is currently subscribed to `DB::listen()`.
     *
     * @since 1.0.0
     */
    protected bool $listening = false;

    /**
     * Creates a new logger instance.
     *
     * @since 1.0.0
     *
     * @param  QueryAnalyzer  $analyzer  The analyzer used for normalization.
     */
    public function __construct( QueryAnalyzer $analyzer )
    {
        $this->analyzer = $analyzer;
    }

    /**
     * Subscribes the logger to `DB::listen()`.
     *
     * Idempotent — repeat calls within the same request short-circuit
     * to avoid stacking listeners that would double-record every query.
     * The listener closure re-reads `database.slow_query_logging.enabled`
     * on every fire so flipping the flag off at runtime stops the work
     * even though the listener stays subscribed (DB::listen has no
     * unsubscribe API in stable Laravel).
     *
     * @since 1.0.0
     */
    public function enable(): void
    {
        if ( $this->listening ) {
            return;
        }

        $this->listening = true;

        DB::listen( function ( QueryExecuted $event ): void {
            if ( ! (bool) config( 'artisanpack.performance.database.slow_query_logging.enabled', false ) ) {
                return;
            }

            $this->record( $event );
        } );
    }

    /**
     * Reports whether the logger is currently subscribed.
     *
     * @since 1.0.0
     */
    public function isEnabled(): bool
    {
        return $this->listening;
    }

    /**
     * Records a single query execution when it exceeds the threshold.
     *
     * The method is public so other package code (and tests) can feed
     * synthesized events without round-tripping through `DB::listen()`.
     *
     * @since 1.0.0
     *
     * @param  QueryExecuted  $event  The Laravel query event.
     *
     * @return array<string, mixed>|null The captured payload, or null when below threshold.
     */
    public function record( QueryExecuted $event ): ?array
    {
        $threshold = (float) config( 'artisanpack.performance.database.slow_query_logging.threshold_ms', 100 );
        $timeMs    = (float) $event->time;

        if ( $timeMs < $threshold ) {
            return null;
        }

        $payload = $this->buildPayload( $event, $timeMs );

        $this->writeToLogChannel( $payload );
        $this->writeToDatabase( $payload );
        $this->dispatchEvent( $event, $timeMs, $payload['trace'] );

        return $payload;
    }

    /**
     * Removes slow query records older than the configured retention window.
     *
     * Returns the number of rows deleted so callers (a scheduled task,
     * the dashboard) can report on housekeeping outcomes. When
     * `store_in_database` is off the table may not be present in the
     * application's database — the call is wrapped in a try/catch so
     * routine maintenance doesn't crash on optional storage.
     *
     * @since 1.0.0
     *
     * @return int Rows deleted.
     */
    public function purgeExpired(): int
    {
        $retention = (int) config( 'artisanpack.performance.database.slow_query_logging.retention_days', 30 );

        if ( $retention <= 0 ) {
            return 0;
        }

        try {
            return SlowQuery::query()
                ->where( 'created_at', '<', now()->subDays( $retention ) )
                ->delete();
        } catch ( Throwable ) {
            return 0;
        }
    }

    /**
     * Builds the payload recorded for a slow query.
     *
     * @since 1.0.0
     *
     * @param  QueryExecuted  $event  The Laravel query event.
     * @param  float  $timeMs  Execution time in milliseconds.
     *
     * @return array{query: string, query_normalized: string, bindings: array<int, mixed>, time_ms: float, connection: string, file: string|null, line: int|null, trace: array<int, array<string, mixed>>, route: string|null}
     */
    protected function buildPayload( QueryExecuted $event, float $timeMs ): array
    {
        $caller = $this->resolveCaller();
        $trace  = $this->captureTrace();

        return [
            'query'            => $event->sql,
            'query_normalized' => $this->analyzer->normalize( $event->sql ),
            'bindings'         => $event->bindings,
            'time_ms'          => $timeMs,
            'connection'       => $event->connectionName,
            'file'             => $caller['file'],
            'line'             => $caller['line'],
            'trace'            => $trace,
            'route'            => $this->resolveRoute(),
        ];
    }

    /**
     * Writes the payload to the configured log channel.
     *
     * Wrapped in a try/catch because the configured channel may not
     * exist in the consuming app (typical in tests or when the package
     * is exercised in isolation). Swallow rather than crash the request
     * the logger is observing — database persistence and the dispatched
     * event remain the primary signals.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  Captured payload.
     */
    protected function writeToLogChannel( array $payload ): void
    {
        $channel = (string) config( 'artisanpack.performance.database.slow_query_logging.log_channel', '' );

        if ( '' === $channel ) {
            return;
        }

        try {
            Log::channel( $channel )->warning( 'Slow query detected', $payload );
        } catch ( Throwable ) {
            // Intentional swallow — see method docblock.
        }
    }

    /**
     * Persists the payload to the `performance_slow_queries` table.
     *
     * The DB write is gated behind the `store_in_database` flag so apps
     * that only want log-channel output don't end up with a growing
     * table they didn't ask for. The write is wrapped in a try/catch
     * because the table may not exist when migrations haven't been run
     * (or when an app has chosen not to ship them).
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  Captured payload.
     */
    protected function writeToDatabase( array $payload ): void
    {
        if ( ! (bool) config( 'artisanpack.performance.database.slow_query_logging.store_in_database', false ) ) {
            return;
        }

        try {
            SlowQuery::create( $payload );
        } catch ( Throwable ) {
            // Intentional swallow — see method docblock.
        }
    }

    /**
     * Dispatches `SlowQueryDetected` to the application's event bus.
     *
     * The event is the primary signal for downstream listeners
     * (notifications, monitoring), so it fires even when log channel
     * and DB writes are both disabled.
     *
     * @since 1.0.0
     *
     * @param  QueryExecuted  $event  The Laravel query event.
     * @param  float  $timeMs  Execution time in milliseconds.
     * @param  array<int, array<string, mixed>>  $trace  Captured stack trace.
     */
    protected function dispatchEvent( QueryExecuted $event, float $timeMs, array $trace ): void
    {
        Event::dispatch( new SlowQueryDetected(
            query: $event->sql,
            timeMs: $timeMs,
            trace: $trace,
            bindings: $event->bindings,
        ) );
    }

    /**
     * Resolves the application file and line that issued the query.
     *
     * Walks `debug_backtrace()` looking for the first frame OUTSIDE the
     * package, Illuminate framework, and any vendor path. Frames are
     * walked without arguments to keep the work cheap on hot paths.
     * Returns nulls when no suitable frame is found (typical when the
     * query runs from a tinker shell or a test runner).
     *
     * @since 1.0.0
     *
     * @return array{file: string|null, line: int|null}
     */
    protected function resolveCaller(): array
    {
        $frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 );

        foreach ( $frames as $frame ) {
            $file = $frame['file'] ?? null;

            if ( null === $file ) {
                continue;
            }

            if ( $this->isFrameworkFrame( $file ) ) {
                continue;
            }

            return [
                'file' => $file,
                'line' => isset( $frame['line'] ) ? (int) $frame['line'] : null,
            ];
        }

        return [ 'file' => null, 'line' => null ];
    }

    /**
     * Captures a compact stack trace for the logged payload.
     *
     * Trims each frame to the file/line/function/class fields used by
     * the dashboard so the JSON column stays small. The full trace
     * (with arguments) is intentionally avoided — it can be megabytes
     * on a deep stack and would blow out the column.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function captureTrace(): array
    {
        $frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 );
        $trace  = [];

        foreach ( $frames as $frame ) {
            $trace[] = [
                'file'     => $frame['file'] ?? null,
                'line'     => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class'    => $frame['class'] ?? null,
            ];
        }

        return $trace;
    }

    /**
     * Reports whether a file path belongs to the framework or this package.
     *
     * The check is path-substring based — a single `str_contains` per
     * needle is cheap and side-steps OS-specific separator quirks.
     *
     * @since 1.0.0
     *
     * @param  string  $file  Absolute file path.
     */
    protected function isFrameworkFrame( string $file ): bool
    {
        foreach ( [
            DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'laravel' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'illuminate' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'artisanpack-ui' . DIRECTORY_SEPARATOR . 'performance' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'ArtisanPackUI' . DIRECTORY_SEPARATOR . 'Performance' . DIRECTORY_SEPARATOR,
        ] as $needle ) {
            if ( str_contains( $file, $needle ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves the current route name for the captured payload.
     *
     * Returns null when called outside an HTTP request lifecycle (e.g.
     * from a queued job or scheduled command) so the `route` column
     * reflects "no route" rather than a stale label from a prior request.
     *
     * @since 1.0.0
     */
    protected function resolveRoute(): ?string
    {
        try {
            $route = Request::route();
        } catch ( Throwable ) {
            return null;
        }

        if ( null === $route ) {
            return null;
        }

        return $route->getName() ?? $route->uri();
    }
}
