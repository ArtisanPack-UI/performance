<?php

/**
 * Performance package public API routes.
 *
 * Loaded from `PerformanceServiceProvider::registerRoutes()` when
 * `artisanpack.performance.routes.enabled` is true. The stateless metrics
 * ingest lives here — session-bearing admin endpoints live in
 * `routes/api-admin.php` because they need `StartSession` in the
 * middleware pipeline.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare(strict_types=1);

use ArtisanPackUI\Performance\Http\Controllers\Api\MetricsApiController;
use Illuminate\Support\Facades\Route;

Route::post('/metrics', [MetricsApiController::class, 'store'])
    ->name('metrics.store');
