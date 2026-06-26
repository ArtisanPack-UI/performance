<?php

/**
 * ArtisanPack UI Performance configuration.
 *
 * Defines all settings for the Performance package including feature toggles,
 * image optimization, JavaScript/CSS optimization, resource hints, speculative
 * loading, HTML minification, caching, database optimization, monitoring,
 * alerts, dashboard, routes, and media library integration.
 *
 * All features are opt-in (disabled by default). Enable only what you need.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

return [

	/*
	|--------------------------------------------------------------------------
	| Feature Toggles
	|--------------------------------------------------------------------------
	|
	| Every feature in the Performance package is opt-in. Toggle on only the
	| features you intend to use. Each toggle has a matching environment
	| variable so toggles can be flipped per-environment without code changes.
	|
	*/

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

	/*
	|--------------------------------------------------------------------------
	| Image Optimization
	|--------------------------------------------------------------------------
	|
	| Configure the image optimization pipeline including driver selection
	| (gd, imagick, cloudinary), format conversion targets, responsive image
	| sizes, lazy-loading behavior, fetchpriority handling, and dominant
	| color extraction.
	|
	*/

	'images' => [
		'driver'  => env( 'PERF_IMAGE_DRIVER', 'gd' ),
		'queue'   => env( 'PERF_IMAGE_QUEUE', 'default' ),
		'formats' => [
			'webp' => [ 'enabled' => true, 'quality' => 80 ],
			'avif' => [ 'enabled' => true, 'quality' => 70 ],
		],
		'sizes'        => [ 320, 640, 768, 1024, 1280, 1920 ],
		'lazy_loading' => [
			'enabled'     => true,
			'placeholder' => 'dominant_color',
			'threshold'   => '200px',
		],
		'fetchpriority' => [
			'auto_detect_lcp' => true,
		],
		'dominant_color' => [
			'enabled'     => true,
			'algorithm'   => 'average',
			'cache_store' => null,
			'cache_ttl'   => 0,
		],
		'jobs' => [
			'tries'   => 3,
			'backoff' => 30,
		],
	],

	/*
	|--------------------------------------------------------------------------
	| JavaScript Optimization
	|--------------------------------------------------------------------------
	|
	| Defines the default script loading strategy and lists of scripts that
	| receive prioritized or deferred handling. Conditional loading strategies
	| allow scripts to load based on user interaction, viewport visibility,
	| or browser idle time.
	|
	*/

	'javascript' => [
		'default_strategy'    => 'defer',
		'priority_scripts'    => [],
		'deferred_scripts'    => [],
		'conditional_loading' => [
			'enabled'    => true,
			'strategies' => [ 'interaction', 'visible', 'idle' ],
		],
	],

	/*
	|--------------------------------------------------------------------------
	| CSS Optimization
	|--------------------------------------------------------------------------
	|
	| Configure critical CSS extraction (viewport size, caching) and whether
	| non-critical stylesheets should be loaded asynchronously.
	|
	*/

	'css' => [
		'critical' => [
			'enabled'   => true,
			'width'     => 1300,
			'height'    => 900,
			'cache'     => true,
			'selectors' => [],
			'sources'   => [],
		],
		'async_loading' => true,
	],

	/*
	|--------------------------------------------------------------------------
	| Resource Hints
	|--------------------------------------------------------------------------
	|
	| Define preconnect, dns-prefetch, and preload origins/resources injected
	| into the document head. When `auto_generate` is true the package will
	| also detect candidate hints from rendered HTML.
	|
	*/

	'resource_hints' => [
		'auto_generate' => true,
		'preconnect'    => [],
		'dns_prefetch'  => [],
		'preload'       => [],
	],

	/*
	|--------------------------------------------------------------------------
	| Speculative Loading
	|--------------------------------------------------------------------------
	|
	| Configure the Speculation Rules API integration. Eagerness controls how
	| aggressively the browser prefetches/prerenders candidate URLs.
	|
	*/

	'speculative_loading' => [
		'enabled'  => true,
		'prefetch' => [
			'eagerness'        => 'moderate',
			'exclude_patterns' => [ '/logout', '/admin/*', '*.pdf' ],
		],
		'prerender' => [
			'eagerness'        => 'conservative',
			'limit'            => 2,
			'include_patterns' => [],
		],
	],

	/*
	|--------------------------------------------------------------------------
	| HTML Minification
	|--------------------------------------------------------------------------
	|
	| Configure how the response body is minified before being sent to the
	| client. Routes and HTML elements listed in `exclude_*` are skipped to
	| preserve formatting where whitespace is significant.
	|
	*/

	'html_minification' => [
		'enabled'              => true,
		'remove_comments'      => true,
		'remove_whitespace'    => true,
		'preserve_line_breaks' => false,
		'exclude_routes'       => [ 'admin/*', 'api/*' ],
		'exclude_elements'     => [ 'pre', 'code', 'textarea', 'script' ],
	],

	/*
	|--------------------------------------------------------------------------
	| Early Hints (HTTP 103)
	|--------------------------------------------------------------------------
	|
	| Configure HTTP 103 Early Hints emission. When `auto_detect` is true the
	| package will attempt to derive hints from registered preloads and
	| critical scripts/styles in addition to `manual_hints`.
	|
	*/

	'early_hints' => [
		'enabled'      => true,
		'auto_detect'  => true,
		'manual_hints' => [],
	],

	/*
	|--------------------------------------------------------------------------
	| Page Caching
	|--------------------------------------------------------------------------
	|
	| Configure full-page caching. Use `exclude_routes` and `exclude_when` to
	| prevent caching of authenticated views or routes with flashed session
	| data. `vary_by` lists headers that should produce distinct cache keys.
	|
	*/

	'page_cache' => [
		'enabled'             => true,
		'driver'              => env( 'PERF_PAGE_CACHE_DRIVER', 'file' ),
		'ttl'                 => 3600,
		'exclude_routes'      => [ 'admin/*', 'user/*' ],
		'exclude_when'        => [ 'authenticated', 'has_flash' ],
		'vary_by'             => [ 'Accept-Encoding' ],
		'cache_query_strings' => false,
	],

	/*
	|--------------------------------------------------------------------------
	| Fragment Caching
	|--------------------------------------------------------------------------
	|
	| Configure fragment caching for expensive view partials. Fragments are
	| keyed independently and can be invalidated by tag.
	|
	*/

	'fragment_cache' => [
		'enabled'     => true,
		'driver'      => env( 'PERF_FRAGMENT_CACHE_DRIVER', 'file' ),
		'default_ttl' => 3600,
	],

	/*
	|--------------------------------------------------------------------------
	| Cache Warming
	|--------------------------------------------------------------------------
	|
	| Configure background cache warming. The warmer issues concurrent HTTP
	| requests to the listed routes/URLs with optional inter-request delay
	| to avoid overloading the origin.
	|
	*/

	'cache_warming' => [
		'enabled'             => true,
		'routes'              => [],
		'urls'                => [],
		'concurrent_requests' => 5,
		'delay_ms'            => 100,
	],

	/*
	|--------------------------------------------------------------------------
	| Database Optimization
	|--------------------------------------------------------------------------
	|
	| Configure N+1 detection, slow query logging, and query result caching.
	| Slow queries above `threshold_ms` are optionally persisted to the
	| `performance_slow_queries` table with a retention window.
	|
	*/

	'database' => [
		'n1_detection' => [
			'enabled'     => true,
			'threshold'   => 5,
			'log_channel' => 'performance',
			'notify'      => false,
		],
		'slow_query_logging' => [
			'enabled'           => true,
			'threshold_ms'      => 100,
			'log_channel'       => 'performance',
			'store_in_database' => false,
			'retention_days'    => 30,
		],
		'query_cache' => [
			'enabled' => true,
			'driver'  => env( 'PERF_QUERY_CACHE_DRIVER', 'redis' ),
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Performance Monitoring
	|--------------------------------------------------------------------------
	|
	| Configure real user monitoring (RUM) collection. `sample_rate` controls
	| the percentage of users for whom metrics are recorded. Aggregated
	| metrics are stored according to `aggregation_interval` and pruned per
	| `retention_days`.
	|
	*/

	'monitoring' => [
		'enabled'              => true,
		'collect_web_vitals'   => true,
		'sample_rate'          => 100,
		'store_raw_metrics'    => false,
		'aggregation_interval' => 'hourly',
		'retention_days'       => 90,
	],

	/*
	|--------------------------------------------------------------------------
	| Alerting
	|--------------------------------------------------------------------------
	|
	| Configure alert dispatch for performance threshold breaches. Thresholds
	| are evaluated against aggregated metrics and dispatched through the
	| configured notification channels to the listed recipients.
	|
	*/

	'alerts' => [
		'enabled'    => false,
		'channels'   => [ 'mail' ],
		'thresholds' => [
			'LCP'          => 4000,
			'FID'          => 300,
			'CLS'          => 0.25,
			'slow_queries' => 10,
		],
		'recipients' => [],
	],

	/*
	|--------------------------------------------------------------------------
	| Dashboard
	|--------------------------------------------------------------------------
	|
	| Configure the performance dashboard UI. The dashboard is gated by an
	| ability name so applications can wire authorization through their own
	| policies.
	|
	*/

	'dashboard' => [
		'enabled'      => true,
		'route_prefix' => 'admin/performance',
		'middleware'   => [ 'web', 'auth' ],
		'gate'         => 'view-performance-dashboard',
	],

	/*
	|--------------------------------------------------------------------------
	| Routes
	|--------------------------------------------------------------------------
	|
	| Configure the JSON API exposed by the dashboard. The API is consumed by
	| the bundled Livewire components and may be consumed externally for
	| custom dashboards.
	|
	*/

	'routes' => [
		'enabled'        => true,
		'api_prefix'     => 'api/performance',
		'api_middleware' => [ 'api' ],
	],

	/*
	|--------------------------------------------------------------------------
	| Media Library Integration
	|--------------------------------------------------------------------------
	|
	| When the artisanpack-ui/media-library package is installed, the
	| Performance package can automatically optimize uploaded images and
	| generate modern format variants.
	|
	*/

	'media_library_integration' => [
		'enabled'                     => true,
		'optimize_on_upload'          => true,
		'generate_formats_on_upload'  => true,
	],

	/*
	|--------------------------------------------------------------------------
	| UI Customization
	|--------------------------------------------------------------------------
	|
	| Override the layout and partials used by the dashboard. Leave at the
	| default to use the bundled views; publish them via `vendor:publish`
	| to customize markup.
	|
	*/

	'ui' => [
		'layout' => 'performance::dashboard.layouts.app',
		'theme'  => 'default',
	],

];
