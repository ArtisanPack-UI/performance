<?php

/**
 * Full-featured configuration example for artisanpack-ui/performance.
 *
 * Every feature toggle is on. Use this to see the shape of every knob
 * before you decide what to enable in your app.
 *
 * A production install rarely wants ALL of these on — critical CSS,
 * for example, has a real build cost. Pare back what you don't need.
 */

return [

    'features' => [
        'image_optimization'  => true,
        'lazy_loading'        => true,
        'script_optimization' => true,
        'critical_css'        => true,
        'resource_hints'      => true,
        'speculative_loading' => true,
        'html_minification'   => true,
        'early_hints'         => true,
        'page_caching'        => true,
        'fragment_caching'    => true,
        'query_optimization'  => true,
        'monitoring'          => true,
        'dashboard'           => true,
    ],

    'image_optimization' => [
        'enabled'   => true,
        'formats'   => [ 'webp', 'avif' ],
        'quality'   => [
            'webp' => 82,
            'avif' => 65,
        ],
        'queue'     => 'images',
        'sizes'     => [
            'thumbnail' => [ 320, 240 ],
            'medium'    => [ 768, 512 ],
            'large'     => [ 1440, 960 ],
        ],
    ],

    'page_cache' => [
        'enabled'             => true,
        'driver'              => env( 'CACHE_STORE', 'redis' ),
        'ttl'                 => 3600,
        'exclude_routes'      => [ 'admin/*', 'api/*' ],
        'exclude_when'        => [],
        'vary_by'             => [ 'auth' ],
        'cache_query_strings' => false,
    ],

    'fragment_cache' => [
        'enabled'     => true,
        'driver'      => env( 'CACHE_STORE', 'redis' ),
        'default_ttl' => 1800,
    ],

    'monitoring' => [
        'enabled'            => true,
        'collect_web_vitals' => true,
        'sample_rate'        => 0.25,
        'endpoint'           => '/api/performance/metrics',
    ],

    'dashboard' => [
        'enabled'      => true,
        'route_prefix' => 'admin/performance',
        'middleware'   => [ 'web', 'auth' ],
        'gate'         => 'view-performance-dashboard',
    ],
];
