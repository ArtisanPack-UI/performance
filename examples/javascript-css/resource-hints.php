<?php

/**
 * Resource hints — preload, preconnect, prefetch, dns-prefetch.
 *
 * The Performance package's ResourceHintInjector accepts hints from
 * two sources: config-declared hints (always emitted) and
 * request-scoped hints registered from a middleware / controller /
 * view composer via the Performance facade.
 *
 * When the `early_hints` feature is on the same hints are also emitted
 * as an HTTP 103 Early Hints interim response so upstream
 * intermediaries can begin fetching before the app has rendered.
 */

namespace App\Providers;

use ArtisanPackUI\Performance\Facades\Performance;
use Illuminate\Support\ServiceProvider;

class ResourceHintServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 1. Static hints from config — apply to every response.
        config( [
            'artisanpack.performance.resource_hints.manual_hints' => [
                [ 'rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com' ],
                [ 'rel' => 'preconnect', 'href' => 'https://cdn.example.com', 'crossorigin' => 'anonymous' ],
                [ 'rel' => 'dns-prefetch', 'href' => 'https://api.example.com' ],
            ],
        ] );

        // 2. Route-scoped hints — register from a view composer so the
        //    checkout page preloads its own bundle.
        view()->composer( 'checkout', function (): void {
            Performance::hint( 'preload', asset( 'js/checkout.js' ), [ 'as' => 'script' ] );
            Performance::hint( 'preload', asset( 'css/checkout.css' ), [ 'as' => 'style' ] );
            Performance::hint( 'prefetch', route( 'checkout.confirm' ) );
        } );
    }
}
