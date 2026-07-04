<?php

/**
 * Page cache configuration example.
 *
 * The page cache stores a full HTML response keyed by URL + vary-by
 * keys, then serves subsequent requests directly from cache. Excluded
 * routes (auth, admin, POST) skip the cache entirely.
 */

return [

    'features' => [
        'page_caching' => true,
    ],

    'page_cache' => [

        'enabled' => true,

        // Any Laravel cache store — redis / memcached / dynamodb / file.
        'driver' => env( 'CACHE_STORE', 'redis' ),

        'ttl' => 3600,

        // Routes that should NEVER hit the cache — auth, mutations,
        // admin, API. Wildcards use Laravel's `Str::is()` matching.
        'exclude_routes' => [
            'admin/*',
            'api/*',
            'login',
            'logout',
            'checkout/*',
        ],

        // Closures that skip the cache for a specific request. Useful
        // for logged-in previews, A/B test buckets, feature flags.
        'exclude_when' => [
            static function ( $request ): bool {
                return $request->user()?->hasRole( 'preview' ) ?? false;
            },
        ],

        // Cache keys are suffixed with the resolved value of each entry.
        // Common choices: `auth` (segments authenticated vs guest), a
        // header ('Accept-Language'), a cookie ('locale'), or a
        // custom closure.
        'vary_by' => [
            'auth',
            'header:Accept-Language',
            static fn ( $request ) => $request->cookie( 'currency', 'USD' ),
        ],

        // When false, ?utm_source=… variants share a single cache
        // entry. Turn on when query strings actually change the page
        // (search, filters).
        'cache_query_strings' => false,
    ],
];

/*
 * Add the middleware to the `web` group in bootstrap/app.php:
 *
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->web(append: [
 *           \ArtisanPackUI\Performance\Http\Middleware\PageCache::class,
 *       ]);
 *   })
 *
 * The middleware is a no-op when the page_cache feature is off, so it
 * is safe to leave in every environment.
 */
