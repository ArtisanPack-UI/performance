<?php

/**
 * Multi-tenant setup.
 *
 * Two things need per-tenant scoping when running the Performance
 * package multi-tenant:
 *
 *   1. Cache keys — so tenant A can't see tenant B's cached pages.
 *   2. Metric rows — so the dashboard only shows the current tenant's
 *      Web Vitals and slow queries.
 *
 * Runtime config overrides (before the service provider caches
 * anything for the request) handle both.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ScopePerformanceToTenant
{
    public function handle( Request $request, Closure $next )
    {
        $tenant = $request->route( 'tenant' ) ?? tenant();

        if ( ! $tenant ) {
            return $next( $request );
        }

        // 1. Prefix every cache key with the tenant id so page and
        //    fragment caches segregate cleanly.
        $prefix = "t{$tenant->id}";

        config( [
            'artisanpack.performance.page_cache.key_prefix'     => $prefix,
            'artisanpack.performance.fragment_cache.key_prefix' => $prefix,
        ] );

        // 2. Scope monitoring rows.
        config( [
            'artisanpack.performance.monitoring.tags' => [
                'tenant' => (string) $tenant->id,
            ],
        ] );

        // 3. Custom dashboard gate — tenant-scoped admins only.
        config( [
            'artisanpack.performance.dashboard.gate' => "view-tenant-{$tenant->id}-performance",
        ] );

        return $next( $request );
    }
}

/*
 * Register the middleware early in bootstrap/app.php so it runs
 * before the Performance package's own middleware:
 *
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->web(prepend: [
 *           \App\Http\Middleware\ScopePerformanceToTenant::class,
 *       ]);
 *   })
 *
 * Define the tenant-scoped gate in AuthServiceProvider:
 *
 *   foreach (Tenant::all() as $tenant) {
 *       Gate::define("view-tenant-{$tenant->id}-performance", function ($user) use ($tenant) {
 *           return $user->tenant_id === $tenant->id && $user->hasRole('admin');
 *       });
 *   }
 */
