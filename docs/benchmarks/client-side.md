# Client-side Benchmark Methodology

This is the recipe for producing the Core Web Vitals numbers reported in
`results-<version>.md`. It uses two tools — Lighthouse for repeatable
lab conditions and the bundled RUM endpoint for field measurements. Both
are stock; nothing here is package-specific.

## Prerequisites

- Two application deployments (or two branches of the same deployment)
  with identical content:
  - **Baseline** — every `PERF_*` toggle set to `false`.
  - **Package** — the production feature set enabled.
- Lighthouse installed (`npm install -g lighthouse@11`).
- Chrome or Chromium available on `$PATH`.
- Optional: WebPageTest account (`npm install -g webpagetest`) for
  network throttling in a realistic geography.

## Page Types to Measure

Cover the same four page shapes for both configurations. Reuse the same
URL structure across environments so the comparison stays honest.

| Page type | Purpose | Suggested URL |
| --------- | ------- | ------------- |
| Home / marketing | LCP dominated by hero image + critical CSS | `/` |
| Listing / grid | LCP + CLS under many thumbnails | `/products` |
| Detail | INP dominated by heavy JS interactions | `/products/<popular-sku>` |
| Form / checkout | TTFB + INP + FID under a form-heavy layout | `/checkout` |

## Lab Runs (Lighthouse)

Two 3-run averages per URL — one baseline, one with the package. The
`--only-categories=performance` flag skips the SEO / a11y / PWA passes
so the run is faster and less noisy.

```bash
# Baseline
for i in 1 2 3; do
    lighthouse "https://baseline.example.com/" \
        --form-factor=mobile \
        --throttling-method=simulate \
        --only-categories=performance \
        --output=json \
        --output-path=./bench/baseline-home-$i.json \
        --quiet
done

# Package
for i in 1 2 3; do
    lighthouse "https://package.example.com/" \
        --form-factor=mobile \
        --throttling-method=simulate \
        --only-categories=performance \
        --output=json \
        --output-path=./bench/package-home-$i.json \
        --quiet
done
```

Extract the fields we care about with `jq`:

```bash
jq '{
    lcp: .audits["largest-contentful-paint"].numericValue,
    fid: .audits["max-potential-fid"].numericValue,
    inp: .audits["interaction-to-next-paint"].numericValue,
    cls: .audits["cumulative-layout-shift"].numericValue,
    ttfb: .audits["server-response-time"].numericValue,
    total_bytes: .audits["total-byte-weight"].numericValue,
    total_requests: (.audits["network-requests"].details.items | length)
}' ./bench/package-home-1.json
```

Median of the three runs is the number that lands in the results table.

## Field Runs (Bundled RUM)

Once the package is deployed, the bundled `web-vitals.js` client posts
LCP / FID / INP / CLS / TTFB samples to
`POST {api_prefix}/metrics`. To capture field data:

1. Enable `PERF_MONITORING=true` and `PERF_MONITORING_STORE_RAW=true`.
2. Optionally set `PERF_MONITORING_SAMPLE_RATE=10` so you capture 10%
   of real traffic.
3. Run `perf:aggregate-metrics` on an hourly schedule.
4. Query `performance_metrics` for the p50/p75/p90/p99 rollups.

A one-liner for the p75 LCP per route over the last 7 days:

```php
DB::table( 'performance_metrics' )
    ->where( 'name', 'LCP' )
    ->where( 'date', '>=', now()->subDays( 7 ) )
    ->select( 'route' )
    ->selectRaw( 'AVG(percentile_75) as p75_lcp' )
    ->groupBy( 'route' )
    ->orderByDesc( 'p75_lcp' )
    ->limit( 20 )
    ->get();
```

## Per-Feature Isolation Runs

To attribute an LCP improvement to a specific feature, toggle features
one at a time and re-run:

```
Iteration 0:  All features off (baseline).
Iteration 1:  + image optimization.
Iteration 2:  + WebP conversion.
Iteration 3:  + critical CSS.
Iteration 4:  + resource hints.
Iteration 5:  + page cache.
```

The delta between iteration N and iteration N-1 attributes to the
feature you enabled in iteration N. Order matters — features that
compound (page cache after critical CSS after image optimization) show
larger improvements than they would in isolation.

## Network Conditions

Stick to Lighthouse's `simulate` throttling for lab runs — the emulated
"Slow 4G" profile (150 kbps up, 1.6 Mbps down, 150 ms RTT) is a fair
mobile stand-in without cross-run variance from real network jitter.

For a realistic geo-distributed measurement, use WebPageTest with the
same profile from at least three locations:

```bash
webpagetest test https://package.example.com/ \
    --location Dulles:Chrome.4G \
    --runs 3 \
    --first
```

## Reporting

Add the numbers to `results-<version>.md` under the "Client-side" table.
Include the runner (device, OS), Lighthouse version, and geographical
location. A benchmark with no environment context ages badly.
