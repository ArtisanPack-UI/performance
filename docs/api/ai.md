---
title: AI Reference
---

# AI Reference

Programmatic surface of the two AI features introduced in 1.1.0. For a walkthrough that shows how to wire them into an application, see the [[guides/ai-features]] guide.

## Agents

Both agents extend `ArtisanPackUI\Ai\Agents\ArtisanPackAgent` and are self-registered against `artisanpack-ui/ai` through `PerformanceServiceProvider::aiFeatures()`.

### `PerformanceInsightAgent`

Namespace: `ArtisanPackUI\Performance\Ai\Agents\PerformanceInsightAgent`

| Field           | Value                                                                                                        |
|-----------------|--------------------------------------------------------------------------------------------------------------|
| `featureKey`    | `performance.query_insight`                                                                                  |
| `package`       | `artisanpack-ui/performance`                                                                                 |
| `defaultModel`  | `claude-sonnet-4-6`                                                                                          |
| `cacheable`     | `false` — outputs are point-in-time diagnostic snapshots.                                                    |

Input:

```
[
    'query'      => string (required),
    'explain'    => array | string | null,
    'schema'     => array | string | null,
    'time_ms'    => int | float | null,
    'connection' => string | null,
]
```

Output:

```
{
    summary:           string,
    bottlenecks:       string[],
    suggested_indexes: [ { table: string, columns: string[], rationale: string } ],
    rewrites:          [ { original: string, suggested: string, rationale: string } ],
    caveats:           string[]
}
```

Rows returned by the model that are missing a `table` / `columns` / `original` / `suggested` field are dropped silently by `validateOutput()` — the final payload always matches the shape above.

### `OptimizationSuggestionAgent`

Namespace: `ArtisanPackUI\Performance\Ai\Agents\OptimizationSuggestionAgent`

| Field           | Value                                                                                                                |
|-----------------|----------------------------------------------------------------------------------------------------------------------|
| `featureKey`    | `performance.optimization_suggestion`                                                                                |
| `package`       | `artisanpack-ui/performance`                                                                                         |
| `defaultModel`  | `claude-sonnet-4-6`                                                                                                  |
| `cacheable`     | `false` — outputs reflect the current metrics window.                                                                |

Input:

```
[
    'range'   => [ 'from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD' ],
    'metrics' => array<int, array<string, mixed>>,
    'context' => [
        'business_priority' => string | null,
        'recent_changes'    => string | null,
        'traffic_mix'       => string | null,
    ],
]
```

Output:

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

Impact / effort values outside the enumerated set are clamped to `medium`. An empty `metrics` list short-circuits without calling the provider.

## HTTP endpoints

Wired by `PerformanceServiceProvider::registerRoutes()` from `routes/api-ai.php` under the `ai_middleware` config (default `['api', 'auth:sanctum']`).

| Method | Path                                             | Controller method                                        |
|--------|--------------------------------------------------|----------------------------------------------------------|
| POST   | `/api/performance/ai/query-insight`              | `AiAgentApiController::queryInsight`                     |
| POST   | `/api/performance/ai/optimization-suggestion`    | `AiAgentApiController::optimizationSuggestion`           |

Success envelope: `{ data: <agent output>, feature_key: string }`.

Error status codes:

| Status | Meaning                                                                        |
|--------|--------------------------------------------------------------------------------|
| 403    | The `performance.ai.use` Gate denied the caller.                               |
| 404    | Unknown feature key (should never happen against the shipped endpoints).       |
| 409    | Feature toggle is off in the registry.                                         |
| 412    | `artisanpack-ui/ai` cannot resolve credentials.                                |
| 422    | Form-request validation failed, or the agent raised `FeatureError`.            |
| 500    | Unexpected error; the exception is `report()`ed before the response is sent.   |

The controller uses a static `AGENTS` map keyed by feature key rather than reflecting through the registry so a caller cannot execute an arbitrary agent class by naming it in the request body.

## Configuration

`config/performance.php`:

```php
'routes' => [
    // …
    // Middleware for the AI JSON API endpoints. These dispatch to paid LLM
    // providers, so the shipped default gates them behind Sanctum plus the
    // performance.ai.use Gate.
    'ai_middleware' => [ 'api', 'auth:sanctum' ],
],
```

`config/artisanpack/ai.php` (from `artisanpack-ui/ai`):

```php
'features' => [
    'performance.query_insight'          => [ 'enabled' => true ],
    'performance.optimization_suggestion' => [ 'enabled' => true ],
],
```

## Authorization Gate

`PerformanceServiceProvider::registerAiGate()` defines a default `performance.ai.use` Gate that allows any authenticated user:

```php
Gate::define( 'performance.ai.use', static function ( $user = null ): bool {
    return null !== $user;
} );
```

Registration is guarded so a host-registered override (defined before boot) wins. The controller's `dispatchAgent()` method also checks the Gate belt-and-braces, so even if the AI route middleware is loosened, unauthorized callers still receive a 403.

## Livewire panels

Two Livewire components ship as trigger UIs for the agents. See [[api/livewire]] for the full component reference.

- `perf-ai-query-insight-panel` (`ArtisanPackUI\Performance\Livewire\Ai\QueryInsightPanel`)
- `perf-ai-optimization-suggestion-panel` (`ArtisanPackUI\Performance\Livewire\Ai\OptimizationSuggestionPanel`)

## JavaScript client

Two methods on `PerformanceClient` cover the endpoints; see [[api/javascript]] for the full client reference.

- `suggestQueryInsight(input)`
- `suggestOptimization(input)`

Both throw `PerformanceAiError` on non-2xx responses and on malformed 2xx bodies (empty response, non-JSON payload, missing `data` key).
