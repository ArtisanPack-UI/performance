<?php

/**
 * Minimal configuration example for artisanpack-ui/performance.
 *
 * This is the smallest config that still ships something useful in
 * production — page cache, HTML minification, and Core Web Vitals
 * monitoring. Copy this into config/artisanpack/performance.php as a
 * starting point.
 */

return [

    'features' => [
        'page_caching'      => true,
        'html_minification' => true,
        'monitoring'        => true,
        'dashboard'         => true,
    ],

    'page_cache' => [
        'enabled'             => true,
        'driver'              => env( 'CACHE_STORE', 'redis' ),
        'ttl'                 => 3600,
        'exclude_routes'      => [
            'admin/*',
            'api/*',
            'login',
            'logout',
        ],
        'exclude_when'        => [],
        'vary_by'             => [ 'auth' ],
        'cache_query_strings' => false,
    ],

    'html_minification' => [
        'enabled'        => true,
        'exclude_routes' => [
            'admin/*',
            'api/*',
        ],
    ],

    'monitoring' => [
        'enabled'            => true,
        'collect_web_vitals' => true,
        'sample_rate'        => 1.0,
        'endpoint'           => '/api/performance/metrics',
    ],

    'dashboard' => [
        'enabled'      => true,
        'route_prefix' => 'admin/performance',
        'middleware'   => [ 'web', 'auth' ],
        'gate'         => 'view-performance-dashboard',
    ],
];
