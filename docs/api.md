---
title: API Reference
---

# API Reference

Programmatic surface of the Performance package: services, models, events, helpers, Blade directives, middleware, Livewire components, Eloquent traits, Artisan commands, and the JavaScript API. For end-to-end walkthroughs that use these primitives, see the [[guides]].

## Reference Pages

- [[api/services]] — `PerformanceService` (the facade target) plus the image, cache, database, monitoring, speculative, script, CSS, and output services it composes
- [[api/models]] — `PerformanceMetric`, `RawMetric`, `SlowQuery`
- [[api/events]] — Events emitted on cache mutations, image optimization, database analysis, and threshold breaches
- [[api/helpers]] — Global `perf*` helper functions registered via `src/helpers.php`
- [[api/blade-directives]] — `@speculativeRules`, `@resourceHints`, `@cache`, `@perfMonitor`, `@perfMetricsChartAssets`, script and hint directives
- [[api/middleware]] — `PageCache`, `MinifyHtml`, `EarlyHints`, `InjectResourceHints`, `DetectSlowQueries`
- [[api/livewire]] — Dashboard, metrics chart, cache manager, query analyzer, recommendations panel
- [[api/traits]] — `HasOptimizedImages`, `HasOptimizedMedia`, `CachesQueries`
- [[api/artisan]] — Every command shipped by the package
- [[api/javascript]] — `@artisanpack-ui/performance` main entry plus `/web-vitals`, `/metrics-chart`, `/speculative-rules`, `/react`, `/vue` subpath exports
- [[api/ai]] — `PerformanceInsightAgent`, `OptimizationSuggestionAgent`, `AiAgentApiController`, and the `performance.ai.use` Gate

## By Concern

| If you need to… | See |
|---|---|
| Optimize an image from PHP | [[api/services]] (`PerformanceService::optimizeImage`) |
| Register a script with a load strategy | [[api/services]] (`PerformanceService::script`) |
| Cache a fragment of view output | [[api/services]] (`PerformanceService::fragmentRemember`) |
| Warm or invalidate the page cache | [[api/services]] (`PerformanceService::warmPageCache`, `invalidatePageCache`) |
| Prefetch or prerender a URL | [[api/services]] (`PerformanceService::prefetch`, `prerender`) |
| Record a custom Web Vital | [[api/services]] (`PerformanceService::recordMetric`) or [[api/helpers]] (`perfRecordMetric`) |
| React to slow queries, N+1s, or threshold breaches | [[api/events]] |
| Serve responses from cache with no controller work | [[api/middleware]] (`PageCache`) |
| Attach optimization metadata to a model | [[api/traits]] (`HasOptimizedImages`, `HasOptimizedMedia`) |
| Emit critical CSS or resource hints in Blade | [[api/blade-directives]] |
| Build a React or Vue dashboard surface | [[api/javascript]] |
| Explain a slow query or triage aggregate metrics with AI | [[api/ai]] |

## Conventions

- All services are container singletons. Resolve them via the `Performance` facade (`Performance::images()`, `Performance::pageCache()`, etc.) or type-hint them in your own classes.
- All models live under `ArtisanPackUI\Performance\Models` and mirror the tables published by the package migrations.
- All events live under `ArtisanPackUI\Performance\Events` and are dispatched through Laravel's standard event system with public promoted properties as the payload.
- All global helpers are prefixed `perf` (e.g. `perfOptimizeImage`, `perfFragmentRemember`) to avoid collisions with other packages and are `function_exists`-guarded so applications can override them.
- All Artisan commands are namespaced under `perf:` and support `--help` for full option details.
