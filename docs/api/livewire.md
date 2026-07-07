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

## `perf-ai-query-insight-panel` — `Livewire\Ai\QueryInsightPanel`

Trigger UI for the [`PerformanceInsightAgent`](ai.md#performanceinsightagent). Renders a form for the query text, EXPLAIN plan, schema hint (JSON or plain text), connection driver, and observed duration; runs the agent on submit and displays the summary, ranked bottlenecks, suggested indexes, suggested rewrites, and caveats. Emits `performance-ai-insight-generated` with the full agent output when a suggestion is produced, and listens for `performance-ai-query-selected` so a sibling component (typically `perf-query-analyzer`) can load a slow query into the panel with one click.

Mount attributes: `query`, `explain`, `schema`, `connection`, `time-ms` — all optional; use them to pre-populate the form from a parent editor.

```blade
<livewire:perf-ai-query-insight-panel />
```

*Introduced in 1.1.0.*

## `perf-ai-optimization-suggestion-panel` — `Livewire\Ai\OptimizationSuggestionPanel`

Trigger UI for the [`OptimizationSuggestionAgent`](ai.md#optimizationsuggestionagent). Reads aggregate rows out of `performance_metrics` for a rolling window (default 7 days, capped at 90 via `MAX_WINDOW_DAYS`) and forwards optional `businessPriority` / `recentChanges` context to the agent. Rows are ordered by `sample_count DESC` and hard-capped at `MAX_METRICS_ROWS = 2000` so the request stays bounded even on installs with very large aggregate tables.

Emits `performance-ai-optimization-generated` with the full agent output. Listens for `performance-ai-context-updated` so a parent dashboard can push a new window or context object without recreating the component.

```blade
<livewire:perf-ai-optimization-suggestion-panel :window-days="14" />
```

*Introduced in 1.1.0.*

## Authorization

The service provider does not register a gate for the dashboard components — wrap the routes that expose them behind whichever authorization surface your application already uses (a policy, a gate, Sanctum, `auth.admin` middleware, etc.).

The AI components additionally check the `performance.ai.use` Gate (permissive default: any authenticated user) so the AI feature keys can be scoped independently of the dashboard. Override the Gate in your `AuthServiceProvider` to enforce a stricter policy — see the [[guides/ai-features]] guide.
