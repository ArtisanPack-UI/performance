---
title: Benchmarks
---

# Benchmarks

Methodology, tooling, and target metrics for measuring the impact of the `artisanpack-ui/performance` package against a real application. The benchmark surface splits cleanly into two layers, and both matter:

- **Client-side (Core Web Vitals)** — what the user experiences, measured in a real browser or a headless surrogate (Lighthouse, WebPageTest).
- **Server-side (implementation overhead)** — how expensive each feature is to enable, measured with Pest-based micro-benchmarks in `tests/Benchmarks/`.

Neither layer replaces the other. A feature that shaves 800 ms off LCP but costs 400 ms of PHP time on every request is a wash — ship a change only when both dials move in the right direction.

## Reference Pages

- [[benchmarks/client-side]] — Lighthouse recipe, page types to measure, reporting format
- [[benchmarks/server-side]] — What the Pest benchmark suite measures and how to interpret its output

## Target Metrics (Web Vitals)

Targets are the "green" thresholds from [web.dev/vitals](https://web.dev/vitals/) with a per-metric ambition tuned to the median site after the package is enabled.

| Metric | Without Package | With Package | Improvement Target |
| ------ | --------------- | ------------ | ------------------ |
| **LCP** (Largest Contentful Paint) | > 4.0 s | < 2.5 s | 40%+ |
| **FID** (First Input Delay) | > 200 ms | < 100 ms | 50%+ |
| **INP** (Interaction to Next Paint) | > 500 ms | < 200 ms | 60%+ |
| **CLS** (Cumulative Layout Shift) | > 0.25 | < 0.10 | 60%+ |
| **TTFB** (Time to First Byte) | > 1.5 s | < 800 ms | 45%+ |

Adjacent secondary metrics worth tracking without a hard target: total page weight, HTTP request count, time to interactive (TTI), and server CPU time per request.

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

## Reporting Format

For every release, record a `results-<version>.md` file alongside these docs using the template below. Keep it terse — one table, one summary sentence, links to any traces/HAR files kept elsewhere.

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

One or two sentences on anything unusual — a regression, an outlier run, an OS-level knob that mattered.
```
