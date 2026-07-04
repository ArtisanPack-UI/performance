<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Facades\Performance;
use ArtisanPackUI\Performance\Speculative\PrefetchManager;
use ArtisanPackUI\Performance\Speculative\PrerenderManager;

beforeEach( function (): void {
    app()->forgetInstance( PrefetchManager::class );
    app()->forgetInstance( PrerenderManager::class );
} );

it( 'registers prefetch URLs through the Performance facade', function (): void {
    Performance::prefetch( ['/about', '/contact'] );

    expect( app( PrefetchManager::class )->all() )->toBe( ['/about', '/contact'] );
} );

it( 'registers a single prerender URL with a priority through the facade', function (): void {
    Performance::prerender( '/checkout', 'high' );

    expect( app( PrerenderManager::class )->all() )->toBe( ['/checkout'] )
        ->and( app( PrerenderManager::class )->priorityFor( '/checkout' ) )->toBe( 'high' );
} );

it( 'clears a prefetch pattern through the facade', function (): void {
    Performance::prefetch( ['/about', '/temporary/x', '/temporary/y'] );
    Performance::clearPrefetch( '/temporary/*' );

    expect( app( PrefetchManager::class )->all() )->toBe( ['/about'] );
} );

it( 'clears a prerender URL through the facade', function (): void {
    Performance::prerender( ['/checkout', '/cart'] );
    Performance::clearPrerender( '/cart' );

    expect( app( PrerenderManager::class )->all() )->toBe( ['/checkout'] );
} );
