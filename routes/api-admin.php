<?php

/**
 * Performance package admin JSON API routes.
 *
 * Loaded from `PerformanceServiceProvider::registerRoutes()` on top of a
 * session-capable middleware stack (see the service provider's
 * `admin_middleware` default) because
 * `RecommendationsAdminApiController` persists dismissals to the
 * session. Every action here is authorized by the
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
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::get('/dashboard', [DashboardAdminApiController::class, 'show'])
        ->name('dashboard');

    Route::get('/chart', [ChartAdminApiController::class, 'show'])
        ->name('chart');

    Route::get('/cache', [CacheAdminApiController::class, 'index'])
        ->name('cache');
    Route::post('/cache/actions', [CacheAdminApiController::class, 'actions'])
        ->name('cache.actions');

    Route::get('/queries', [QueriesAdminApiController::class, 'index'])
        ->name('queries');
    Route::get('/queries/export', [QueriesAdminApiController::class, 'export'])
        ->name('queries.export');

    Route::get('/recommendations', [RecommendationsAdminApiController::class, 'index'])
        ->name('recommendations');
    Route::post('/recommendations/actions', [RecommendationsAdminApiController::class, 'actions'])
        ->name('recommendations.actions');
});
