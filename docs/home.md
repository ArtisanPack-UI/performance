---
title: Performance Documentation
---

# Performance Documentation

Welcome to the ArtisanPack UI Performance documentation! This Laravel package is a comprehensive performance-optimization toolkit — image optimization, JavaScript and CSS strategies, speculative loading, page and fragment caching, database analysis, and a full real-user monitoring dashboard — with Livewire, React, **and** Vue front-ends.

## Overview

The Performance package helps Laravel applications ship faster pages and better Core Web Vitals. It provides:

- **Image Optimization**: WebP/AVIF conversion, responsive srcsets, dominant-color LQIP placeholders, lazy loading
- **JavaScript & CSS Strategies**: `defer`, `async`, `module`, `on-interaction`, `on-visible`, `on-idle` script loading plus critical CSS extraction
- **Speculative Loading**: Speculation Rules API integration for prefetch and prerender with eagerness controls
- **Resource Hints & Early Hints**: preconnect, dns-prefetch, preload, and HTTP 103 Early Hints
- **Caching**: Full-page cache, fragment cache with tags, cache warming, wildcard invalidation
- **Database Optimization**: N+1 detection, slow-query logging, query-cache trait, index suggestions
- **Monitoring & Dashboard**: RUM Web Vitals collector, aggregation pipeline, Livewire dashboard, recommendations engine
- **Media Library Integration**: automatic optimization for uploads through `artisanpack-ui/media-library`
- **Artisan Tooling**: `perf:install`, `perf:generate-webp`, `perf:critical-css`, `perf:warm-cache`, `perf:purge-cache`, `perf:suggest-indexes`, `perf:aggregate-metrics`
- **Event-Driven**: Every cache mutation, slow query, N+1, and threshold breach emits an event you can listen for
- **JavaScript API**: `@artisanpack-ui/performance` main entry plus `/web-vitals`, `/metrics-chart`, `/speculative-rules`, `/react`, `/vue` subpath exports

All features are **opt-in**. Nothing runs unless you turn it on.

---

## Getting Started

- [Installation](../README.md#installation) — Setup and `perf:install` walkthrough
- [Configuration](../README.md#configuration) — Every feature toggle and its `PERF_*` env var
- [Changelog](../CHANGELOG.md) — Release history and version-to-version notes

---

## Guides

End-to-end walkthroughs for each major feature of the package.

- [[guides]] — Overview of the guides section
- [[guides/image-optimization]] — WebP/AVIF, responsive srcsets, dominant color, `HasOptimizedImages`
- [[guides/javascript-css-strategies]] — Script strategies and critical CSS extraction
- [[guides/caching]] — Page cache, fragment cache, tag-based invalidation, warming
- [[guides/database-optimization]] — N+1 detection, slow queries, query cache, index suggestions
- [[guides/monitoring-dashboard]] — RUM collector, aggregation, dashboard, recommendations
- [[guides/speculative-loading]] — Speculation Rules, prefetch, prerender, resource hints
- [[guides/media-library-integration]] — Automatic optimization for `artisanpack-ui/media-library` uploads

---

## API Reference

- [[api]] — Overview of the API reference
- [[api/services]] — `PerformanceService` plus supporting services (images, cache, database, monitoring, speculative, scripts)
- [[api/models]] — `PerformanceMetric`, `RawMetric`, `SlowQuery`
- [[api/events]] — Every event fired by the package and what to listen for
- [[api/helpers]] — Global `perf*` helper functions
- [[api/blade-directives]] — `@speculativeRules`, `@resourceHints`, `@perfMonitor`, `@cache`, scripts, hints
- [[api/middleware]] — `PageCache`, `MinifyHtml`, `EarlyHints`, `InjectResourceHints`, `DetectSlowQueries`
- [[api/livewire]] — Dashboard, metrics chart, cache manager, query analyzer, recommendations panel
- [[api/traits]] — `HasOptimizedImages`, `HasOptimizedMedia`, `CachesQueries`
- [[api/artisan]] — Every Artisan command shipped by the package
- [[api/javascript]] — `@artisanpack-ui/performance` subpath exports, `useWebVitals`, React / Vue components

---

## Benchmarks

- [[benchmarks]] — Overview and Web Vitals targets
- [[benchmarks/client-side]] — Lighthouse recipe and page-type matrix
- [[benchmarks/server-side]] — Pest benchmark suite methodology

---

## Customization

- [[customization]] — Overview of the customization surface
- [[customization/views]] — Blade templates, prop reference, CSS custom properties, JS bundle overrides

---

## Security

- [[security]] — Security policy and reporting a vulnerability
- [[security/audit-1.0.0]] — Pre-release security audit for v1.0.0

---

## Development

- [[development]] — Contributor overview
- [[development/code-style]] — Package code standard and the tooling that enforces it

---

## Module Quick Reference

| Area | Purpose | Status |
|------|---------|--------|
| Image Optimization | WebP/AVIF, responsive sizes, dominant color | Stable |
| JavaScript / CSS | Load strategies, critical CSS | Stable |
| Speculative Loading | Speculation Rules API, prefetch, prerender | Stable |
| Resource Hints | preconnect / dns-prefetch / preload / 103 Early Hints | Stable |
| HTML Minification | Response-body minifier middleware | Stable |
| Page Cache | Full-page cache with wildcard invalidation | Stable |
| Fragment Cache | Tag-scoped fragment cache | Stable |
| Cache Warming | Background warmer for URLs and sitemaps | Stable |
| Database | N+1 detection, slow queries, query cache | Stable |
| Monitoring | RUM Web Vitals collector + aggregation | Stable |
| Dashboard | Livewire admin surface | Stable |
| Media Library | Auto-optimize uploads from `artisanpack-ui/media-library` | Stable |

---

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `perf:install` | Publish config, JS, CSS, views, run migrations, validate environment |
| `perf:generate-webp` | Bulk-convert existing JPEG/PNG images to WebP |
| `perf:critical-css` | Extract critical CSS for the given routes |
| `perf:warm-cache` | Warm the page cache from routes, URLs, or a sitemap |
| `perf:purge-cache` | Purge page, fragment, or all caches by pattern or tag |
| `perf:suggest-indexes` | Analyze recent queries and suggest indexes (optionally as a migration) |
| `perf:aggregate-metrics` | Aggregate raw metrics into daily rollups (with optional backfill) |

---

## Configuration

The package is configured through `config/artisanpack/performance.php`:

```php
// config/artisanpack/performance.php
return [
    'features' => [
        'image_optimization'  => env( 'PERF_IMAGE_OPTIMIZATION', false ),
        'script_optimization' => env( 'PERF_SCRIPT_OPTIMIZATION', false ),
        'critical_css'        => env( 'PERF_CRITICAL_CSS', false ),
        'resource_hints'      => env( 'PERF_RESOURCE_HINTS', false ),
        'speculative_loading' => env( 'PERF_SPECULATIVE_LOADING', false ),
        'html_minification'   => env( 'PERF_HTML_MINIFICATION', false ),
        'page_cache'          => env( 'PERF_PAGE_CACHE', false ),
        'fragment_cache'      => env( 'PERF_FRAGMENT_CACHE', false ),
        'query_optimization'  => env( 'PERF_QUERY_OPTIMIZATION', false ),
        'monitoring'          => env( 'PERF_MONITORING', false ),
        'dashboard'           => env( 'PERF_DASHBOARD', false ),
    ],
    // …
];
```

Every feature toggle has a matching `PERF_*` environment variable so you can flip features per-environment without code changes. See the [configuration section of the README](../README.md#configuration) for every option.

---

## Support

For issues, feature requests, and contributions:

- **GitHub**: https://github.com/ArtisanPack-UI/performance
- **Documentation**: https://artisanpack.dev/packages/performance

---

*This documentation covers Performance v1.0.0*
