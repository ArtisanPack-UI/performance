<?php

/**
 * Performance package API routes.
 *
 * Loaded from `PerformanceServiceProvider::registerRoutes()` when
 * `artisanpack.performance.routes.enabled` is true. The package's
 * route prefix, throttle middleware, and any user-supplied middleware
 * stack are applied by the loader — this file only declares the
 * endpoints themselves so the surface stays readable.
 *
 * The admin/* group is consumed by the React and Vue companion
 * components (and any custom dashboard) and is gated by the
 * `artisanpack.performance.dashboard.gate` ability.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare(strict_types=1);

use ArtisanPackUI\Performance\Http\Controllers\Api\Admin\CacheAdminApiController;
use ArtisanPackUI\Performance\Http\Controllers\Api\Admin\ChartAdminApiController;
use ArtisanPackUI\Performance\Http\Controllers\Api\Admin\DashboardAdminApiController;
use ArtisanPackUI\Performance\Http\Controllers\Api\Admin\QueriesAdminApiController;
use ArtisanPackUI\Performance\Http\Controllers\Api\Admin\RecommendationsAdminApiController;
use ArtisanPackUI\Performance\Http\Controllers\Api\MetricsApiController;
use Illuminate\Support\Facades\Route;

Route::post('/metrics', [MetricsApiController::class, 'store'])
    ->name('metrics.store');

Route::prefix('admin')->group(function (): void {
    Route::get('/dashboard', [DashboardAdminApiController::class, 'show'])
        ->name('admin.dashboard');

    Route::get('/chart', [ChartAdminApiController::class, 'show'])
        ->name('admin.chart');

    Route::get('/cache', [CacheAdminApiController::class, 'index'])
        ->name('admin.cache');
    Route::post('/cache/actions', [CacheAdminApiController::class, 'actions'])
        ->name('admin.cache.actions');

    Route::get('/queries', [QueriesAdminApiController::class, 'index'])
        ->name('admin.queries');
    Route::get('/queries/export', [QueriesAdminApiController::class, 'export'])
        ->name('admin.queries.export');

    Route::get('/recommendations', [RecommendationsAdminApiController::class, 'index'])
        ->name('admin.recommendations');
    Route::post('/recommendations/actions', [RecommendationsAdminApiController::class, 'actions'])
        ->name('admin.recommendations.actions');
});
