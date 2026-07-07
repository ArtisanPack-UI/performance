<?php

/**
 * Performance package AI JSON API routes.
 *
 * Loaded from `PerformanceServiceProvider::registerRoutes()` under the
 * dedicated `ai_middleware` stack (Sanctum + a `performance.ai.use`
 * Gate by default) because these endpoints dispatch to paid LLM
 * providers — sharing the stateless public middleware used by the
 * metrics ingest would let anonymous callers drain the credit balance.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare(strict_types=1);

use ArtisanPackUI\Performance\Http\Controllers\Api\AiAgentApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai')->group(function (): void {
    Route::post('/query-insight', [AiAgentApiController::class, 'queryInsight'])
        ->name('ai.query-insight');

    Route::post('/optimization-suggestion', [AiAgentApiController::class, 'optimizationSuggestion'])
        ->name('ai.optimization-suggestion');
});
