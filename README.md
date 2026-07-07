# ArtisanPack UI Performance

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A comprehensive performance optimization toolkit for Laravel applications. Image optimization, JS/CSS strategies, resource hints, speculative loading, page and fragment caching, database query analysis, and a real-user performance monitoring dashboard — with Livewire, React, **and** Vue front-ends.

## Highlights

- 🖼 **Image optimization** — WebP/AVIF conversion, responsive sizes, dominant-color LQIP, lazy loading
- ⚡ **JS & CSS strategies** — deferred / async / conditional scripts, critical CSS extraction, HTML minification
- 🔮 **Speculative loading** — Speculation Rules API for prefetch and prerender
- 🔗 **Resource hints & Early Hints** — preconnect, dns-prefetch, preload, HTTP 103 Early Hints
- 🗄 **Caching** — page cache, fragment cache, tag-based invalidation, cache warming
- 🧮 **Database optimization** — N+1 detection, slow query logging, query cache, index suggestions
- 📊 **Real-user monitoring** — Web Vitals collection, aggregation, and a Livewire admin dashboard
- 🤖 **AI features** — query-insight and portfolio-level optimization suggestions, with Livewire, React, and Vue trigger components in the box
- 🧰 **Artisan tooling** — `perf:install`, `perf:warm-cache`, `perf:purge-cache`, `perf:aggregate-metrics`, `perf:critical-css`, `perf:generate-webp`, `perf:suggest-indexes`
- 🎛 **Every feature is opt-in** — nothing runs until you toggle it on

## Requirements

- PHP 8.2+ for Laravel 10, 11, or 12
- PHP 8.3+ for Laravel 13
- `ext-gd` or `ext-imagick` for image optimization
- Livewire 3 (optional — only required for the bundled dashboard components)

## Installation

```bash
composer require artisanpack-ui/performance
php artisan perf:install
```

The install command publishes config, runs migrations, validates environment requirements, and prints the dashboard gate stub plus next steps.

### Non-interactive install (CI/CD)

```bash
php artisan perf:install --no-interaction --force
```

### React / Vue projects

Front-end companion is published as `@artisanpack-ui/performance` with subpath exports:

```ts
// React
import { PerformanceDashboard, MetricsChart } from '@artisanpack-ui/performance/react'

// Vue
import MetricsChart from '@artisanpack-ui/performance/vue/MetricsChart.vue'

// Vanilla helpers
import { reportWebVitals } from '@artisanpack-ui/performance/web-vitals'
import { applySpeculativeRules } from '@artisanpack-ui/performance/speculative-rules'
```

The React/Vue components hit the same JSON API the Livewire dashboard does, so back-end behavior is identical regardless of the chosen front-end.

## Quick start

```blade
{{-- Drop the RUM collector into your layout --}}
@perfMonitor

{{-- Emit Speculation Rules for a prefetch/prerender ruleset --}}
@speculativeRules('main-nav')

{{-- Render the admin dashboard behind your gate --}}
<livewire:performance-dashboard />
```

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Convert an image to WebP + AVIF and generate responsive sizes
Performance::optimizeImage($path, [
    'sizes'   => [320, 640, 1024],
    'formats' => ['webp', 'avif'],
]);

// Cache a fragment for 10 minutes, tagged for invalidation
$html = Performance::fragmentRemember('product.card.'.$product->id, 600, function () use ($product) {
    return view('products.card', ['product' => $product])->render();
}, tags: ['product:'.$product->id]);

// Register a script with a load strategy
Performance::script(asset('js/analytics.js'))
    ->name('analytics')
    ->defer()
    ->loadOn('interaction');
```

## Documentation

- [docs/home.md](docs/home.md) — documentation home
- [docs/guides.md](docs/guides.md) — feature walkthroughs
- [docs/api.md](docs/api.md) — programmatic API reference

### AI features

The performance package registers two AI features via `artisanpack-ui/ai`. Both are gated by the `artisanpack.ai.features.<key>.enabled` toggle and will no-op when the toggle is off.

| Feature key | Purpose | Livewire | React | Vue |
|-------------|---------|----------|-------|-----|
| `performance.query_insight` | Explain why a slow query is slow, suggest indexes and rewrites (never runs DDL). | `perf-ai-query-insight-panel` | `QueryInsightPanel` | `QueryInsightPanel` |
| `performance.optimization_suggestion` | Look at aggregate metrics over a date range and recommend where to focus optimization work. | `perf-ai-optimization-suggestion-panel` | `OptimizationSuggestionPanel` | `OptimizationSuggestionPanel` |

The React and Vue components live inside this package (not in `@artisanpack-ui/react` / `@artisanpack-ui/vue`) so both frameworks are supported without adding a coupling to the shared UI packages.

```blade
{{-- Livewire --}}
<livewire:perf-ai-query-insight-panel />
<livewire:perf-ai-optimization-suggestion-panel window-days="14" />
```

```tsx
// React
import { QueryInsightPanel, OptimizationSuggestionPanel } from '@artisanpack-ui/performance/react';

<QueryInsightPanel clientOptions={ { baseUrl: '/api/performance' } } />
<OptimizationSuggestionPanel
    range={ { from: '2026-07-01', to: '2026-07-07' } }
    metrics={ metrics }
    clientOptions={ { baseUrl: '/api/performance' } }
/>
```

```vue
<!-- Vue -->
<script setup lang="ts">
import { QueryInsightPanel, OptimizationSuggestionPanel } from '@artisanpack-ui/performance/vue';
</script>

<template>
    <QueryInsightPanel :client-options="{ baseUrl: '/api/performance' }" />
    <OptimizationSuggestionPanel
        :range="{ from: '2026-07-01', to: '2026-07-07' }"
        :metrics="metrics"
        :client-options="{ baseUrl: '/api/performance' }"
    />
</template>
```

The React and Vue triggers POST to `POST /api/performance/ai/query-insight` and `POST /api/performance/ai/optimization-suggestion`. Responses are shaped as `{ data: <agent output>, feature_key: string }`. Disabled features return `409`; missing credentials return `412`; validation errors return `422`.

### Feature guides

- [Image optimization](docs/guides/image-optimization.md)
- [JavaScript & CSS strategies](docs/guides/javascript-css-strategies.md)
- [Caching](docs/guides/caching.md)
- [Database optimization](docs/guides/database-optimization.md)
- [Monitoring & dashboard](docs/guides/monitoring-dashboard.md)
- [Speculative loading](docs/guides/speculative-loading.md)
- [Media library integration](docs/guides/media-library-integration.md)

### API reference

- [Services](docs/api/services.md)
- [Models](docs/api/models.md)
- [Events](docs/api/events.md)
- [Helpers](docs/api/helpers.md)
- [Blade directives](docs/api/blade-directives.md)
- [Middleware](docs/api/middleware.md)
- [Livewire components](docs/api/livewire.md)
- [Traits](docs/api/traits.md)
- [Artisan commands](docs/api/artisan.md)
- [JavaScript API](docs/api/javascript.md)

### Benchmarks

- [Overview](docs/benchmarks.md)
- [Client-side methodology](docs/benchmarks/client-side.md)
- [Server-side methodology](docs/benchmarks/server-side.md)

### Customization

- [Overview](docs/customization.md)
- [View customization](docs/customization/views.md)

### Security

- [Overview](docs/security.md)
- [Audit — v1.0.0](docs/security/audit-1.0.0.md)

### Development

- [Overview](docs/development.md)
- [Code style](docs/development/code-style.md)

## Artisan commands

| Command | Purpose |
|---|---|
| `perf:install` | Publish config, migrate, validate environment, print gate stub |
| `perf:warm-cache` | Pre-populate page/fragment cache for configured URLs |
| `perf:purge-cache` | Flush the page and/or fragment cache (optional tag filters) |
| `perf:aggregate-metrics` | Roll raw RUM samples up into hourly/daily buckets |
| `perf:critical-css` | Extract critical CSS for configured routes |
| `perf:generate-webp` | Batch-convert existing images to WebP/AVIF |
| `perf:suggest-indexes` | Analyze slow queries and suggest missing indexes |

Schedule the recurring commands in `bootstrap/app.php` (Laravel 11+):

```php
->withSchedule(function (Schedule $schedule): void {
    $schedule->command('perf:aggregate-metrics')->hourly();
    $schedule->command('perf:warm-cache')->everyThirtyMinutes();
    $schedule->command('perf:suggest-indexes')->daily();
})
```

## Testing

```bash
composer test                                # full suite
./vendor/bin/pest tests/Feature/Cache        # one suite
./vendor/bin/pest --filter=speculative       # one test
composer bench                               # benchmark suite (opt-in)
```

The package ships with 800+ Pest tests covering every service, model, Livewire component, Artisan command, middleware, event, and listener.

## Configuration

Every feature toggle in `config/artisanpack/performance.php` has a matching `PERF_*` environment variable, so you can flip features per environment without code changes:

```php
'features' => [
    'image_optimization'  => env('PERF_IMAGE_OPTIMIZATION', false),
    'script_optimization' => env('PERF_SCRIPT_OPTIMIZATION', false),
    'critical_css'        => env('PERF_CRITICAL_CSS', false),
    'resource_hints'      => env('PERF_RESOURCE_HINTS', false),
    'speculative_loading' => env('PERF_SPECULATIVE_LOADING', false),
    'html_minification'   => env('PERF_HTML_MINIFICATION', false),
    'early_hints'         => env('PERF_EARLY_HINTS', false),
    'page_cache'          => env('PERF_PAGE_CACHE', false),
    'fragment_cache'      => env('PERF_FRAGMENT_CACHE', false),
    'cache_warming'       => env('PERF_CACHE_WARMING', false),
    'query_optimization'  => env('PERF_QUERY_OPTIMIZATION', false),
    'monitoring'          => env('PERF_MONITORING', false),
    'dashboard'           => env('PERF_DASHBOARD', false),
],
```

The published configuration file is heavily commented — every section documents what it does and the trade-offs of each option.

## Upgrading

See [CHANGELOG.md](CHANGELOG.md) for release history. Version-to-version upgrade notes will land in `UPGRADING.md` once there is a v1.x → v2.x transition to document.

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.

## License

MIT — see [LICENSE](LICENSE).
