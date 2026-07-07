---
title: AI Features
---

# AI Features

The performance package ships two AI-powered features, both introduced in 1.1.0 and both built on top of `artisanpack-ui/ai`:

| Feature key                            | Purpose                                                                                                    |
|----------------------------------------|------------------------------------------------------------------------------------------------------------|
| `performance.query_insight`            | Explain why a single slow query is slow and suggest indexes + rewrites (never emits DDL).                  |
| `performance.optimization_suggestion`  | Look at aggregate performance metrics over a date range and recommend where to focus optimization work.    |

Both features are opt-in through the `artisanpack-ui/ai` feature toggle. Nothing runs against a provider until the toggle is on and credentials are configured.

---

## Requirements

- `artisanpack-ui/ai` 1.0 or later configured with a provider and credentials — see the [BYOK guide](https://github.com/ArtisanPack-UI/ai) in that package.
- A Sanctum-authenticated caller (default) or a host-provided authentication middleware — the AI endpoints do not share the anonymous middleware used by the Web Vitals ingest.

---

## Feature toggle

Each feature key must be toggled on before the agent will run. Toggle state lives in the `artisanpack.ai.features.<key>.enabled` config or in the `ai_features` settings table if you use the shipped admin surface.

```php
// config/artisanpack/ai.php or a host override
'features' => [
    'performance.query_insight'          => [ 'enabled' => true ],
    'performance.optimization_suggestion' => [ 'enabled' => true ],
],
```

When a feature is disabled the agent throws `FeatureDisabledException` and the shipped HTTP + Livewire surfaces render a "This AI feature is disabled." state instead of calling the provider.

---

## Authorization

AI endpoints are gated by the `performance.ai.use` Gate. The package ships a permissive default (any authenticated user is allowed) so upgrades are non-breaking; installers should override it in their own `AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define( 'performance.ai.use', function ( $user ): bool {
    return $user?->hasRole( 'admin' ) ?? false;
} );
```

The Gate is checked inside `AiAgentApiController::dispatchAgent()` as well as by the route middleware, so denials return HTTP 403 with `{ message, feature_key }` regardless of the middleware stack you configure.

The AI route middleware stack itself is configurable — see `config/performance.php`:

```php
'routes' => [
    // …
    'ai_middleware' => [ 'api', 'auth:sanctum' ], // change to fit your auth model
],
```

---

## Query insight — `performance.query_insight`

Given a slow query, the agent returns a diagnosis, ranked bottlenecks, suggested indexes, suggested rewrites, and caveats. The agent never issues DDL — it hands the operator a report to review before writing a migration.

### Input

```php
[
    'query'      => 'SELECT * FROM articles WHERE slug = ? ORDER BY published_at DESC',
    'explain'    => "id | select_type | table    | type | rows  | Extra\n 1 | SIMPLE      | articles | ALL  | 42000 | Using where; Using filesort",
    'schema'     => [ 'articles' => [ 'id' => 'bigint', 'slug' => 'varchar(191)' ] ],
    'time_ms'    => 850.4,
    'connection' => 'mysql',
]
```

`schema` accepts an associative array, a plain-text description, or `null`. `explain` accepts JSON output (as an array) or the raw text a client typed in.

### Output

```
{
    summary:           string,
    bottlenecks:       string[],
    suggested_indexes: [ { table: string, columns: string[], rationale: string } ],
    rewrites:          [ { original: string, suggested: string, rationale: string } ],
    caveats:           string[]
}
```

### Direct PHP invocation

```php
use ArtisanPackUI\Performance\Ai\Agents\PerformanceInsightAgent;

$insight = PerformanceInsightAgent::for( [
    'query'   => $slowQuery->query,
    'explain' => $slowQuery->explain_plan,
    'time_ms' => (float) $slowQuery->time_ms,
] )->run();
```

### Caching

Query-insight outputs are point-in-time diagnostic snapshots. Once the operator ships one of the suggestions the previous diagnosis is stale, so the agent declares `$cacheable = false` and every run hits the provider fresh.

---

## Optimization suggestion — `performance.optimization_suggestion`

Given a batch of aggregate metrics over a date range, the agent returns a portfolio-level diagnosis plus ranked focus areas and quick wins. Distinct from query insight: that agent works on a single query; this one triages the whole application.

### Input

```php
[
    'range'   => [ 'from' => '2026-07-01', 'to' => '2026-07-07' ],
    'metrics' => [
        [ 'metric' => 'lcp', 'route' => 'GET /articles/{slug}', 'p50' => 2100, 'p75' => 4600, 'p90' => 6200, 'p99' => 9800, 'samples' => 830, 'device' => 'mobile' ],
        // …
    ],
    'context' => [
        'business_priority' => 'checkout > blog',
        'recent_changes'    => 'switched to Vite last week',
        'traffic_mix'       => '65% mobile, 35% desktop',
    ],
]
```

`context` is optional. When the metrics list is empty the agent short-circuits without calling the provider and returns a "No metrics to analyze" summary — you never spend tokens on an empty prompt.

### Output

```
{
    summary:     string,
    focus_areas: [
        {
            title:     string,
            routes:    string[],
            impact:    'high' | 'medium' | 'low',
            effort:    'high' | 'medium' | 'low',
            rationale: string,
            actions:   string[]
        }
    ],
    quick_wins:  string[],
    caveats:     string[]
}
```

Model output that supplies an out-of-vocabulary impact/effort level is clamped to `medium` so downstream UIs never render surprise values.

---

## Trigger surfaces

Both features ship UI in all three supported front-ends. Pick the surface that matches your host application; they all POST to the same endpoints under the hood.

### Livewire

```blade
<livewire:perf-ai-query-insight-panel />

<livewire:perf-ai-optimization-suggestion-panel :window-days="14" />
```

- `perf-ai-query-insight-panel` accepts optional `query`, `explain`, `schema`, `connection`, and `time-ms` mount attributes so a parent editor can seed the form.
- `perf-ai-optimization-suggestion-panel` reads its metric batch straight from `performance_metrics` for the last `windowDays` days (capped at 90) and forwards optional `businessPriority` / `recentChanges` context. Rows are ordered by `sample_count DESC` and capped at 2000 so busy installs don't overflow the prompt.

Both panels react to `#[On(...)]` events so parent components can push updated context in without a hard dependency.

### React

```tsx
import {
    QueryInsightPanel,
    OptimizationSuggestionPanel,
} from '@artisanpack-ui/performance/react';

<QueryInsightPanel clientOptions={{ baseUrl: '/api/performance' }} />

<OptimizationSuggestionPanel
    clientOptions={{ baseUrl: '/api/performance' }}
    range={{ from: '2026-07-01', to: '2026-07-07' }}
    metrics={metrics}
/>
```

The React components are exported from `@artisanpack-ui/performance/react`, not the shared `@artisanpack-ui/react` package — the performance package supports both React and Vue on its own so you never have to install the shared UI packages just to get an AI trigger.

### Vue

```vue
<script setup lang="ts">
import {
    QueryInsightPanel,
    OptimizationSuggestionPanel,
} from '@artisanpack-ui/performance/vue';
</script>

<template>
    <QueryInsightPanel :client-options="{ baseUrl: '/api/performance' }" />

    <OptimizationSuggestionPanel
        :client-options="{ baseUrl: '/api/performance' }"
        :range="{ from: '2026-07-01', to: '2026-07-07' }"
        :metrics="metrics"
    />
</template>
```

---

## HTTP API

The React and Vue triggers POST to the following endpoints; the Livewire panels dispatch the agents directly in PHP and skip HTTP.

| Method | Path                                                | Body                                                     | Success |
|--------|-----------------------------------------------------|----------------------------------------------------------|---------|
| POST   | `/api/performance/ai/query-insight`                 | `{ query, explain?, schema?, time_ms?, connection? }`    | 200     |
| POST   | `/api/performance/ai/optimization-suggestion`       | `{ range, metrics, context? }`                           | 200     |

Both endpoints respond with `{ data: <agent output>, feature_key: string }` on success. Error shapes:

| Status | When                                                   |
|--------|--------------------------------------------------------|
| 403    | The `performance.ai.use` Gate denied the caller.       |
| 404    | Unknown feature key.                                   |
| 409    | Feature toggle is off in the registry.                 |
| 412    | `artisanpack-ui/ai` cannot resolve credentials.        |
| 422    | Agent input failed validation.                         |
| 500    | Unexpected error; the exception is `report()`ed first. |

### JavaScript client

```ts
import { getPerformanceClient, PerformanceAiError } from '@artisanpack-ui/performance';

const client = getPerformanceClient({ baseUrl: '/api/performance' });

try {
    const insight = await client.suggestQueryInsight({
        query: 'SELECT * FROM articles WHERE slug = ?',
        time_ms: 210,
    });
} catch (error) {
    if (error instanceof PerformanceAiError) {
        console.warn(`AI feature ${error.featureKey} unavailable (${error.status}): ${error.message}`);
    } else {
        throw error;
    }
}
```

`PerformanceAiError` carries the HTTP status and the feature key so UI callers can render disabled / misconfigured states without parsing exception messages. Malformed 2xx bodies (empty responses, non-JSON) are also converted into `PerformanceAiError`s so you never see an uncaught `TypeError` from a `.data` deref.

---

## Adding your own agents

Neither of the shipped agents is final — subclass or replace them in your host application by binding your class to the same feature key on the container. See the [`artisanpack-ui/ai` overriding-agents guide](https://github.com/ArtisanPack-UI/ai) for the pattern.

If you build an entirely new performance-flavoured agent, register it through your own service provider's `aiFeatures()` method so `artisanpack-ui/ai` auto-discovers it alongside the shipped features.
