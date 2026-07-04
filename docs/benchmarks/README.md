# Performance Benchmarks

This directory documents the methodology, tooling, and target metrics
for measuring the impact of the `artisanpack-ui/performance` package
against a real application.

The benchmark surface splits cleanly into two layers, and both matter
for a release-1.0 sign-off:

- **Client-side (Core Web Vitals)** — what the user experiences. Measured
  in a real browser or a headless surrogate (Lighthouse, WebPageTest).
  See [`client-side.md`](client-side.md).
- **Server-side (implementation overhead)** — how expensive each feature
  is to enable. Measured with Pest-based micro-benchmarks in
  `tests/Benchmarks/`. See [`server-side.md`](server-side.md).

Neither layer replaces the other. A feature that shaves 800 ms off LCP
but costs 400 ms of PHP time on every request is a wash. Ship a change
only when both dials move in the right direction.

## Target Metrics (Web Vitals)

Targets are the "green" thresholds from
[web.dev/vitals](https://web.dev/vitals/) with a per-metric ambition
tuned to the median site after the package is enabled.

| Metric | Without Package | With Package | Improvement Target |
| ------ | --------------- | ------------ | ------------------ |
| **LCP** (Largest Contentful Paint) | > 4.0 s | < 2.5 s | 40%+ |
| **FID** (First Input Delay) | > 200 ms | < 100 ms | 50%+ |
| **INP** (Interaction to Next Paint) | > 500 ms | < 200 ms | 60%+ |
| **CLS** (Cumulative Layout Shift) | > 0.25 | < 0.10 | 60%+ |
| **TTFB** (Time to First Byte) | > 1.5 s | < 800 ms | 45%+ |

Adjacent secondary metrics we track without a hard target:

- Total page weight (bytes)
- Total number of HTTP requests
- Time to interactive (TTI)
- Server CPU time per request (from `nginx-access-log` `$request_time`
  or Laravel's request middleware)

## Per-Feature Deliverables

| Feature | Client-side lever | Server-side benchmark |
| ------- | ----------------- | --------------------- |
| Image optimization | LCP, page weight | Optimizer wall-time per image |
| WebP/AVIF conversion | LCP, page weight | Encode wall-time per image, byte savings |
| Lazy loading | LCP (below-fold images), page weight | Marker-injection cost per image |
| JS defer/async | INP, TTI | Registration cost per handle |
| Conditional JS loading | INP, TTI | Conditional-parked script build cost |
| Critical CSS inlining | LCP, CLS | `CriticalCssExtractor::extract()` wall-time + byte savings |
| Page cache | TTFB | `PageCacheManager::cacheResponse()` write / `cachedResponse()` read wall-time |
| Fragment cache | TTFB, LCP | `FragmentCache::remember()` wall-time |
| HTML minification | Page weight | `HtmlMinifier::minify()` wall-time + byte savings |
| Database query optimization | TTFB | Query wall-time delta on real workloads |
| Query cache | TTFB | `CachingEloquentBuilder` HIT vs MISS wall-time |
| Resource hints | LCP (preconnect wins) | Hint-render cost per page |

## Running the Server-side Benchmark Suite

The suite lives under `tests/Benchmarks/` and is registered as its own
PHPUnit test suite so it never runs as part of the regular test-run
pipeline.

```bash
# Skips silently unless PERF_RUN_BENCHMARKS=1 is set.
./vendor/bin/pest --testsuite=Benchmarks

# Actually runs the suite.
PERF_RUN_BENCHMARKS=1 ./vendor/bin/pest --testsuite=Benchmarks
```

Each benchmark prints one line per measurement:

```
BENCH  HtmlMinifier::minify()          n=200  mean=  0.312ms  median=  0.301ms  min=  0.284ms  max=  0.842ms
BENCH  html minifier size reduction    14180 → 9482 bytes  (33.1% savings)
```

Numbers are deterministic under stable CPU load — capture them from a
warmed-up runner (`--warm-up-cache` on GitHub Actions or a quiet local
box) if you want to diff runs.

## Running the Client-side Benchmark Suite

Client-side measurements happen against a running application; the
package itself cannot bring them up. The recommended flow:

1. Deploy the application in two configurations: one with `PERF_*=false`
   for every feature toggle (baseline), one with the intended production
   feature set enabled.
2. Point Lighthouse (or WebPageTest, or `web-vitals-cli`) at each URL and
   capture LCP / FID / INP / CLS / TTFB.
3. Record the deltas in a `results-<version>.md` file next to this
   README so future releases have a comparison base line.

See [`client-side.md`](client-side.md) for the step-by-step recipe.

## Reporting Format

For every release, create a `results-<version>.md` file in this directory
using the template below. Keep it terse — one table, one summary
sentence, links to any traces/HAR files kept elsewhere.

```markdown
# Benchmark Results — v<version>

- Date: YYYY-MM-DD
- Runner: (GitHub Actions ubuntu-latest / MacBook Pro M2 / …)
- Baseline commit: <sha>
- Package commit: <sha>

## Client-side (Lighthouse Mobile, 4G throttle)

| Metric | Baseline | Package | Δ |
| ------ | -------- | ------- | -- |
| LCP    |          |         |    |
| FID    |          |         |    |
| INP    |          |         |    |
| CLS    |          |         |    |
| TTFB   |          |         |    |

## Server-side (`vendor/bin/pest --testsuite=Benchmarks`)

Paste the `BENCH …` lines here.

## Notes

One or two sentences on anything unusual — a regression, an outlier
run, an OS-level knob that mattered.
```

## Recommendations (feed the roadmap)

Each release cycle, sift the benchmark output for any metric that
regressed vs the previous release and add it as a tracking issue with
the `Perf/Regression` label. Any metric that stayed within 5% of the
previous run does not need a follow-up.
