---
title: Guides
---

# Guides

End-to-end walkthroughs for the major features of the Performance package. Each guide assumes you have already run `perf:install` and have the package wired into a Laravel application.

## Available Guides

- [[guides/image-optimization]] — Convert to WebP/AVIF, generate responsive srcsets, extract dominant color, wire the `HasOptimizedImages` trait
- [[guides/javascript-css-strategies]] — Register scripts with `defer` / `async` / `on-interaction` / `on-visible` / `on-idle` strategies and inline critical CSS
- [[guides/caching]] — Enable the page cache middleware, use fragment caching with tags, warm the cache, and invalidate by pattern
- [[guides/database-optimization]] — Enable N+1 detection, slow-query logging, the `CachesQueries` trait, and generate index suggestions
- [[guides/monitoring-dashboard]] — Mount the RUM collector, aggregate metrics, mount the Livewire dashboard, act on recommendations
- [[guides/speculative-loading]] — Register prefetch / prerender URLs, emit the Speculation Rules block, add resource hints and Early Hints
- [[guides/media-library-integration]] — Auto-optimize uploads made through `artisanpack-ui/media-library` and read the optimization metadata back

## When to Read What

| If you are… | Start with |
|---|---|
| Shrinking bytes on the wire for images | [[guides/image-optimization]] |
| Cutting render-blocking JS/CSS from your critical path | [[guides/javascript-css-strategies]] |
| Reducing TTFB on hot pages | [[guides/caching]] |
| Chasing an N+1 or slow-query regression | [[guides/database-optimization]] |
| Instrumenting Core Web Vitals for real users | [[guides/monitoring-dashboard]] |
| Making next-page navigation instant | [[guides/speculative-loading]] |
| Integrating with the media library | [[guides/media-library-integration]] |

For the underlying classes, methods, events, and helpers used by each guide, see the [[api]] reference.
