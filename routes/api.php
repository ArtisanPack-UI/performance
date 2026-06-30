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
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\Performance\Http\Controllers\Api\MetricsApiController;
use Illuminate\Support\Facades\Route;

Route::post( '/metrics', [ MetricsApiController::class, 'store' ] )
    ->name( 'metrics.store' );
