# ArtisanPack UI Performance Package - Implementation Plan

## Overview

The `artisanpack-ui/performance` package provides comprehensive performance optimization tools for Laravel applications, including image optimization, JavaScript/CSS loading strategies, caching, database optimization, and real-time performance monitoring. Inspired by WordPress Performance Lab and modern web performance best practices.

**Version**: 1.0.0
**Status**: Planning
**Last Updated**: January 2026

---

## Table of Contents

1. [Goals & Objectives](#goals--objectives)
2. [Feature Overview](#feature-overview)
3. [Architecture Overview](#architecture-overview)
4. [Image Optimization](#image-optimization)
5. [JavaScript & CSS Optimization](#javascript--css-optimization)
6. [Speculative Loading](#speculative-loading)
7. [Server-Side Optimizations](#server-side-optimizations)
8. [Database Optimization](#database-optimization)
9. [Caching System](#caching-system)
10. [Performance Monitoring](#performance-monitoring)
11. [Database Schema](#database-schema)
12. [Configuration](#configuration)
13. [Livewire Components](#livewire-components)
14. [Service Classes](#service-classes)
15. [Artisan Commands](#artisan-commands)
16. [Middleware](#middleware)
17. [Blade Directives & Components](#blade-directives--components)
18. [View Customization](#view-customization)
19. [Integration Points](#integration-points)
20. [Testing Strategy](#testing-strategy)
21. [Implementation Phases](#implementation-phases)

---

## Goals & Objectives

### Primary Goals

1. **Improve Core Web Vitals**: Target excellent LCP, FID/INP, and CLS scores
2. **Reduce Page Load Time**: Minimize time to first byte (TTFB) and total page weight
3. **Developer-Friendly**: Easy integration with minimal configuration required
4. **Opt-In Architecture**: All features disabled by default, developers enable what they need
5. **Measurable Impact**: Provide metrics and reporting to quantify improvements
6. **WordPress Performance Lab Parity**: Implement key features from WP Performance Lab

### Non-Goals

- Replace dedicated CDN services (integrate with them instead)
- Full build tool replacement (complement Vite/webpack, not replace)
- Application-level caching logic (provide helpers, not business logic)

### Target Metrics

| Metric | Target |
|--------|--------|
| Largest Contentful Paint (LCP) | < 2.5s |
| First Input Delay (FID) | < 100ms |
| Interaction to Next Paint (INP) | < 200ms |
| Cumulative Layout Shift (CLS) | < 0.1 |
| Time to First Byte (TTFB) | < 800ms |

---

## Feature Overview

### WordPress Performance Lab Feature Parity

| WP Perf Lab Feature | Package Equivalent | Status |
|---------------------|-------------------|--------|
| WebP Uploads | Image Format Conversion | Planned |
| AVIF Support | Image Format Conversion | Planned |
| Dominant Color Placeholders | Image Placeholders | Planned |
| Fetchpriority | Resource Prioritization | Planned |
| Lazy Loading | Lazy Load Service | Planned |
| Modern Image Output | Responsive Images | Planned |
| Auto-sizes for Lazy Images | Auto Sizes Attribute | Planned |
| Speculative Rules API | Speculative Loading | Planned |
| Embed Optimizer | Embed Optimization | Planned |

### Additional Features

| Feature | Description |
|---------|-------------|
| Critical CSS | Extract and inline above-the-fold CSS |
| Script Loading Strategies | Defer, async, module loading |
| Resource Hints | Preload, prefetch, preconnect, dns-prefetch |
| HTTP/2 Early Hints (103) | Server push alternative |
| HTML Minification | Remove whitespace and comments |
| Page Caching | Full-page cache with invalidation |
| Fragment Caching | Cache expensive view partials |
| Query Optimization | N+1 detection, slow query logging |
| Performance Dashboard | Real-time metrics and recommendations |

---

## Architecture Overview

### Directory Structure

```
src/
├── Commands/
│   ├── InstallCommand.php
│   ├── GenerateWebPCommand.php
│   ├── WarmCacheCommand.php
│   ├── PurgeCacheCommand.php
│   ├── AnalyzePerformanceCommand.php
│   ├── OptimizeImagesCommand.php
│   └── DetectSlowQueriesCommand.php
├── Config/
│   └── performance.php
├── Contracts/
│   ├── ImageOptimizer.php
│   ├── CacheStrategy.php
│   ├── PerformanceMonitor.php
│   └── ResourceHintProvider.php
├── Events/
│   ├── ImageOptimized.php
│   ├── CacheWarmed.php
│   ├── CachePurged.php
│   ├── SlowQueryDetected.php
│   ├── N1QueryDetected.php
│   └── PerformanceThresholdExceeded.php
├── Facades/
│   └── Performance.php
├── Http/
│   ├── Controllers/
│   │   ├── PerformanceDashboardController.php
│   │   └── Api/
│   │       ├── MetricsApiController.php
│   │       └── CacheApiController.php
│   └── Middleware/
│       ├── InjectResourceHints.php
│       ├── MinifyHtml.php
│       ├── PageCache.php
│       ├── EarlyHints.php
│       ├── MeasurePerformance.php
│       └── DetectSlowQueries.php
├── Images/
│   ├── ImageOptimizer.php
│   ├── FormatConverter.php
│   ├── ResponsiveImageGenerator.php
│   ├── DominantColorExtractor.php
│   ├── PlaceholderGenerator.php
│   └── Processors/
│       ├── GdProcessor.php
│       ├── ImagickProcessor.php
│       └── CloudinaryProcessor.php
├── JavaScript/
│   ├── ScriptManager.php
│   ├── DeferStrategy.php
│   ├── AsyncStrategy.php
│   ├── ModuleStrategy.php
│   └── InlineStrategy.php
├── Css/
│   ├── CriticalCssExtractor.php
│   ├── CssMinifier.php
│   └── UnusedCssDetector.php
├── Cache/
│   ├── PageCacheManager.php
│   ├── FragmentCache.php
│   ├── ObjectCacheHelper.php
│   ├── CacheWarmer.php
│   ├── CacheInvalidator.php
│   └── Strategies/
│       ├── FileCacheStrategy.php
│       ├── RedisCacheStrategy.php
│       └── MemcachedCacheStrategy.php
├── Database/
│   ├── QueryAnalyzer.php
│   ├── N1Detector.php
│   ├── SlowQueryLogger.php
│   ├── IndexSuggester.php
│   └── QueryCacheHelper.php
├── Speculative/
│   ├── SpeculativeRulesGenerator.php
│   ├── PrefetchManager.php
│   └── PrerenderManager.php
├── Monitoring/
│   ├── PerformanceCollector.php
│   ├── CoreWebVitalsTracker.php
│   ├── MetricsAggregator.php
│   └── RecommendationEngine.php
├── Livewire/
│   ├── PerformanceDashboard.php
│   ├── MetricsChart.php
│   ├── CacheManager.php
│   ├── ImageOptimizationStatus.php
│   ├── QueryAnalyzer.php
│   └── RecommendationsPanel.php
├── Models/
│   ├── PerformanceMetric.php
│   ├── SlowQuery.php
│   ├── CacheEntry.php
│   └── OptimizedImage.php
├── Notifications/
│   ├── PerformanceAlertNotification.php
│   └── SlowQueryNotification.php
├── Output/
│   ├── HtmlMinifier.php
│   ├── OutputBuffer.php
│   └── ResourceHintInjector.php
├── Services/
│   ├── PerformanceService.php
│   ├── ImageService.php
│   ├── CacheService.php
│   ├── DatabaseService.php
│   └── EmbedOptimizer.php
├── Traits/
│   ├── HasOptimizedImages.php
│   ├── CachesQueries.php
│   └── TracksPerformance.php
├── View/
│   ├── Components/
│   │   ├── LazyImage.php
│   │   ├── ResponsiveImage.php
│   │   ├── DeferredScript.php
│   │   ├── CriticalCss.php
│   │   └── Prefetch.php
│   └── Composers/
│       └── PerformanceComposer.php
├── Performance.php
├── PerformanceServiceProvider.php
└── helpers.php

resources/
├── views/
│   ├── components/
│   │   ├── lazy-image.blade.php
│   │   ├── responsive-image.blade.php
│   │   ├── deferred-script.blade.php
│   │   └── prefetch.blade.php
│   ├── dashboard/
│   │   ├── index.blade.php
│   │   ├── metrics.blade.php
│   │   ├── cache.blade.php
│   │   ├── images.blade.php
│   │   ├── queries.blade.php
│   │   └── recommendations.blade.php
│   └── livewire/
│       ├── performance-dashboard.blade.php
│       ├── metrics-chart.blade.php
│       ├── cache-manager.blade.php
│       └── query-analyzer.blade.php
└── js/
    ├── performance-monitor.js
    ├── lazy-load.js
    ├── speculative-rules.js
    └── web-vitals.js
```

### Component Relationships

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           Request Lifecycle                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│  │ Early Hints │───▶│ Page Cache  │───▶│   Laravel   │                 │
│  │ Middleware  │    │ Middleware  │    │   Router    │                 │
│  └─────────────┘    └─────────────┘    └──────┬──────┘                 │
│                                               │                         │
│                     ┌─────────────────────────┼─────────────────────────┤
│                     │                         ▼                         │
│                     │              ┌─────────────────┐                  │
│                     │              │   Controller    │                  │
│                     │              └────────┬────────┘                  │
│                     │                       │                           │
│  ┌──────────────────┼───────────────────────┼───────────────────────┐  │
│  │                  │      View Rendering   │                       │  │
│  │  ┌───────────────┴───────────────────────┴────────────────────┐  │  │
│  │  │                                                            │  │  │
│  │  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐    │  │  │
│  │  │  │ Lazy Image  │  │  Deferred   │  │   Fragment      │    │  │  │
│  │  │  │ Components  │  │   Scripts   │  │    Cache        │    │  │  │
│  │  │  └─────────────┘  └─────────────┘  └─────────────────┘    │  │  │
│  │  │                                                            │  │  │
│  │  └────────────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                               │                         │
│                     ┌─────────────────────────┼─────────────────────────┤
│                     │                         ▼                         │
│                     │              ┌─────────────────┐                  │
│                     │              │ Output Buffer   │                  │
│                     │              └────────┬────────┘                  │
│                     │                       │                           │
│                     │         ┌─────────────┼─────────────┐             │
│                     │         ▼             ▼             ▼             │
│                     │  ┌───────────┐ ┌───────────┐ ┌───────────┐       │
│                     │  │  Minify   │ │ Resource  │ │Speculative│       │
│                     │  │   HTML    │ │   Hints   │ │   Rules   │       │
│                     │  └───────────┘ └───────────┘ └───────────┘       │
│                     │                       │                           │
│                     └───────────────────────┼───────────────────────────┤
│                                             ▼                           │
│                                    ┌─────────────────┐                  │
│                                    │    Response     │                  │
│                                    └─────────────────┘                  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Image Optimization

### Features

#### 1. Modern Format Conversion (WebP/AVIF)

**Purpose**: Serve images in modern formats for smaller file sizes

**Implementation**:
```php
// Automatic conversion on upload
$image = perfConvertToWebP($uploadedFile);
$image = perfConvertToAvif($uploadedFile);

// Or via queue
ConvertImageJob::dispatch($imagePath, ['webp', 'avif']);
```

**Supported Formats**:
- WebP (85%+ browser support)
- AVIF (75%+ browser support, better compression)
- Original fallback for unsupported browsers

**Processing Pipeline**:
```
Original Upload ──▶ Queue Job ──▶ Generate WebP ──▶ Generate AVIF
                                       │                   │
                                       ▼                   ▼
                              Store alongside original
```

#### 2. Lazy Loading

**Purpose**: Defer loading of off-screen images

**Implementation**:
```blade
{{-- Using component --}}
<x-perf-lazy-image
    src="/images/hero.jpg"
    alt="Hero image"
    width="1200"
    height="600"
/>

{{-- Using Blade directive --}}
@lazyImage('/images/hero.jpg', 'Hero image', ['width' => 1200, 'height' => 600])

{{-- Native lazy loading with fallback --}}
<img src="image.jpg" loading="lazy" decoding="async" />
```

**Features**:
- Native `loading="lazy"` attribute
- JavaScript fallback for older browsers
- Configurable threshold (viewport distance)
- Placeholder support (blur, dominant color, skeleton)

#### 3. Responsive Images (srcset)

**Purpose**: Serve appropriately sized images for different viewports

**Implementation**:
```blade
<x-perf-responsive-image
    src="/images/hero.jpg"
    alt="Hero image"
    :sizes="['sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280]"
    sizes-attr="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
/>
```

**Output**:
```html
<picture>
    <source type="image/avif" srcset="
        hero-640.avif 640w,
        hero-768.avif 768w,
        hero-1024.avif 1024w,
        hero-1280.avif 1280w
    " sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw">
    <source type="image/webp" srcset="
        hero-640.webp 640w,
        hero-768.webp 768w,
        hero-1024.webp 1024w,
        hero-1280.webp 1280w
    " sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw">
    <img src="hero-1024.jpg" alt="Hero image" loading="lazy" decoding="async">
</picture>
```

#### 4. Dominant Color Placeholders

**Purpose**: Show image's dominant color while loading to reduce CLS

**Implementation**:
```php
// Extract dominant color
$color = perfGetDominantColor($imagePath); // Returns '#3b82f6'

// Store with image metadata
$image->dominant_color = $color;
```

**Output**:
```html
<img
    src="hero.jpg"
    alt="Hero"
    style="background-color: #3b82f6;"
    loading="lazy"
>
```

#### 5. Fetchpriority Attribute

**Purpose**: Hint browser about image loading priority

**Implementation**:
```blade
{{-- High priority for LCP image --}}
<x-perf-lazy-image
    src="/images/hero.jpg"
    fetchpriority="high"
    :lazy="false"
/>

{{-- Low priority for below-fold images --}}
<x-perf-lazy-image
    src="/images/footer-logo.jpg"
    fetchpriority="low"
/>
```

#### 6. Auto-sizes for Lazy Images

**Purpose**: Automatically calculate sizes attribute for lazy images

**Implementation**:
```blade
{{-- Auto-calculate sizes based on rendered size --}}
<x-perf-lazy-image
    src="/images/content.jpg"
    auto-sizes
/>
```

**JavaScript**:
```javascript
// Observe image, set sizes when it enters viewport
document.querySelectorAll('img[data-auto-sizes]').forEach(img => {
    img.sizes = `${img.getBoundingClientRect().width}px`;
});
```

### Image Model Trait

```php
use ArtisanPackUI\Performance\Traits\HasOptimizedImages;

class Post extends Model
{
    use HasOptimizedImages;

    /**
     * Define image fields and their optimization settings.
     */
    protected function optimizableImages(): array
    {
        return [
            'featured_image' => [
                'sizes' => [640, 768, 1024, 1280, 1920],
                'formats' => ['webp', 'avif'],
                'quality' => 80,
                'extract_dominant_color' => true,
            ],
            'thumbnail' => [
                'sizes' => [150, 300],
                'formats' => ['webp'],
                'quality' => 85,
            ],
        ];
    }
}
```

---

## JavaScript & CSS Optimization

### Script Loading Strategies

#### 1. Defer Loading

**Purpose**: Load scripts after HTML parsing completes

```blade
@deferScript('/js/analytics.js')

{{-- Or with component --}}
<x-perf-script src="/js/analytics.js" strategy="defer" />
```

**Output**:
```html
<script src="/js/analytics.js" defer></script>
```

#### 2. Async Loading

**Purpose**: Load scripts asynchronously without blocking

```blade
@asyncScript('/js/widget.js')

<x-perf-script src="/js/widget.js" strategy="async" />
```

#### 3. Module Loading

**Purpose**: Load ES modules with automatic defer behavior

```blade
<x-perf-script src="/js/app.mjs" strategy="module" />
```

**Output**:
```html
<script type="module" src="/js/app.mjs"></script>
```

#### 4. Conditional Loading

**Purpose**: Load scripts only when needed

```blade
{{-- Load only on interaction --}}
<x-perf-script
    src="/js/heavy-widget.js"
    :load-on="['click', 'mouseover']"
    target="#widget-container"
/>

{{-- Load when element is visible --}}
<x-perf-script
    src="/js/comments.js"
    load-on="visible"
    target="#comments-section"
/>
```

### Script Manager Service

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Register scripts with strategies
Performance::script('/js/app.js')->defer();
Performance::script('/js/analytics.js')->async();
Performance::script('/js/polyfill.js')->inline();

// Conditional loading
Performance::script('/js/editor.js')
    ->loadOn('interaction')
    ->target('#editor');

// Priority ordering
Performance::script('/js/critical.js')->priority(1);
Performance::script('/js/non-critical.js')->priority(10);
```

### CSS Optimization

#### 1. Critical CSS Extraction

**Purpose**: Inline above-the-fold CSS for faster rendering

```blade
{{-- In layout head --}}
@criticalCss

{{-- Or with component --}}
<x-perf-critical-css :route="request()->route()->getName()" />
```

**Artisan Command**:
```bash
# Generate critical CSS for routes
php artisan perf:critical-css --route=home
php artisan perf:critical-css --all
```

#### 2. CSS Loading Optimization

```blade
{{-- Preload critical CSS --}}
<link rel="preload" href="/css/critical.css" as="style">

{{-- Load non-critical CSS asynchronously --}}
<x-perf-stylesheet href="/css/non-critical.css" :critical="false" />
```

**Output**:
```html
<link rel="preload" href="/css/non-critical.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/css/non-critical.css"></noscript>
```

### Resource Hints

#### Available Hints

| Hint | Purpose | Use Case |
|------|---------|----------|
| `dns-prefetch` | Resolve DNS early | Third-party domains |
| `preconnect` | Establish connection early | Critical third-party resources |
| `prefetch` | Fetch resource for future navigation | Next page resources |
| `preload` | Fetch critical resource for current page | Fonts, critical images |
| `prerender` | Render entire page in background | Highly likely next page |

#### Implementation

```blade
{{-- Individual hints --}}
@preconnect('https://fonts.googleapis.com')
@dnsPrefetch('https://analytics.example.com')
@preload('/fonts/inter.woff2', 'font', 'font/woff2')
@prefetch('/js/next-page.js')

{{-- Or using component --}}
<x-perf-resource-hints :hints="[
    ['rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com'],
    ['rel' => 'preload', 'href' => '/fonts/inter.woff2', 'as' => 'font', 'type' => 'font/woff2'],
]" />
```

#### Automatic Hint Generation

```php
// In config/artisanpack/performance.php
'resource_hints' => [
    'auto_generate' => true,
    'preconnect' => [
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
    ],
    'dns_prefetch' => [
        'https://www.google-analytics.com',
    ],
],
```

---

## Speculative Loading

### Speculative Rules API

**Purpose**: Enable instant page navigations by pre-rendering likely next pages

#### Implementation

```blade
{{-- Add speculative rules to page --}}
@speculativeRules

{{-- Or with configuration --}}
<x-perf-speculative-rules
    :prefetch="['moderate']"
    :prerender="['conservative']"
/>
```

**Output**:
```html
<script type="speculationrules">
{
    "prefetch": [
        {
            "source": "document",
            "where": { "href_matches": "/*" },
            "eagerness": "moderate"
        }
    ],
    "prerender": [
        {
            "source": "document",
            "where": { "selector_matches": "a[data-prerender]" },
            "eagerness": "conservative"
        }
    ]
}
</script>
```

#### Eagerness Levels

| Level | Trigger | Use Case |
|-------|---------|----------|
| `immediate` | Page load | Highly confident next page |
| `eager` | Hover (200ms) | Likely navigation |
| `moderate` | Hover (hover intent) | Possible navigation |
| `conservative` | Pointer down | Confirmed intent |

#### Configuration

```php
// config/artisanpack/performance.php
'speculative_loading' => [
    'enabled' => true,
    'prefetch' => [
        'eagerness' => 'moderate',
        'exclude_patterns' => [
            '/logout',
            '/admin/*',
            '*.pdf',
        ],
    ],
    'prerender' => [
        'eagerness' => 'conservative',
        'limit' => 2, // Max concurrent prerenders
        'include_patterns' => [
            '/products/*',
            '/blog/*',
        ],
    ],
],
```

#### Blade Attributes

```blade
{{-- Mark link for prerendering --}}
<a href="/products/1" data-prerender>Product 1</a>

{{-- Mark link for prefetching --}}
<a href="/blog/post" data-prefetch>Blog Post</a>

{{-- Exclude from speculation --}}
<a href="/logout" data-no-speculate>Logout</a>
```

### Embed Optimizer

**Purpose**: Optimize third-party embeds (YouTube, Twitter, etc.)

#### Implementation

```blade
{{-- Lazy load YouTube embed --}}
<x-perf-embed
    provider="youtube"
    id="dQw4w9WgXcQ"
    :lazy="true"
/>

{{-- Lazy load with facade placeholder --}}
<x-perf-embed
    provider="twitter"
    id="1234567890"
    :lazy="true"
    :show-facade="true"
/>
```

**Output (before interaction)**:
```html
<div class="perf-embed-facade" data-provider="youtube" data-id="dQw4w9WgXcQ">
    <img src="/placeholders/youtube-dQw4w9WgXcQ.jpg" alt="Video thumbnail">
    <button class="play-button" aria-label="Play video">▶</button>
</div>
```

**After click**: Loads actual embed iframe

---

## Server-Side Optimizations

### Output Buffering & HTML Minification

#### Implementation

```php
// Enable via middleware
Route::middleware('perf.minify')->group(function () {
    // Routes with minified HTML output
});

// Or globally in bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\ArtisanPackUI\Performance\Http\Middleware\MinifyHtml::class);
})
```

#### Minification Options

```php
// config/artisanpack/performance.php
'html_minification' => [
    'enabled' => true,
    'remove_comments' => true,
    'remove_whitespace' => true,
    'preserve_line_breaks' => false,
    'exclude_routes' => [
        'admin/*',
        'api/*',
    ],
    'exclude_elements' => ['pre', 'code', 'textarea', 'script'],
],
```

### Resource Hint Injection

**Purpose**: Automatically inject resource hints into HTML response

```php
// Middleware automatically adds:
// - Preconnect for detected third-party domains
// - DNS prefetch for external resources
// - Preload for critical assets
```

### HTTP/2 Early Hints (103)

**Purpose**: Send hints before main response is ready

```php
// Enable via middleware
Route::middleware('perf.early-hints')->group(function () {
    // Routes with early hints
});
```

**Response Flow**:
```
HTTP/1.1 103 Early Hints
Link: </css/app.css>; rel=preload; as=style
Link: </js/app.js>; rel=preload; as=script
Link: <https://fonts.googleapis.com>; rel=preconnect

HTTP/1.1 200 OK
Content-Type: text/html
...
```

### Configuration

```php
'early_hints' => [
    'enabled' => true,
    'auto_detect' => true, // Auto-detect critical resources
    'manual_hints' => [
        ['href' => '/css/app.css', 'rel' => 'preload', 'as' => 'style'],
        ['href' => '/js/app.js', 'rel' => 'preload', 'as' => 'script'],
    ],
],
```

---

## Database Optimization

### N+1 Query Detection

**Purpose**: Identify and log N+1 query problems

#### Implementation

```php
// Enable via middleware
Route::middleware('perf.detect-n1')->group(function () {
    // Routes with N+1 detection
});

// Or enable globally
// config/artisanpack/performance.php
'database' => [
    'n1_detection' => [
        'enabled' => true,
        'threshold' => 5, // Trigger after 5 similar queries
        'log_channel' => 'performance',
        'notify' => true, // Send notification
    ],
],
```

#### Output

```
[PERFORMANCE WARNING] N+1 Query Detected
Model: App\Models\Post
Relation: comments
Query Count: 25
Suggestion: Use ->with('comments') for eager loading

Query Pattern:
SELECT * FROM comments WHERE post_id = ?
Executed 25 times
```

### Slow Query Logging

**Purpose**: Track and analyze slow database queries

#### Implementation

```php
// config/artisanpack/performance.php
'database' => [
    'slow_query_logging' => [
        'enabled' => true,
        'threshold_ms' => 100, // Log queries > 100ms
        'log_channel' => 'performance',
        'store_in_database' => true, // Store for dashboard
        'retention_days' => 30,
    ],
],
```

#### Logged Data

```php
[
    'query' => 'SELECT * FROM posts WHERE ...',
    'bindings' => [...],
    'time_ms' => 250,
    'connection' => 'mysql',
    'file' => 'app/Http/Controllers/PostController.php',
    'line' => 45,
    'trace' => [...],
]
```

### Index Suggestions

**Purpose**: Analyze queries and suggest missing indexes

```bash
php artisan perf:suggest-indexes
```

**Output**:
```
Analyzing slow queries...

Suggested Indexes:
┌─────────────────┬──────────────────────────────────┬─────────────────┐
│ Table           │ Suggested Index                  │ Potential Impact│
├─────────────────┼──────────────────────────────────┼─────────────────┤
│ posts           │ INDEX (user_id, created_at)      │ High            │
│ comments        │ INDEX (post_id, approved)        │ Medium          │
│ orders          │ INDEX (status, created_at DESC)  │ High            │
└─────────────────┴──────────────────────────────────┴─────────────────┘

Generate migration? [y/N]
```

### Query Caching Helper

**Purpose**: Easy caching for expensive queries

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Cache query result
$posts = Performance::cacheQuery(
    fn() => Post::with('author')->popular()->get(),
    'popular-posts',
    ttl: 3600
);

// Or use trait
class Post extends Model
{
    use CachesQueries;
}

$posts = Post::cacheFor(3600)->popular()->get();
```

---

## Caching System

### Page Caching

**Purpose**: Cache entire HTML responses

#### Implementation

```php
// Via middleware
Route::middleware('perf.page-cache')->group(function () {
    Route::get('/', HomeController::class);
    Route::get('/products', [ProductController::class, 'index']);
});

// Or via attribute
#[PageCache(ttl: 3600)]
public function index()
{
    return view('products.index');
}
```

#### Configuration

```php
'page_cache' => [
    'enabled' => true,
    'driver' => 'file', // file, redis, memcached
    'ttl' => 3600, // Default TTL in seconds
    'exclude_routes' => [
        'admin/*',
        'user/*',
    ],
    'exclude_when' => [
        'authenticated', // Don't cache for logged-in users
        'has_flash',     // Don't cache with flash messages
    ],
    'vary_by' => [
        'Accept-Encoding',
        'Accept-Language',
    ],
    'cache_query_strings' => false, // Include query strings in cache key
],
```

#### Cache Invalidation

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Invalidate specific page
Performance::invalidatePageCache('/products');

// Invalidate by pattern
Performance::invalidatePageCache('/products/*');

// Invalidate all
Performance::flushPageCache();

// Auto-invalidation via model events
class Product extends Model
{
    protected static function booted()
    {
        static::saved(fn() => Performance::invalidatePageCache('/products*'));
    }
}
```

### Fragment Caching

**Purpose**: Cache expensive view partials

#### Implementation

```blade
{{-- Cache expensive partial for 1 hour --}}
@cache('sidebar-popular-posts', 3600)
    @foreach($popularPosts as $post)
        <x-post-card :post="$post" />
    @endforeach
@endcache

{{-- Cache with dynamic key --}}
@cache("user-{$user->id}-notifications", 300)
    @include('partials.notifications')
@endcache

{{-- Cache with tags for easy invalidation --}}
@cache('homepage-featured', 3600, ['homepage', 'products'])
    @include('partials.featured-products')
@endcache
```

#### Programmatic Usage

```php
use ArtisanPackUI\Performance\Facades\Performance;

$html = Performance::fragmentCache('expensive-widget', 3600, function () {
    return view('widgets.expensive')->render();
});

// With tags
$html = Performance::fragmentCache('user-stats', 300, function () use ($user) {
    return view('widgets.user-stats', compact('user'))->render();
}, tags: ['user', "user-{$user->id}"]);

// Invalidate by tag
Performance::invalidateFragmentsByTag('user');
```

### Object Caching Helpers

**Purpose**: Simplify caching of arbitrary data

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Simple remember
$data = Performance::remember('expensive-calculation', 3600, fn() => calculate());

// Remember forever
$config = Performance::rememberForever('app-config', fn() => loadConfig());

// Tagged caching
$posts = Performance::tags(['posts', 'homepage'])
    ->remember('featured-posts', 3600, fn() => Post::featured()->get());

// Invalidate by tag
Performance::invalidateTags(['posts']);
```

### Cache Warming

**Purpose**: Pre-populate cache for optimal performance

```bash
# Warm page cache
php artisan perf:warm-cache --type=page

# Warm specific routes
php artisan perf:warm-cache --routes=home,products.index

# Warm from sitemap
php artisan perf:warm-cache --sitemap=public/sitemap.xml

# Schedule warming
# In app/Console/Kernel.php
$schedule->command('perf:warm-cache')->hourly();
```

#### Configuration

```php
'cache_warming' => [
    'enabled' => true,
    'routes' => [
        'home',
        'products.index',
        'blog.index',
    ],
    'urls' => [
        '/',
        '/products',
        '/about',
    ],
    'concurrent_requests' => 5,
    'delay_ms' => 100, // Delay between requests
],
```

---

## Performance Monitoring

### Core Web Vitals Tracking

**Purpose**: Track LCP, FID/INP, CLS in real user monitoring

#### Implementation

```blade
{{-- Add to layout before </body> --}}
@perfMonitor
```

**JavaScript**:
```javascript
// Automatically included
import { onLCP, onFID, onCLS, onINP, onTTFB } from 'web-vitals';

onLCP(metric => sendToAnalytics('LCP', metric));
onFID(metric => sendToAnalytics('FID', metric));
onCLS(metric => sendToAnalytics('CLS', metric));
onINP(metric => sendToAnalytics('INP', metric));
onTTFB(metric => sendToAnalytics('TTFB', metric));
```

#### Backend Collection

```php
// API endpoint receives metrics
POST /api/performance/metrics
{
    "name": "LCP",
    "value": 2100,
    "delta": 2100,
    "id": "v3-1234567890",
    "page": "/products",
    "user_agent": "...",
    "connection": "4g"
}
```

### Metrics Storage

```php
// Aggregated metrics stored in database
[
    'date' => '2026-01-24',
    'route' => 'products.index',
    'metric' => 'LCP',
    'p50' => 1800,
    'p75' => 2400,
    'p90' => 3200,
    'p99' => 5100,
    'sample_count' => 1250,
]
```

### Performance Dashboard

**Purpose**: Visualize performance metrics and provide recommendations

#### Features

1. **Overview Tab**
   - Core Web Vitals summary
   - Pass/fail status per metric
   - Trend charts (7 days, 30 days)

2. **Pages Tab**
   - Per-page performance breakdown
   - Worst performing pages
   - Improvement suggestions

3. **Images Tab**
   - Optimization status
   - Unoptimized images list
   - Batch optimization actions

4. **Cache Tab**
   - Cache hit/miss rates
   - Cache size by type
   - Manual invalidation controls

5. **Queries Tab**
   - Slow queries list
   - N+1 detections
   - Index suggestions

6. **Recommendations Tab**
   - Actionable improvements
   - Priority ordering
   - One-click fixes where possible

#### Customization

```php
// Extend or replace dashboard components
use ArtisanPackUI\Performance\Livewire\PerformanceDashboard;

class CustomDashboard extends PerformanceDashboard
{
    // Override tabs
    protected function getTabs(): array
    {
        return array_merge(parent::getTabs(), [
            'custom' => 'Custom Metrics',
        ]);
    }

    // Add custom metrics
    protected function getCustomMetrics(): array
    {
        return [
            // Custom implementation
        ];
    }
}

// Register in service provider
Livewire::component('custom-performance-dashboard', CustomDashboard::class);
```

### Alerting

```php
// config/artisanpack/performance.php
'alerts' => [
    'enabled' => true,
    'channels' => ['mail', 'slack'],
    'thresholds' => [
        'LCP' => 4000, // Alert if p75 > 4s
        'FID' => 300,  // Alert if p75 > 300ms
        'CLS' => 0.25, // Alert if p75 > 0.25
        'slow_queries' => 10, // Alert if > 10 slow queries/hour
    ],
    'recipients' => [
        'admin@example.com',
    ],
],
```

---

## Database Schema

### Tables

#### `performance_metrics`
```php
Schema::create('performance_metrics', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('route')->nullable();
    $table->string('url')->nullable();
    $table->string('metric'); // LCP, FID, CLS, INP, TTFB
    $table->float('p50');
    $table->float('p75');
    $table->float('p90');
    $table->float('p99');
    $table->unsignedInteger('sample_count');
    $table->string('device_type')->nullable(); // mobile, desktop, tablet
    $table->string('connection_type')->nullable(); // 4g, 3g, slow-2g
    $table->timestamps();

    $table->index(['date', 'metric']);
    $table->index(['route', 'date']);
});
```

#### `performance_slow_queries`
```php
Schema::create('performance_slow_queries', function (Blueprint $table) {
    $table->id();
    $table->text('query');
    $table->text('query_normalized'); // For grouping
    $table->json('bindings')->nullable();
    $table->float('time_ms');
    $table->string('connection');
    $table->string('file')->nullable();
    $table->unsignedInteger('line')->nullable();
    $table->json('trace')->nullable();
    $table->string('route')->nullable();
    $table->timestamps();

    $table->index('created_at');
    $table->index('time_ms');
});
```

#### `performance_cache_entries`
```php
Schema::create('performance_cache_entries', function (Blueprint $table) {
    $table->id();
    $table->string('key');
    $table->string('type'); // page, fragment, object
    $table->string('route')->nullable();
    $table->unsignedBigInteger('size_bytes');
    $table->unsignedBigInteger('hits')->default(0);
    $table->unsignedBigInteger('misses')->default(0);
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();

    $table->index('key');
    $table->index('type');
});
```

#### `performance_optimized_images`
```php
Schema::create('performance_optimized_images', function (Blueprint $table) {
    $table->id();
    $table->string('original_path');
    $table->string('optimized_path');
    $table->string('format'); // webp, avif
    $table->unsignedInteger('width');
    $table->unsignedInteger('height');
    $table->unsignedBigInteger('original_size');
    $table->unsignedBigInteger('optimized_size');
    $table->float('compression_ratio');
    $table->string('dominant_color', 7)->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index('original_path');
    $table->unique(['original_path', 'format', 'width']);
});
```

---

## Configuration

### Main Configuration File (`config/artisanpack/performance.php`)

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Features
    |--------------------------------------------------------------------------
    |
    | All features are opt-in. Enable only what you need.
    |
    */
    'features' => [
        'image_optimization' => env('PERF_IMAGE_OPTIMIZATION', false),
        'lazy_loading' => env('PERF_LAZY_LOADING', false),
        'script_optimization' => env('PERF_SCRIPT_OPTIMIZATION', false),
        'critical_css' => env('PERF_CRITICAL_CSS', false),
        'resource_hints' => env('PERF_RESOURCE_HINTS', false),
        'speculative_loading' => env('PERF_SPECULATIVE_LOADING', false),
        'html_minification' => env('PERF_HTML_MINIFICATION', false),
        'page_cache' => env('PERF_PAGE_CACHE', false),
        'fragment_cache' => env('PERF_FRAGMENT_CACHE', false),
        'query_optimization' => env('PERF_QUERY_OPTIMIZATION', false),
        'monitoring' => env('PERF_MONITORING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Optimization
    |--------------------------------------------------------------------------
    */
    'images' => [
        'driver' => env('PERF_IMAGE_DRIVER', 'gd'), // gd, imagick, cloudinary
        'queue' => env('PERF_IMAGE_QUEUE', 'default'),
        'formats' => [
            'webp' => ['enabled' => true, 'quality' => 80],
            'avif' => ['enabled' => true, 'quality' => 70],
        ],
        'sizes' => [320, 640, 768, 1024, 1280, 1920],
        'lazy_loading' => [
            'enabled' => true,
            'placeholder' => 'dominant_color', // dominant_color, blur, skeleton, none
            'threshold' => '200px', // Load when within 200px of viewport
        ],
        'fetchpriority' => [
            'auto_detect_lcp' => true,
        ],
        'dominant_color' => [
            'enabled' => true,
            'algorithm' => 'average', // average, quantize
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JavaScript Optimization
    |--------------------------------------------------------------------------
    */
    'javascript' => [
        'default_strategy' => 'defer', // defer, async, module, inline
        'priority_scripts' => [
            // Scripts that should load with high priority
        ],
        'deferred_scripts' => [
            // Scripts that can be deferred
        ],
        'conditional_loading' => [
            'enabled' => true,
            'strategies' => ['interaction', 'visible', 'idle'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSS Optimization
    |--------------------------------------------------------------------------
    */
    'css' => [
        'critical' => [
            'enabled' => true,
            'width' => 1300,
            'height' => 900,
            'cache' => true,
        ],
        'async_loading' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Hints
    |--------------------------------------------------------------------------
    */
    'resource_hints' => [
        'auto_generate' => true,
        'preconnect' => [],
        'dns_prefetch' => [],
        'preload' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Speculative Loading
    |--------------------------------------------------------------------------
    */
    'speculative_loading' => [
        'enabled' => true,
        'prefetch' => [
            'eagerness' => 'moderate',
            'exclude_patterns' => ['/logout', '/admin/*', '*.pdf'],
        ],
        'prerender' => [
            'eagerness' => 'conservative',
            'limit' => 2,
            'include_patterns' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML Minification
    |--------------------------------------------------------------------------
    */
    'html_minification' => [
        'enabled' => true,
        'remove_comments' => true,
        'remove_whitespace' => true,
        'preserve_line_breaks' => false,
        'exclude_routes' => ['admin/*', 'api/*'],
        'exclude_elements' => ['pre', 'code', 'textarea', 'script'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Early Hints (HTTP 103)
    |--------------------------------------------------------------------------
    */
    'early_hints' => [
        'enabled' => true,
        'auto_detect' => true,
        'manual_hints' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Caching
    |--------------------------------------------------------------------------
    */
    'page_cache' => [
        'enabled' => true,
        'driver' => env('PERF_PAGE_CACHE_DRIVER', 'file'),
        'ttl' => 3600,
        'exclude_routes' => ['admin/*', 'user/*'],
        'exclude_when' => ['authenticated', 'has_flash'],
        'vary_by' => ['Accept-Encoding'],
        'cache_query_strings' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fragment Caching
    |--------------------------------------------------------------------------
    */
    'fragment_cache' => [
        'enabled' => true,
        'driver' => env('PERF_FRAGMENT_CACHE_DRIVER', 'file'),
        'default_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warming
    |--------------------------------------------------------------------------
    */
    'cache_warming' => [
        'enabled' => true,
        'routes' => [],
        'urls' => [],
        'concurrent_requests' => 5,
        'delay_ms' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Optimization
    |--------------------------------------------------------------------------
    */
    'database' => [
        'n1_detection' => [
            'enabled' => true,
            'threshold' => 5,
            'log_channel' => 'performance',
            'notify' => false,
        ],
        'slow_query_logging' => [
            'enabled' => true,
            'threshold_ms' => 100,
            'log_channel' => 'performance',
            'store_in_database' => true,
            'retention_days' => 30,
        ],
        'query_cache' => [
            'enabled' => true,
            'driver' => env('PERF_QUERY_CACHE_DRIVER', 'redis'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => true,
        'collect_web_vitals' => true,
        'sample_rate' => 100, // Percentage of users to monitor
        'store_raw_metrics' => false, // Store individual samples
        'aggregation_interval' => 'hourly', // hourly, daily
        'retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'enabled' => false,
        'channels' => ['mail'],
        'thresholds' => [
            'LCP' => 4000,
            'FID' => 300,
            'CLS' => 0.25,
            'slow_queries' => 10,
        ],
        'recipients' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled' => true,
        'route_prefix' => 'admin/performance',
        'middleware' => ['web', 'auth'],
        'gate' => 'view-performance-dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true,
        'api_prefix' => 'api/performance',
        'api_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Library Integration
    |--------------------------------------------------------------------------
    */
    'media_library_integration' => [
        'enabled' => true, // Auto-detect if media-library is installed
        'optimize_on_upload' => true,
        'generate_formats_on_upload' => true,
    ],
];
```

---

## Livewire Components

### PerformanceDashboard

**File**: `src/Livewire/PerformanceDashboard.php`

**Purpose**: Main dashboard interface

**Props**:
- `dateRange` (string): Selected date range
- `activeTab` (string): Currently active tab

**Methods**:
- `setDateRange(string $range)`: Change date filter
- `refreshMetrics()`: Reload all metrics
- `exportReport()`: Generate downloadable report

### MetricsChart

**File**: `src/Livewire/MetricsChart.php`

**Purpose**: Visualize performance metrics over time

**Props**:
- `metric` (string): Which metric to display
- `dateRange` (string): Date range for data
- `groupBy` (string): Aggregation level (hour, day)

### CacheManager

**File**: `src/Livewire/CacheManager.php`

**Purpose**: Manage cache entries

**Methods**:
- `invalidate(string $key)`: Invalidate specific cache
- `invalidateByTag(string $tag)`: Invalidate by tag
- `flushAll()`: Clear all caches
- `warmCache()`: Trigger cache warming

### ImageOptimizationStatus

**File**: `src/Livewire/ImageOptimizationStatus.php`

**Purpose**: Show image optimization progress

**Methods**:
- `optimizeAll()`: Queue all unoptimized images
- `optimizeSelected(array $ids)`: Optimize specific images
- `regenerate(int $id)`: Regenerate optimized versions

### QueryAnalyzer

**File**: `src/Livewire/QueryAnalyzer.php`

**Purpose**: Display and analyze slow queries

**Methods**:
- `getSlowQueries()`: Fetch slow queries
- `suggestIndexes()`: Get index suggestions
- `exportQueries()`: Export for analysis

### RecommendationsPanel

**File**: `src/Livewire/RecommendationsPanel.php`

**Purpose**: Display actionable recommendations

**Features**:
- Priority-ordered recommendations
- One-click fixes where possible
- Estimated impact indicators
- Progress tracking

---

## Service Classes

### PerformanceService (Facade)

**File**: `src/Services/PerformanceService.php`

```php
class PerformanceService
{
    // Image operations
    public function optimizeImage(string $path, array $options = []): array;
    public function convertToWebP(string $path, int $quality = 80): string;
    public function convertToAvif(string $path, int $quality = 70): string;
    public function getDominantColor(string $path): string;
    public function generateResponsiveSizes(string $path, array $sizes): array;

    // Script management
    public function script(string $src): ScriptBuilder;
    public function registerScript(string $name, string $src, array $options = []): void;
    public function getScripts(): Collection;

    // Caching
    public function remember(string $key, int $ttl, Closure $callback): mixed;
    public function rememberForever(string $key, Closure $callback): mixed;
    public function tags(array $tags): static;
    public function invalidatePageCache(string $pattern): void;
    public function flushPageCache(): void;
    public function fragmentCache(string $key, int $ttl, Closure $callback): string;
    public function invalidateFragmentsByTag(string $tag): void;
    public function cacheQuery(Closure $query, string $key, int $ttl = 3600): mixed;

    // Monitoring
    public function recordMetric(string $name, float $value, array $context = []): void;
    public function getMetrics(string $metric, Carbon $from, Carbon $to): Collection;
    public function getRecommendations(): array;
}
```

### ImageService

**File**: `src/Services/ImageService.php`

```php
class ImageService
{
    public function optimize(string $path, array $options = []): OptimizationResult;
    public function convertFormat(string $path, string $format, int $quality): string;
    public function resize(string $path, int $width, ?int $height = null): string;
    public function generateSrcset(string $path, array $sizes): array;
    public function extractDominantColor(string $path): string;
    public function generatePlaceholder(string $path, string $type): string;
}
```

### CacheService

**File**: `src/Services/CacheService.php`

```php
class CacheService
{
    // Page cache
    public function cacheResponse(Request $request, Response $response): void;
    public function getCachedResponse(Request $request): ?Response;
    public function invalidatePageCache(string $pattern): int;
    public function warmPageCache(array $urls): void;

    // Fragment cache
    public function cacheFragment(string $key, string $html, int $ttl, array $tags = []): void;
    public function getFragment(string $key): ?string;
    public function invalidateFragments(array $keys): void;
    public function invalidateFragmentsByTag(string $tag): void;

    // Statistics
    public function getHitRate(): float;
    public function getCacheSize(): int;
    public function getEntries(): Collection;
}
```

### DatabaseService

**File**: `src/Services/DatabaseService.php`

```php
class DatabaseService
{
    public function detectN1Queries(): array;
    public function getSlowQueries(int $limit = 100): Collection;
    public function suggestIndexes(): array;
    public function analyzeQuery(string $query): QueryAnalysis;
    public function enableQueryLogging(): void;
    public function disableQueryLogging(): void;
}
```

---

## Artisan Commands

### InstallCommand

```bash
php artisan perf:install
```

**Actions**:
- Publish configuration
- Publish migrations
- Run migrations
- Publish assets
- Create dashboard gate

### GenerateWebPCommand

```bash
php artisan perf:generate-webp {path?} --quality=80 --recursive
```

**Actions**:
- Scan for images
- Generate WebP versions
- Report savings

### OptimizeImagesCommand

```bash
php artisan perf:optimize-images {--all} {--path=} {--queue}
```

**Actions**:
- Generate all format variants
- Extract dominant colors
- Create responsive sizes

### WarmCacheCommand

```bash
php artisan perf:warm-cache {--type=page} {--routes=} {--sitemap=}
```

**Actions**:
- Warm page cache
- Warm fragment cache
- Report progress

### PurgeCacheCommand

```bash
php artisan perf:purge-cache {--type=all} {--pattern=}
```

**Actions**:
- Purge specified cache type
- Report cleared entries

### AnalyzePerformanceCommand

```bash
php artisan perf:analyze {--days=7} {--export=}
```

**Actions**:
- Analyze Core Web Vitals
- Generate recommendations
- Export report

### GenerateCriticalCssCommand

```bash
php artisan perf:critical-css {--route=} {--all} {--url=}
```

**Actions**:
- Extract critical CSS
- Cache for routes
- Report coverage

### SuggestIndexesCommand

```bash
php artisan perf:suggest-indexes {--generate}
```

**Actions**:
- Analyze slow queries
- Suggest indexes
- Optionally generate migration

---

## Middleware

### InjectResourceHints

**File**: `src/Http/Middleware/InjectResourceHints.php`

**Purpose**: Add resource hints to HTML responses

**Added Headers/Tags**:
- `Link` headers for preload/preconnect
- `<link>` tags in HTML head

### MinifyHtml

**File**: `src/Http/Middleware/MinifyHtml.php`

**Purpose**: Minify HTML output

**Operations**:
- Remove HTML comments
- Collapse whitespace
- Preserve pre/code/textarea content

### PageCache

**File**: `src/Http/Middleware/PageCache.php`

**Purpose**: Full-page caching

**Logic**:
1. Check if cacheable request
2. Look for cached response
3. If cached, return immediately
4. Otherwise, capture response and cache

### EarlyHints

**File**: `src/Http/Middleware/EarlyHints.php`

**Purpose**: Send HTTP 103 Early Hints

**Logic**:
1. Detect critical resources
2. Send 103 response with Link headers
3. Continue to main response

### MeasurePerformance

**File**: `src/Http/Middleware/MeasurePerformance.php`

**Purpose**: Measure server-side performance

**Collected**:
- TTFB
- Total response time
- Memory usage
- Query count

### DetectSlowQueries

**File**: `src/Http/Middleware/DetectSlowQueries.php`

**Purpose**: Log slow queries and N+1 issues

---

## Blade Directives & Components

### Directives

```blade
{{-- Image optimization --}}
@lazyImage($src, $alt, $attributes = [])
@responsiveImage($src, $alt, $sizes, $attributes = [])
@dominantColor($imagePath)

{{-- Script loading --}}
@deferScript($src)
@asyncScript($src)
@moduleScript($src)
@conditionalScript($src, $event, $target)

{{-- CSS optimization --}}
@criticalCss
@asyncStylesheet($href)

{{-- Resource hints --}}
@preconnect($url)
@dnsPrefetch($url)
@preload($url, $as, $type = null)
@prefetch($url)

{{-- Speculative loading --}}
@speculativeRules

{{-- Caching --}}
@cache($key, $ttl = 3600, $tags = [])
@endcache

{{-- Monitoring --}}
@perfMonitor
```

### Components

```blade
{{-- Image components --}}
<x-perf-lazy-image src="" alt="" />
<x-perf-responsive-image src="" alt="" :sizes="[]" />
<x-perf-picture src="" alt="" :formats="['avif', 'webp']" />

{{-- Script components --}}
<x-perf-script src="" strategy="defer" />
<x-perf-conditional-script src="" load-on="interaction" target="" />

{{-- CSS components --}}
<x-perf-stylesheet href="" :critical="false" />
<x-perf-critical-css :route="''" />

{{-- Resource hints --}}
<x-perf-resource-hints :hints="[]" />
<x-perf-prefetch :urls="[]" />

{{-- Speculative loading --}}
<x-perf-speculative-rules :prefetch="[]" :prerender="[]" />

{{-- Embed optimization --}}
<x-perf-embed provider="youtube" id="" :lazy="true" />
```

---

## View Customization

Developers can customize the package's UI in several ways:

### 1. Publishing Views

Publish all views to your application for full customization:

```bash
php artisan vendor:publish --tag=performance-views
```

This publishes views to `resources/views/vendor/artisanpack-ui/performance/`:

```
resources/views/vendor/artisanpack-ui/performance/
├── components/
│   ├── lazy-image.blade.php
│   ├── responsive-image.blade.php
│   ├── deferred-script.blade.php
│   ├── critical-css.blade.php
│   ├── prefetch.blade.php
│   ├── speculative-rules.blade.php
│   └── embed.blade.php
├── dashboard/
│   ├── index.blade.php
│   ├── metrics.blade.php
│   ├── cache.blade.php
│   ├── images.blade.php
│   ├── queries.blade.php
│   └── recommendations.blade.php
└── livewire/
    ├── performance-dashboard.blade.php
    ├── metrics-chart.blade.php
    ├── cache-manager.blade.php
    ├── image-optimization-status.blade.php
    ├── query-analyzer.blade.php
    └── recommendations-panel.blade.php
```

### 2. Component Customization via Props

Livewire components accept customization props:

```blade
{{-- Custom CSS classes --}}
<livewire:performance-dashboard
    class="my-custom-dashboard"
    :card-classes="'shadow-xl rounded-lg'"
/>

{{-- Custom chart configuration --}}
<livewire:metrics-chart
    metric="LCP"
    :chart-options="['height' => 300, 'colors' => ['#3b82f6']]"
    :show-legend="true"
/>

{{-- Custom labels --}}
<livewire:cache-manager
    :labels="[
        'purge' => 'Clear Cache',
        'warm' => 'Pre-load Cache',
        'invalidate' => 'Remove Entry',
    ]"
/>
```

### 3. Image Component Customization

Image components support extensive customization:

```blade
{{-- Custom placeholder styling --}}
<x-perf-lazy-image
    src="/images/hero.jpg"
    alt="Hero image"
    class="rounded-xl"
    :placeholder-class="'bg-gradient-to-r from-gray-200 to-gray-300 animate-pulse'"
/>

{{-- Custom responsive breakpoints --}}
<x-perf-responsive-image
    src="/images/product.jpg"
    alt="Product"
    :sizes="['sm' => 480, 'md' => 768, 'lg' => 1024, 'xl' => 1440]"
    :picture-class="'product-image-container'"
    :img-class="'object-cover w-full'"
/>
```

### 4. Slot-Based Content Injection

Dashboard components support slots for custom content:

```blade
<livewire:performance-dashboard>
    <x-slot:header>
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Performance Insights</h1>
            <x-artisanpack-button wire:click="exportReport">
                Export Report
            </x-artisanpack-button>
        </div>
    </x-slot:header>

    <x-slot:before-metrics>
        <x-artisanpack-alert type="info">
            Metrics are updated every hour. Last update: {{ now()->format('H:i') }}
        </x-artisanpack-alert>
    </x-slot:before-metrics>

    <x-slot:after-recommendations>
        <div class="mt-4 p-4 bg-base-200 rounded-lg">
            <h4 class="font-semibold">Custom Metrics</h4>
            {{-- Your custom metrics --}}
        </div>
    </x-slot:after-recommendations>
</livewire:performance-dashboard>
```

### 5. CSS Variables for Theming

The default views use CSS variables that can be overridden:

```css
:root {
    /* Dashboard */
    --perf-dashboard-bg: theme('colors.base-100');
    --perf-card-bg: theme('colors.base-200');
    --perf-card-border: theme('colors.base-300');

    /* Metrics colors */
    --perf-metric-good: theme('colors.success');
    --perf-metric-needs-improvement: theme('colors.warning');
    --perf-metric-poor: theme('colors.error');

    /* Charts */
    --perf-chart-primary: theme('colors.primary');
    --perf-chart-secondary: theme('colors.secondary');
    --perf-chart-grid: theme('colors.base-300');

    /* Loading states */
    --perf-skeleton-bg: theme('colors.base-300');
    --perf-skeleton-shimmer: theme('colors.base-100');

    /* Image placeholders */
    --perf-placeholder-bg: theme('colors.base-200');
    --perf-placeholder-text: theme('colors.base-content/50');
}
```

### 6. Configuration-Based Customization

The `config/artisanpack/performance.php` file includes UI settings:

```php
'ui' => [
    'dashboard' => [
        'theme' => 'auto',                    // auto, light, dark
        'show_recommendations' => true,
        'show_quick_actions' => true,
        'default_date_range' => '7d',         // 24h, 7d, 30d, 90d
        'charts' => [
            'type' => 'line',                 // line, bar, area
            'show_grid' => true,
            'animate' => true,
        ],
        'tabs' => [
            'overview' => true,
            'pages' => true,
            'images' => true,
            'cache' => true,
            'queries' => true,
            'recommendations' => true,
        ],
    ],
    'image_components' => [
        'default_placeholder' => 'dominant_color',  // dominant_color, blur, skeleton, none
        'show_loading_indicator' => true,
        'lazy_threshold' => '200px',
    ],
    'custom_css_class' => '',                 // Additional CSS class for all components
    'use_daisyui' => true,                    // Use daisyUI component classes
],
```

### 7. Extending Components

Create custom Livewire components that extend the package's components:

```php
namespace App\Livewire;

use ArtisanPackUI\Performance\Livewire\PerformanceDashboard as BaseDashboard;

class CustomPerformanceDashboard extends BaseDashboard
{
    public function render()
    {
        return view('livewire.custom-performance-dashboard', $this->getViewData());
    }

    // Override methods as needed
    protected function getTabs(): array
    {
        return array_merge(parent::getTabs(), [
            'custom' => 'Custom Metrics',
            'seo' => 'SEO Scores',
        ]);
    }

    // Add custom metrics
    public function getCustomMetrics(): array
    {
        return [
            'api_response_time' => $this->getAverageApiResponseTime(),
            'cache_efficiency' => $this->getCacheEfficiencyScore(),
        ];
    }
}

// Register in AppServiceProvider
Livewire::component('custom-performance-dashboard', CustomPerformanceDashboard::class);
```

### 8. Blade Component Customization

Override individual Blade components by creating them in your application:

```php
// Create: resources/views/components/perf-lazy-image.blade.php
// This will override the package's lazy-image component

@props([
    'src',
    'alt',
    'width' => null,
    'height' => null,
    'placeholder' => 'skeleton',
])

<div {{ $attributes->merge(['class' => 'relative overflow-hidden']) }}>
    {{-- Your custom lazy image implementation --}}
    <img
        data-src="{{ $src }}"
        alt="{{ $alt }}"
        @if($width) width="{{ $width }}" @endif
        @if($height) height="{{ $height }}" @endif
        loading="lazy"
        decoding="async"
        class="lazy-image transition-opacity duration-300"
    />

    {{-- Custom placeholder --}}
    @if($placeholder === 'skeleton')
        <div class="absolute inset-0 bg-base-300 animate-pulse"></div>
    @endif
</div>
```

### 9. JavaScript Customization

Customize JavaScript behavior via configuration:

```javascript
// In your app.js or a dedicated script
document.addEventListener('DOMContentLoaded', function () {
    // Configure lazy loading behavior
    window.perfConfig = {
        lazyLoad: {
            rootMargin: '200px',
            threshold: 0.1,
            onLoad: (img) => {
                img.classList.add('loaded');
                // Custom tracking
                analytics.track('image_loaded', { src: img.src });
            },
        },
        webVitals: {
            onMetric: (metric) => {
                // Send to custom analytics
                customAnalytics.recordMetric(metric.name, metric.value);
            },
        },
    };
});
```

---

## Integration Points

### Media Library Integration

When `artisanpack-ui/media-library` is installed:

```php
// Auto-optimization on upload
// In MediaLibraryServiceProvider (via event listener)
Event::listen(MediaUploaded::class, function ($event) {
    if (config('artisanpack.performance.media_library_integration.optimize_on_upload')) {
        OptimizeImageJob::dispatch($event->media);
    }
});

// Extended Media model
$media->getOptimizedUrl('webp', 'large');
$media->getDominantColor();
$media->getSrcset();
```

### Model Trait: HasOptimizedImages

```php
use ArtisanPackUI\Performance\Traits\HasOptimizedImages;

class Product extends Model
{
    use HasOptimizedImages;

    protected function optimizableImages(): array
    {
        return [
            'image' => [
                'sizes' => [320, 640, 1024],
                'formats' => ['webp', 'avif'],
                'quality' => 80,
            ],
        ];
    }
}

// Usage
$product->getOptimizedImageUrl('image', 'webp', 640);
$product->getImageSrcset('image');
$product->getImageDominantColor('image');
```

### Model Trait: CachesQueries

```php
use ArtisanPackUI\Performance\Traits\CachesQueries;

class Post extends Model
{
    use CachesQueries;
}

// Usage
$posts = Post::cacheFor(3600)->popular()->get();
$post = Post::cacheFor(3600)->find($id);
```

### Helper Functions

```php
// Image helpers
perfOptimizeImage($path, $options = []);
perfConvertToWebP($path, $quality = 80);
perfConvertToAvif($path, $quality = 70);
perfGetDominantColor($path);
perfGetResponsiveSrcset($path, $sizes);

// Cache helpers
perfRemember($key, $ttl, $callback);
perfRememberForever($key, $callback);
perfInvalidateCache($key);
perfFlushCache($type = 'all');

// Monitoring helpers
perfRecordMetric($name, $value, $context = []);
perfGetRecommendations();

// Feature checks
perfFeatureEnabled($feature);
```

---

## Testing Strategy

### Unit Tests

- Image format conversion
- Dominant color extraction
- HTML minification
- Cache key generation
- Query analysis

### Feature Tests

- Middleware behavior
- API endpoints
- Dashboard functionality
- Cache warming
- Image optimization pipeline

### Browser Tests (Dusk)

- Cookie banner interaction
- Dashboard interactions
- Lazy loading behavior

### Performance Tests

- Cache hit performance
- Image processing speed
- Minification overhead
- Query detection accuracy

### Test Helpers

```php
use ArtisanPackUI\Performance\Testing\PerformanceTestHelpers;

class MyTest extends TestCase
{
    use PerformanceTestHelpers;

    public function test_something()
    {
        // Enable features for test
        $this->enableFeature('page_cache');

        // Assert cache behavior
        $this->assertPageCached('/products');

        // Assert image optimization
        $this->assertImageOptimized($path);

        // Measure performance
        $this->assertResponseTimeUnder(500); // ms

        // Check for N+1
        $this->assertNoN1Queries(function () {
            // Code that shouldn't have N+1
        });
    }
}
```

---

## Implementation Phases

### Phase 1: Core Infrastructure (Weeks 1-2)

**Goals**: Establish package foundation

**Tasks**:
- [ ] Set up package structure
- [ ] Create configuration system
- [ ] Implement Performance facade
- [ ] Create database migrations
- [ ] Set up event system
- [ ] Create helper functions
- [ ] Basic unit tests

**Deliverables**:
- Working package scaffold
- Configuration system
- Core service classes

### Phase 2: Image Optimization (Weeks 3-4)

**Goals**: Complete image optimization features

**Tasks**:
- [ ] Implement ImageService
- [ ] WebP conversion with GD/Imagick
- [ ] AVIF conversion
- [ ] Dominant color extraction
- [ ] Responsive image generation
- [ ] Lazy loading component
- [ ] Queue-based processing
- [ ] HasOptimizedImages trait
- [ ] Feature tests

**Deliverables**:
- Full image optimization pipeline
- Blade components for images
- Queue integration

### Phase 3: JavaScript & CSS Optimization (Weeks 5-6)

**Goals**: Script and style optimization

**Tasks**:
- [ ] ScriptManager service
- [ ] Defer/async/module strategies
- [ ] Critical CSS extraction
- [ ] Resource hints injection
- [ ] Blade directives
- [ ] Script components
- [ ] InjectResourceHints middleware
- [ ] Feature tests

**Deliverables**:
- Complete script management
- Critical CSS generation
- Resource hints system

### Phase 4: Speculative Loading (Week 7)

**Goals**: Implement speculation rules and embed optimization

**Tasks**:
- [ ] SpeculativeRulesGenerator
- [ ] Prefetch/prerender management
- [ ] Embed optimizer
- [ ] Blade components
- [ ] JavaScript for speculation
- [ ] Feature tests

**Deliverables**:
- Speculation rules API support
- Embed lazy loading
- YouTube/Twitter optimized embeds

### Phase 5: Caching System (Weeks 8-9)

**Goals**: Complete caching infrastructure

**Tasks**:
- [ ] PageCacheManager
- [ ] Page cache middleware
- [ ] Fragment caching (@cache directive)
- [ ] Cache warming command
- [ ] Cache invalidation
- [ ] CachesQueries trait
- [ ] Redis/Memcached strategies
- [ ] Feature tests

**Deliverables**:
- Full page caching
- Fragment caching
- Cache warming/invalidation

### Phase 6: Database Optimization (Week 10)

**Goals**: Query analysis and optimization

**Tasks**:
- [ ] QueryAnalyzer service
- [ ] N+1 detection
- [ ] Slow query logging
- [ ] Index suggestion
- [ ] DetectSlowQueries middleware
- [ ] Artisan commands
- [ ] Feature tests

**Deliverables**:
- N+1 detection system
- Slow query logging
- Index recommendations

### Phase 7: Server-Side Optimizations (Week 11)

**Goals**: Output optimization and early hints

**Tasks**:
- [ ] HTML minifier
- [ ] Output buffer management
- [ ] MinifyHtml middleware
- [ ] EarlyHints middleware
- [ ] Configuration options
- [ ] Feature tests

**Deliverables**:
- HTML minification
- Early hints support
- Output optimization

### Phase 8: Performance Monitoring (Weeks 12-13)

**Goals**: Metrics collection and dashboard

**Tasks**:
- [ ] Core Web Vitals JavaScript
- [ ] Metrics collection API
- [ ] MetricsAggregator
- [ ] PerformanceDashboard Livewire component
- [ ] MetricsChart component
- [ ] CacheManager component
- [ ] QueryAnalyzer component
- [ ] RecommendationsPanel component
- [ ] View customization support:
  - [ ] `vendor:publish --tag=performance-views` command
  - [ ] Component props (class, card-classes, labels)
  - [ ] Blade slots (header, before-metrics, after-recommendations)
  - [ ] CSS variables for theming
  - [ ] Configuration-based UI settings
  - [ ] Component extension patterns
- [ ] Feature tests

**Deliverables**:
- Real-user monitoring
- Fully customizable admin dashboard
- Actionable recommendations
- Published view customization

### Phase 9: Media Library Integration (Week 14)

**Goals**: Integrate with artisanpack-ui/media-library

**Tasks**:
- [ ] Detect media-library installation
- [ ] Event listeners for auto-optimization
- [ ] Extended Media model methods
- [ ] Migration for optimization metadata
- [ ] Integration tests

**Deliverables**:
- Seamless media-library integration
- Auto-optimization on upload

### Phase 10: Polish & Documentation (Week 15)

**Goals**: Production readiness

**Tasks**:
- [ ] Complete documentation
- [ ] View customization documentation:
  - [ ] Publishing views guide
  - [ ] Component props reference
  - [ ] Slots documentation
  - [ ] CSS variables reference
  - [ ] Component extension examples
  - [ ] JavaScript customization guide
- [ ] Performance benchmarks
- [ ] Security audit
- [ ] Code style compliance
- [ ] 80%+ test coverage
- [ ] Example implementations
- [ ] CHANGELOG

**Deliverables**:
- Complete documentation including view customization
- Production-ready release
- Performance benchmarks

---

## Dependencies

### Required

- `php`: ^8.2
- `illuminate/support`: ^10.0|^11.0|^12.0
- `artisanpack-ui/core`: ^1.0
- `artisanpack-ui/livewire-ui-components`: ^2.0

### Optional

- `intervention/image`: ^3.0 (for image processing)
- `artisanpack-ui/media-library`: ^1.0 (for media library integration)

### Dev Dependencies

- `pestphp/pest`: ^3.8
- `orchestra/testbench`: ^10.2
- `artisanpack-ui/code-style`: ^1.1
- `artisanpack-ui/code-style-pint`: ^1.1

---

## Success Metrics

### Technical

- [ ] 80%+ code coverage
- [ ] All code style checks pass
- [ ] Compatible with Laravel 10, 11, 12
- [ ] < 5ms overhead for middleware stack
- [ ] Queue processing for heavy operations

### Performance Impact

- [ ] Image optimization: 50%+ file size reduction
- [ ] Page cache: < 10ms TTFB for cached pages
- [ ] HTML minification: 10-20% size reduction
- [ ] Critical CSS: Eliminate render-blocking CSS

### User Experience

- [ ] Dashboard loads in < 2s
- [ ] Real-time metric updates
- [ ] Mobile-responsive dashboard
- [ ] Clear, actionable recommendations

---

## Open Questions

1. **Cloud image processing**: Should we support cloud services (Cloudinary, imgix) as first-class drivers, or leave as community extensions?

2. **Build tool integration**: Should critical CSS extraction integrate with Vite, or remain standalone?

3. **Multi-tenancy**: Should caching support multi-tenant applications with tenant-aware cache keys?

4. **Real-time monitoring**: Should we support WebSocket-based real-time dashboard updates?

5. **A/B testing integration**: Should performance features integrate with A/B testing to measure impact?

---

## References

### WordPress Performance Lab

- [Performance Lab Plugin](https://wordpress.org/plugins/performance-lab/)
- [WebP Uploads](https://make.wordpress.org/core/2022/03/28/webp-by-default/)
- [Speculative Loading](https://make.wordpress.org/core/2024/09/25/speculative-loading-in-wordpress-6-7/)
- [Fetchpriority](https://make.wordpress.org/core/2023/07/13/image-loading-optimization-in-wordpress-6-3/)

### Web Performance Standards

- [Core Web Vitals](https://web.dev/vitals/)
- [Speculation Rules API](https://developer.mozilla.org/en-US/docs/Web/API/Speculation_Rules_API)
- [Early Hints](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/103)

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 0.1 | Jan 2026 | Jacob Martella | Initial plan draft |
| 0.2 | Jan 2026 | Jacob Martella | Added View Customization section |
