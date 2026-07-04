# Livewire components

Every component is registered by the service provider under the `perf-` alias prefix. Livewire is a **suggested** dependency — the components register only when Livewire is present.

## `perf-performance-dashboard` — `PerformanceDashboard`

Top-level dashboard surface. Composes a date-range picker, a tab strip, and the per-tab summary data sourced from the aggregated `performance_metrics` table and the cache statistics helpers. Read-only; mutating cache actions live on `perf-cache-manager`.

```blade
<livewire:perf-performance-dashboard />
```

## `perf-metrics-chart` — `MetricsChart`

Renders a daily time series of one or more Web Vitals as a line chart. Reads from the aggregated `performance_metrics` table (`p75` column) and emits a `<canvas>` plus a data island the client-side Chart.js bootstrap consumes.

```blade
<livewire:perf-metrics-chart metric="LCP" days="30" />
```

Include `@perfMetricsChartAssets` in the layout when mounting outside the dashboard.

## `perf-cache-manager` — `CacheManager`

Exposes the page and fragment cache controls — invalidate by key, invalidate by tag, flush both stores, and trigger cache warming — as a single dashboard surface. Destructive actions go through an inline confirmation step.

```blade
<livewire:perf-cache-manager />
```

## `perf-query-analyzer` — `QueryAnalyzer`

Admin surface for reviewing slow queries captured by `SlowQueryLogger` into `performance_slow_queries`. Groups rows by their normalized signature so repeat offenders collapse to a single row; ranks by either total time or occurrence count.

```blade
<livewire:perf-query-analyzer />
```

## `perf-recommendations-panel` — `RecommendationsPanel`

Renders the ranked recommendation list produced by `RecommendationEngine`. Supports dismissing individual recommendations (session-scoped by default) and exposes a small set of one-click action handlers where the fix is deterministic (e.g. applying an index migration recommendation, which fires the `IndexMigrationRequested` event).

```blade
<livewire:perf-recommendations-panel />
```

## Authorization

The service provider does not register a gate for these components. Wrap the routes that expose them behind whichever authorization surface your application already uses (a policy, a gate, Sanctum, `auth.admin` middleware, etc.).
