<?php

/**
 * N+1 detection.
 *
 * `N1Detector` hooks Laravel's query log and flags relations that were
 * loaded lazily inside a loop. It emits an `N1QueryDetected` event so
 * you can log, alert, or fail tests.
 */

// ---------------------------------------------------------------------
// 1. Enable in config.
// ---------------------------------------------------------------------

// config/artisanpack/performance.php
return [

    'features' => [
        'query_optimization' => true,
    ],

    'query_optimization' => [
        'detect_n1'   => true,
        'threshold'   => 5,                 // Flag when >= 5 queries hit the same relation.
        'fail_tests'  => env( 'APP_ENV' ) === 'testing',
    ],
];

// ---------------------------------------------------------------------
// 2. Subscribe to the event.
// ---------------------------------------------------------------------

namespace App\Providers;

use ArtisanPackUI\Performance\Events\N1QueryDetected;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class N1EventProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen( function ( N1QueryDetected $event ): void {
            Log::channel( 'performance' )->warning( 'N+1 detected', [
                'model'    => $event->model,
                'relation' => $event->relation,
                'count'    => $event->count,
                'url'      => $event->url,
            ] );
        } );
    }
}

/*
 * Once enabled, the shipped RecommendationEngine will also pick up
 * repeated N+1 offenders and surface them in the dashboard's
 * Recommendations panel with the suggested `->with(...)` fix.
 */
