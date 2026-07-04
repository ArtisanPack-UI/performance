# Models

All models live under `ArtisanPackUI\Performance\Models`.

## PerformanceMetric

Stored in `performance_metrics`. Aggregated Web Vitals — one row per (metric, date, aggregation window) with computed percentiles.

| Column | Type | Purpose |
|---|---|---|
| `metric` | string | Metric key (`LCP`, `CLS`, `FID`, `INP`, `TTFB`, `FCP`) |
| `date` | date | Bucket date |
| `interval` | string | `hourly` or `daily` |
| `p50` / `p75` / `p95` / `p99` | float | Computed percentiles |
| `sample_count` | int | Number of raw samples that fed this row |
| `context` | json | Optional dimensions (route, device class, etc.) |

Populated by `MetricsAggregator::aggregate()` (run via `perf:aggregate-metrics`).

## RawMetric

Stored in `performance_raw_metrics`. Individual RUM samples posted by the browser bundle.

| Column | Type | Purpose |
|---|---|---|
| `metric` | string | Metric key |
| `value` | float | Raw sample value |
| `url` | string\|null | Page the sample originated from |
| `session_id` | string\|null | Anonymous session identifier |
| `context` | json | Additional client-side context |
| `created_at` | datetime | Sample time |

Rows are drained into `performance_metrics` by the aggregator and pruned according to `monitoring.retention_days`.

## SlowQuery

Stored in `performance_slow_queries`. Written by `SlowQueryLogger` when `slow_query_logging.store_in_database` is enabled.

| Column | Type | Purpose |
|---|---|---|
| `query` | text | Raw SQL |
| `normalized` | text | Query with parameter placeholders replaced by `?` |
| `time_ms` | float | Execution time |
| `bindings` | json | Bindings for the raw query |
| `route` | string\|null | Route name if resolvable |
| `trace` | json\|null | Trimmed stack trace |
| `created_at` | datetime | Capture time |

The `QueryAnalyzer` Livewire component groups rows by `normalized` so repeat offenders collapse to a single dashboard entry.
