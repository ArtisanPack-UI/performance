<?php

declare( strict_types=1 );

namespace Tests\Benchmarks;

/**
 * Tiny stats helper shared by the benchmark files.
 *
 * Runs a callback N times and reports mean, median, min, max, and total
 * wall-clock in milliseconds. Prints a single formatted line so the
 * benchmark suite can be diffed across runs with a plain `diff`.
 *
 * @since 1.0.0
 */
final class BenchmarkReport
{
    /**
     * Runs the callback N times and prints a one-line stats summary.
     *
     * The warmup pass is discarded so opcache priming and autoloader
     * warmup do not skew the first-iteration timing. Returns the raw
     * stats so a caller can assert on a soft ceiling (`expect(mean_ms)
     * ->toBeLessThan(200.0)`) or diff outputs across runs.
     *
     * @since 1.0.0
     *
     * @param  string  $label  Human-readable label emitted on the report line.
     * @param  int  $iterations  Number of measured iterations (warmup pass excluded).
     * @param  callable  $callback  Zero-arg callable to time.
     *
     * @return array{label: string, iterations: int, mean_ms: float, median_ms: float, min_ms: float, max_ms: float, total_ms: float}
     */
    public static function measure(
        string $label,
        int $iterations,
        callable $callback,
    ): array {
        $timings = [];

        // One warmup pass to prime opcache + autoloader; excluded from stats.
        $callback();

        for ( $i = 0; $i < $iterations; $i++ ) {
            $start = hrtime( true );
            $callback();
            $timings[] = ( hrtime( true ) - $start ) / 1_000_000; // ns → ms
        }

        sort( $timings );

        $count  = count( $timings );
        $sum    = array_sum( $timings );
        $mean   = $sum / $count;
        $median = 0 === $count % 2
            ? ( $timings[ intdiv( $count, 2 ) - 1 ] + $timings[ intdiv( $count, 2 ) ] ) / 2
            : $timings[ intdiv( $count, 2 ) ];

        $summary = [
            'label'      => $label,
            'iterations' => $iterations,
            'mean_ms'    => round( $mean, 3 ),
            'median_ms'  => round( $median, 3 ),
            'min_ms'     => round( $timings[0], 3 ),
            'max_ms'     => round( $timings[ $count - 1 ], 3 ),
            'total_ms'   => round( $sum, 3 ),
        ];

        printf(
            "\n  BENCH  %-50s  n=%d  mean=%7.3fms  median=%7.3fms  min=%7.3fms  max=%7.3fms\n",
            $summary['label'],
            $summary['iterations'],
            $summary['mean_ms'],
            $summary['median_ms'],
            $summary['min_ms'],
            $summary['max_ms'],
        );

        return $summary;
    }

    /**
     * Skips the current test unless `PERF_RUN_BENCHMARKS=1` is set.
     *
     * Invoked from the shared `beforeEach` hook in `tests/Pest.php` so
     * benchmark files never accidentally run as part of the regular
     * test-run pipeline. Individual files no longer need to call this
     * themselves; call sites are kept for direct-use scenarios (running
     * a single benchmark without the Pest suite wrapper).
     *
     * @since 1.0.0
     *
     * @param  object  $test  The current Pest / PHPUnit test instance (typically `$this`).
     */
    public static function skipIfNotEnabled( object $test ): void
    {
        if ( '1' !== getenv( 'PERF_RUN_BENCHMARKS' ) ) {
            $test->markTestSkipped(
                'Benchmarks are opt-in. Run with PERF_RUN_BENCHMARKS=1 vendor/bin/pest tests/Benchmarks.',
            );
        }
    }
}
