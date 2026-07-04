# Server-side Benchmark Methodology

This document explains what the Pest-based benchmark suite under
`tests/Benchmarks/` measures, how to interpret the output, and how to
extend it.

Client-side numbers (LCP / FID / etc.) tell you how the user experience
changed. Server-side numbers tell you *why* — and whether a feature is
paying its own weight in PHP-time.

## What the Suite Measures

The bundled benchmarks target the components whose cost is entirely a
function of PHP execution — no browser or network round-trip involved.
Each file exercises a single component through a representative
workload:

| File | Measures |
| ---- | -------- |
| `CacheStrategyBenchmark.php` | `Cache::put()` / `Cache::get()` overhead on the array driver — a lower bound for the framework's own cache API. |
| `CriticalCssBenchmark.php` | `CriticalCssExtractor::extract()` wall-time and size reduction against a repeated CSS blob. |
| `HtmlMinifierBenchmark.php` | `HtmlMinifier::minify()` throughput on a realistic full-page HTML fixture. |
| `QueryCacheBenchmark.php` | Uncached vs cached `Eloquent::get()` wall-time using `CachingEloquentBuilder`. |

## Running

```bash
# Skipped by default — a bare `./vendor/bin/pest` will not run them.
PERF_RUN_BENCHMARKS=1 ./vendor/bin/pest --testsuite=Benchmarks
```

Add `--filter=<benchmark-name>` to isolate a single measurement while
you tune the underlying feature.

## Reading the Output

Every measurement prints a single line of the form:

```
BENCH  <label>  n=<iterations>  mean=  <mean>ms  median=  <median>ms  min=  <min>ms  max=  <max>ms
```

Metrics:

- `n` — sample size. Each benchmark runs a warmup pass (not counted) and
  then N timed iterations.
- `mean` — arithmetic mean.
- `median` — 50th-percentile timing. Prefer this to mean when comparing
  runs — the tail (`max`) is dominated by GC pauses and opcache
  invalidations that are noisy across machines.
- `min` — the fastest single run.
- `max` — the slowest single run. Useful for spotting outliers; ignore
  when calculating "typical" overhead.

Some benchmarks emit an extra line reporting byte savings or a speedup
ratio — that's a size reduction (bytes → bytes with percent savings) or
a wall-time comparison across two configurations.

## Interpreting Micro-benchmarks Honestly

Micro-benchmarks measure **implementation overhead**, not real-world
savings. Two things follow.

**Cache benchmarks on the array driver understate cache wins.** The
array driver runs in the same PHP process — no network round-trip, no
serialization to disk. A real Redis / Memcached / DynamoDB backend
carries a millisecond-scale round-trip on every read; that's where
Laravel-side caching earns its keep. The array-driver numbers are still
useful — they tell you the *floor* — but do not multiply them out for
production.

**`QueryCacheBenchmark.php` can show cached results being *slower* than
uncached ones on SQLite `:memory:`.** SQLite in-process is faster than
`serialize` + `hash_hmac` + `unserialize`. The benchmark is honest about
this to make the trade-off visible: query cache is a win when the
underlying query is expensive (network hop to MySQL, complex plan,
large result set). Do not enable it for every query — enable it only
for queries you have measured to be slow.

**HTML minifier can report 0.0% savings** when the minifier config is
disabled (all options default to `false` in the package config so an
uninstalled minifier costs nothing). Enable one of the size-reducing
options to see the real number.

## Adding a New Benchmark

Create `tests/Benchmarks/<Component>Benchmark.php`, extend the pattern:

```php
<?php

declare( strict_types=1 );

use Tests\Benchmarks\BenchmarkReport;

beforeEach( function (): void {
    BenchmarkReport::skipIfNotEnabled( $this );
} );

it( 'measures <thing>', function (): void {
    $subject = /* build a representative fixture */;
    $target  = new YourComponent;

    $stats = BenchmarkReport::measure(
        'YourComponent::method()',
        200,
        static fn () => $target->method( $subject ),
    );

    // Optional soft ceiling so an accidental order-of-magnitude
    // regression fails CI instead of silently passing.
    expect( $stats['mean_ms'] )->toBeLessThan( 200.0 );
} );
```

Notes:

- File suffix must end in `Benchmark.php` — the `<testsuite name="Benchmarks">`
  in `phpunit.xml` filters on that suffix so files land in the right
  suite without a manual annotation.
- Print any adjacent context (byte savings, speedup ratios) with a
  `printf` prefixed by `"  BENCH  "` so it grep-diffs cleanly against
  the rest of the suite output.
- Do not assert on absolute wall-time — CI runners drift. Use
  `expect(...)->toBeLessThan(<generous ceiling>)` if you want a
  guard-rail; otherwise omit the assertion entirely.

## Reporting

Copy the `BENCH …` lines into the release `results-<version>.md`. Keep
them verbatim — they diff cleanly across releases, which makes
regression reviews trivially fast.
