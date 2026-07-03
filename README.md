# ArtisanPack UI Performance

Comprehensive performance optimization toolkit for Laravel applications. Ship
faster pages, better Core Web Vitals, and a real-user performance dashboard
without leaving Laravel.

- **Image optimization** — WebP/AVIF conversion, responsive sizes, dominant color extraction, lazy loading.
- **JavaScript & CSS strategies** — deferred, conditional, and priority scripts; critical CSS extraction; async loading.
- **Speculative loading** — Speculation Rules API integration for prefetch and prerender.
- **Resource hints & early hints** — preconnect, dns-prefetch, preload, and HTTP 103 Early Hints.
- **HTML minification** — request-time or middleware-based.
- **Caching** — page cache, fragment cache, cache warming, tag-based invalidation.
- **Database optimization** — N+1 detection, slow query logging, query cache, index suggestions.
- **Performance monitoring** — RUM (Real User Monitoring) with Web Vitals collection, aggregation, and a Livewire dashboard.
- **Media library integration** — automatic optimization for uploads made through `artisanpack-ui/media-library`.

All features are **opt-in**. Nothing runs unless you turn it on.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Image Optimization](#image-optimization)
- [JavaScript & CSS Optimization](#javascript--css-optimization)
- [Speculative Loading & Resource Hints](#speculative-loading--resource-hints)
- [Caching](#caching)
- [Database Optimization](#database-optimization)
- [Performance Monitoring & Dashboard](#performance-monitoring--dashboard)
- [Media Library Integration](#media-library-integration)
- [Blade Components](#blade-components)
- [Blade Directives](#blade-directives)
- [Livewire Components](#livewire-components)
- [Artisan Commands](#artisan-commands)
- [Middleware](#middleware)
- [Events](#events)
- [Helper Functions](#helper-functions)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Contributing](#contributing)

---

## Requirements

- PHP 8.2+ for Laravel 10, 11, or 12
- PHP 8.3+ for Laravel 13
- `ext-gd` or `ext-imagick` for image optimization
- Livewire 3 (optional — required only for the bundled dashboard components)

---

## Installation

```bash
composer require artisanpack-ui/performance
```

Publish the configuration file and run migrations. The package migrations ship
inside the package and are loaded automatically — you don't need to publish
them:

```bash
php artisan vendor:publish --tag=artisanpack-performance-config
php artisan migrate
```

Optionally publish the front-end bundle (used by the RUM collector) and view
templates if you want to customize markup or styling:

```bash
php artisan vendor:publish --tag=artisanpack-performance-js
php artisan vendor:publish --tag=performance-views
php artisan vendor:publish --tag=performance-css
```

---

## Configuration

Configuration lives at `config/artisanpack/performance.php` after publishing.
Every feature toggle has a matching `PERF_*` environment variable so you can
flip features per-environment without code changes.

```php
'features' => [
    'image_optimization'  => env( 'PERF_IMAGE_OPTIMIZATION', false ),
    'lazy_loading'        => env( 'PERF_LAZY_LOADING', false ),
    'script_optimization' => env( 'PERF_SCRIPT_OPTIMIZATION', false ),
    'critical_css'        => env( 'PERF_CRITICAL_CSS', false ),
    'resource_hints'      => env( 'PERF_RESOURCE_HINTS', false ),
    'speculative_loading' => env( 'PERF_SPECULATIVE_LOADING', false ),
    'html_minification'   => env( 'PERF_HTML_MINIFICATION', false ),
    'early_hints'         => env( 'PERF_EARLY_HINTS', false ),
    'page_cache'          => env( 'PERF_PAGE_CACHE', false ),
    'fragment_cache'      => env( 'PERF_FRAGMENT_CACHE', false ),
    'cache_warming'       => env( 'PERF_CACHE_WARMING', false ),
    'query_optimization'  => env( 'PERF_QUERY_OPTIMIZATION', false ),
    'monitoring'          => env( 'PERF_MONITORING', false ),
    'alerts'              => env( 'PERF_ALERTS', false ),
    'dashboard'           => env( 'PERF_DASHBOARD', false ),
],
```

The configuration file is heavily commented — every section documents what it
does and the trade-offs of each option.

---

## Image Optimization

Convert JPEG/PNG uploads to WebP or AVIF, generate responsive sizes, and
extract a dominant color for use as an LQIP placeholder.

```php
use ArtisanPackUI\Performance\Facades\Performance;

$result = Performance::optimizeImage( $path, [
    'sizes'   => [320, 640, 1024],
    'formats' => ['webp', 'avif'],
    'quality' => 80,
] );

// Convert directly.
$webp = Performance::convertToWebP( $path, 80 );
$avif = Performance::convertToAvif( $path, 70 );

// LQIP dominant color.
$color = Performance::getDominantColor( $path );

// Responsive srcset.
$srcset = Performance::getResponsiveSrcset( $path, [320, 640, 1024] );
```

Optimization can also run in the background via `OptimizeImageJob`:

```php
use ArtisanPackUI\Performance\Jobs\OptimizeImageJob;

OptimizeImageJob::dispatch( $path, [
    'sizes'   => [320, 640, 1024],
    'formats' => ['webp', 'avif'],
] );
```

### Model integration

Attach the `HasOptimizedImages` trait to any Eloquent model that owns image
attributes:

```php
use ArtisanPackUI\Performance\Traits\HasOptimizedImages;

class Product extends Model
{
    use HasOptimizedImages;

    protected function optimizableImages(): array
    {
        return [
            'hero_image' => [
                'sizes'                  => [320, 640, 1024],
                'formats'                => ['webp', 'avif'],
                'quality'                => 80,
                'extract_dominant_color' => true,
                'auto_optimize'          => true,
            ],
        ];
    }
}

$product->getOptimizedImageUrl( 'hero_image', 'webp', 640 );
$product->getImageSrcset( 'hero_image', 'webp' );
$product->getImageDominantColor( 'hero_image' );
```

Setting `auto_optimize` to `true` dispatches `OptimizeImageJob` whenever the
attribute changes on save.

---

## JavaScript & CSS Optimization

Register scripts with a load strategy — the default is `defer`:

```php
use ArtisanPackUI\Performance\Facades\Performance;

Performance::script( '/js/analytics.js' )->defer();
Performance::script( '/js/chat.js' )->onInteraction();
Performance::script( '/js/lazy-carousel.js' )->onVisible();
Performance::script( '/js/telemetry.js' )->onIdle();

echo Performance::renderScripts();
```

Extract and inline critical CSS to eliminate render-blocking stylesheet requests
for above-the-fold content:

```bash
php artisan perf:critical-css --url=https://example.com/
```

Or invoke the extractor at runtime:

```php
$critical = Performance::criticalCss()->extract( $html );
```

---

## Speculative Loading & Resource Hints

Register URLs to prefetch or prerender on the current request:

```php
Performance::prefetch( ['/products', '/about'], 'moderate' );
Performance::prerender( '/checkout', 'conservative' );
```

Emit the resulting `<script type="speculationrules">` block and resource hints
in your layout:

```blade
@speculativeRules
@resourceHints
```

---

## Caching

Full-page and fragment caching with tag-based invalidation.

```php
$html = Performance::remember( 'homepage', 3600, fn () => renderHomepage() );

$fragment = Performance::fragmentRemember(
    'sidebar-featured',
    900,
    fn () => renderSidebar(),
    tags: ['sidebar', 'featured'],
);

Performance::invalidateFragmentsByTag( 'featured' );
Performance::invalidatePageCache( '/products/*' );
Performance::flushPageCache();
```

Warm the cache in the background:

```bash
php artisan perf:warm-cache
```

Or via the API:

```php
Performance::warmPageCache( ['/', '/pricing', '/docs'] );
```

Purge caches on demand:

```bash
php artisan perf:purge-cache --pattern="/products/*"
```

---

## Database Optimization

### N+1 detection

Enable the detector to log or notify when queries repeat above a threshold:

```php
// config/artisanpack/performance.php
'database' => [
    'n1_detection' => [
        'enabled'     => true,
        'threshold'   => 5,
        'log_channel' => 'performance',
        'notify'      => false,
    ],
],
```

### Slow query logging

Log queries above a millisecond threshold to the log channel or a database
table:

```php
'slow_query_logging' => [
    'enabled'           => true,
    'threshold_ms'      => 100,
    'store_in_database' => true,
    'retention_days'    => 30,
],
```

### Query cache trait

```php
use ArtisanPackUI\Performance\Traits\CachesQueries;

class Report extends Model
{
    use CachesQueries;

    // Model queries are cached automatically according to the trait's rules.
}
```

### Index suggestions

Analyze recent queries and get suggested indexes:

```bash
php artisan perf:suggest-indexes
```

---

## Performance Monitoring & Dashboard

The RUM collector emits Web Vitals from the browser to a configurable endpoint,
aggregates them on a schedule, and surfaces the results in a Livewire dashboard.

```php
'monitoring' => [
    'enabled'              => true,
    'collect_web_vitals'   => true,
    'endpoint'             => '/api/performance/metrics',
    'sample_rate'          => 100,
    'aggregation_interval' => 'hourly',
    'retention_days'       => 90,
],
```

Enable the dashboard and mount the Livewire component in a route your
application authorizes:

```blade
<livewire:perf-performance-dashboard />
```

Aggregate metrics on a schedule:

```bash
php artisan perf:aggregate-metrics --interval=hourly
```

Record a custom metric from application code:

```php
Performance::recordMetric( 'checkout.duration', 4200.0, ['step' => 'shipping'] );
```

---

## Media Library Integration

When [`artisanpack-ui/media-library`](https://github.com/ArtisanPack-UI/media-library)
is installed, the Performance package automatically optimizes every uploaded
image. The integration is detected at boot (via `MediaLibraryDetector`) and
can be forced on or off explicitly:

```php
'media_library_integration' => [
    'enabled'                    => true,   // null = auto-detect
    'optimize_on_upload'         => true,
    'generate_formats_on_upload' => true,
],
```

Uploaded rows are enriched with optimization metadata via the
`HasOptimizedMedia` trait:

```php
use ArtisanPackUI\MediaLibrary\Models\Media;
use ArtisanPackUI\Performance\Traits\HasOptimizedMedia;

class Media extends Model
{
    use HasOptimizedMedia;
}

$media = Media::find( 1 );

$media->isOptimized();                         // bool
$media->getOptimizationStatus();               // 'pending' | 'processing' | 'completed' | 'failed'
$media->getDominantColor();                    // '#3b82f6' or null
$media->getOptimizedUrl( 'webp' );             // format-only lookup
$media->getOptimizedUrl( 'webp', 640 );        // format + width lookup
$media->getSrcset( 'webp' );                   // '<url> 320w, <url> 640w, ...'
```

Optimization runs asynchronously in `OptimizeMediaJob`, which writes back
`dominant_color`, `optimization_status`, `optimized_at`, `optimized_formats`,
and `optimized_sizes` to the row on completion. The migration that adds
these columns is a no-op when the `media` table is absent, so applications
without media-library never fail their migrate step.

---

## Blade Components

All components are registered under the `perf` prefix.

| Component | Purpose |
|-----------|---------|
| `<x-perf-lazy-image>` | Lazy-loaded image with dominant-color placeholder. |
| `<x-perf-responsive-image>` | Responsive image with automatic `srcset`. |
| `<x-perf-critical-css>` | Inline critical CSS block. |
| `<x-perf-resource-hints>` | Emit preconnect/dns-prefetch/preload tags. |
| `<x-perf-script>` | Script tag with a load strategy (`defer`, `async`, `priority`). |
| `<x-perf-conditional-script>` | Script that loads on interaction, visibility, or idle. |
| `<x-perf-speculative-rules>` | Emit the Speculation Rules `<script>` block. |
| `<x-perf-prefetch>` | Register a URL for prefetching. |
| `<x-perf-embed>` | Optimized `<iframe>` embed (YouTube/Vimeo lite). |

Example:

```blade
<x-perf-responsive-image
    src="/images/hero.jpg"
    :sizes="[320, 640, 1024]"
    formats="webp,avif"
    alt="Hero"
/>

<x-perf-lazy-image
    src="/images/product.jpg"
    dominant-color="#3b82f6"
    alt="Product"
/>
```

---

## Blade Directives

| Directive | Purpose |
|-----------|---------|
| `@speculativeRules` | Emit the Speculation Rules `<script>` block. |
| `@resourceHints` | Emit registered resource hints. |
| `@perfScript($src, $strategy)` | Register and emit a strategy-loaded script. |
| `@perfMonitor` | Inject the RUM monitor bootstrap script. |
| `@perfMetricsChart($metric)` | Emit a metrics chart for a Web Vitals metric. |

---

## Livewire Components

| Component | Description |
|-----------|-------------|
| `perf-performance-dashboard` | Top-level dashboard with tabs. |
| `perf-metrics-chart` | Time-series chart for a single metric. |
| `perf-cache-manager` | Browse and invalidate cached pages/fragments. |
| `perf-query-analyzer` | Inspect slow queries and N+1 offenders. |
| `perf-recommendations-panel` | Human-readable recommendations from collected metrics. |

Livewire itself is a suggested (not required) dependency — the components only
register when Livewire is present.

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `perf:generate-webp` | Bulk-convert existing images to WebP. |
| `perf:critical-css` | Extract critical CSS for a URL. |
| `perf:warm-cache` | Warm the page cache. |
| `perf:purge-cache` | Purge caches by pattern. |
| `perf:suggest-indexes` | Analyze recent queries and suggest indexes. |
| `perf:aggregate-metrics` | Aggregate raw metrics into hourly/daily buckets. |

Every command supports `--help` for full option details.

---

## Middleware

Two named middleware aliases ship with the package:

| Alias | Class | Purpose |
|-------|-------|---------|
| `perf.minify` | `MinifyHtml` | Minify the response body. |
| `perf.early-hints` | `EarlyHints` | Emit HTTP 103 Early Hints before the response body. |

Apply them to route groups:

```php
Route::middleware( [ 'web', 'perf.minify', 'perf.early-hints' ] )->group( function () {
    // ...
} );
```

Additional middleware — `DetectSlowQueries`, `InjectResourceHints`,
`PageCache` — can be applied via the full FQCN.

---

## Events

The package dispatches these events for applications to listen for:

| Event | Payload |
|-------|---------|
| `ImageOptimized` | `path`, `formats`, `sizes` |
| `CacheWarmed` | `url`, `duration_ms` |
| `CachePurged` | `pattern`, `count` |
| `SlowQueryDetected` | `query`, `time_ms`, `bindings` |
| `N1QueryDetected` | `query_normalized`, `count`, `route` |
| `PerformanceThresholdExceeded` | `metric`, `value`, `threshold` |

Registered internally:

| Listener | Trigger |
|----------|---------|
| `OptimizeUploadedMedia` | Media-library Media model `created` event; also `MediaUploaded` if the future event class exists. |

---

## Helper Functions

Global helpers that mirror the facade. Use them in templates and lightweight
application code where dependency injection is impractical.

| Function | Description |
|----------|-------------|
| `performance()` | Resolve the PerformanceService. |
| `perfFeatureEnabled(string $feature)` | Check whether a feature toggle is on. |
| `perfOptimizeImage(string $path, array $options = [])` | Run the optimization pipeline. |
| `perfConvertToWebP(string $path, int $quality = 80)` | Convert to WebP. |
| `perfConvertToAvif(string $path, int $quality = 70)` | Convert to AVIF. |
| `perfGetDominantColor(string $path)` | Extract dominant color hex. |
| `perfGetResponsiveSrcset(string $path, array $sizes)` | Build a `srcset` string. |
| `perfRemember(string $key, int $ttl, Closure $callback)` | Cache remember. |
| `perfRememberForever(string $key, Closure $callback)` | Cache remember forever. |
| `perfInvalidateCache(string $key)` | Invalidate a cache key. |
| `perfFlushCache()` | Flush the whole cache store. |
| `perfFragmentRemember(string $key, int $ttl, Closure $callback, array $tags = [])` | Fragment cache. |
| `perfInvalidatePageCache(string $pattern)` | Invalidate page cache entries by pattern. |
| `perfFlushPageCache()` | Flush all page cache entries. |
| `perfInvalidateFragmentsByTag(string $tag)` | Invalidate fragments by tag. |
| `perfWarmPageCache(array $urls)` | Warm the page cache. |
| `perfRecordMetric(string $name, float $value, array $context = [])` | Record a custom RUM metric. |
| `perfGetRecommendations()` | Get performance recommendations. |

---

## Troubleshooting

### GD/Imagick not installed

Image optimization requires either `ext-gd` (default) or `ext-imagick`. Choose
the driver in config:

```php
'images' => [ 'driver' => 'imagick' ],
```

Check the driver's format support at runtime:

```php
Performance::images()->supportsFormat( 'avif' );
```

### Media library integration seems dormant

Check the detector's status:

```php
app( \ArtisanPackUI\Performance\Services\MediaLibraryDetector::class )->status();
// [
//     'installed' => true,
//     'enabled'   => true,
//     'source'    => 'auto',
// ]
```

If `installed` is false, the media-library package isn't autoloadable. If
`enabled` is false, the `media_library_integration.enabled` config value is
`false` — set it to `null` for auto-detect or `true` to force on.

### Optimized images not appearing on the Media model

Check that the migration ran (`php artisan migrate`) and that the target
`Media` model uses the `HasOptimizedMedia` trait. Rows uploaded before the
migration ran will keep their `optimization_status = pending` until they
are re-uploaded or manually re-queued via `OptimizeMediaJob::dispatch($media)`.

### Dashboard shows no data

The RUM collector fires from the client-side bundle. Publish it and include
it in your layout:

```bash
php artisan vendor:publish --tag=artisanpack-performance-js
```

```blade
@perfMonitor
```

Metrics are aggregated by `perf:aggregate-metrics` — schedule it every hour
(or match `aggregation_interval`) via Laravel's task scheduler.

### Page cache serving stale content

Reduce the TTL, exclude the route under `page_cache.exclude_routes`, or
invalidate the pattern:

```php
Performance::invalidatePageCache( '/products/*' );
```

### Slow queries not being logged

The logger is opt-in. Confirm:

```php
config( 'artisanpack.performance.database.slow_query_logging.enabled' );      // true
config( 'artisanpack.performance.database.slow_query_logging.threshold_ms' ); // 100
```

And make sure the `performance` log channel is defined in `config/logging.php`
if you configured `log_channel` to `performance`.

---

## FAQ

**Do I have to use every feature?**  
No — every feature is opt-in and toggled independently. Enable only what you need.

**Does the package replace a CDN?**  
No. It complements a CDN. Serve WebP/AVIF variants from your CDN and let the
package handle generation.

**Can I use the RUM collector without the dashboard?**  
Yes. Set `monitoring.enabled = true` and `dashboard.enabled = false`. Query
`performance_metrics` directly, or read from the `MetricsAggregator` service.

**Does the media-library integration work with S3?**  
The current implementation reads the source file from a disk exposing a local
`path()`. Remote disks (S3, GCS) are supported for storage but the
optimization job needs a local copy — download to a temp file and dispatch
`OptimizeImageJob` against that path in the interim. Native remote-disk
support is planned.

**Will enabling all features slow down my app?**  
Only if you enable expensive features (monitoring, N+1 detection) with an
aggressive `sample_rate` or `threshold`. Start conservative and increase as
you validate impact.

**How do I disable the package temporarily?**  
Set every `PERF_*` env var to `false` and clear config cache. The service
provider still boots but no listeners, middleware, or observers activate.

---

## Contributing

As an open source project, this package is open to contributions from anyone.
Please [read through the contributing guidelines](CONTRIBUTING.md) to learn
more about how you can contribute to this project.
