# ArtisanPack UI Performance

Comprehensive performance optimization toolkit for Laravel applications: image
optimization (WebP/AVIF), JavaScript and CSS strategies, resource hints and
speculative loading, page and fragment caching, query analysis, and real-user
performance monitoring.

All features are opt-in. Enable only what you need.

## Requirements

- PHP 8.2+ for Laravel 10, 11, or 12
- PHP 8.3+ for Laravel 13

## Installation

```bash
composer require artisanpack-ui/performance
```

Publish the configuration and run the migrations (migrations ship inside the
package and are picked up automatically — you do not need to publish them):

```bash
php artisan vendor:publish --tag=artisanpack-performance-config
php artisan migrate
```

## Configuration

Configuration lives in `config/artisanpack/performance.php` after publishing.
Every feature is disabled by default — flip the corresponding `features.*`
toggle (or its `PERF_*` environment variable) to opt in.

## Usage

```php
use ArtisanPackUI\Performance\Facades\Performance;

if ( Performance::isFeatureEnabled( 'image_optimization' ) ) {
    Performance::optimizeImage( $path );
}

$value = Performance::remember( 'expensive-key', 3600, fn () => compute() );

Performance::recordMetric( 'LCP', 2100.0 );
```

The same API is available via the `performance()` global helper.

## Events

The package dispatches the following events that applications can listen for:

- `ImageOptimized`
- `CacheWarmed`
- `CachePurged`
- `SlowQueryDetected`
- `N1QueryDetected`
- `PerformanceThresholdExceeded`

## Contributing

As an open source project, this package is open to contributions from anyone.
Please [read through the contributing guidelines](CONTRIBUTING.md) to learn
more about how you can contribute to this project.
