<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Speculative\PrerenderManager;

it( 'registers a single URL', function (): void {
    $manager = new PrerenderManager( limit: 5 );
    $manager->register( '/checkout' );

    expect( $manager->all() )->toBe( ['/checkout'] );
} );

it( 'enforces the configured limit when listing URLs', function (): void {
    $manager = new PrerenderManager( limit: 2 );
    $manager->register( ['/a', '/b', '/c', '/d'] );

    expect( $manager->all() )->toBe( ['/a', '/b'] )
        ->and( $manager->count() )->toBe( 4 );
} );

it( 'prefers higher-priority URLs when truncating to the limit', function (): void {
    $manager = new PrerenderManager( limit: 2 );
    $manager->register( '/checkout', 'low' );
    $manager->register( '/checkout-high', 'high' );
    $manager->register( '/checkout-medium', 'medium' );

    expect( $manager->all() )->toBe( ['/checkout-high', '/checkout-medium'] );
} );

it( 'reads the limit from the package config when no override is supplied', function (): void {
    config( ['artisanpack.performance.speculative_loading.prerender.limit' => 3] );

    $manager = new PrerenderManager;

    expect( $manager->limit() )->toBe( 3 );
} );

it( 'falls back to the default limit when config is missing', function (): void {
    config( ['artisanpack.performance.speculative_loading' => []] );

    $manager = new PrerenderManager;

    expect( $manager->limit() )->toBe( PrerenderManager::DEFAULT_LIMIT );
} );

it( 'treats limit 0 as an explicit suppression of prerendering', function (): void {
    $manager = new PrerenderManager( limit: 0 );
    $manager->register( ['/a', '/b', '/c'] );

    expect( $manager->all() )->toBe( [] );
} );

it( 'disables truncation for a negative limit', function (): void {
    $manager = new PrerenderManager( limit: -1 );
    $manager->register( ['/a', '/b', '/c'] );

    expect( $manager->all() )->toBe( ['/a', '/b', '/c'] );
} );

it( 'removes prerendered URLs that match a glob pattern', function (): void {
    $manager = new PrerenderManager( limit: 10 );
    $manager->register( ['/checkout/step-1', '/checkout/step-2', '/about'] );
    $manager->clear( '/checkout/*' );

    expect( $manager->all() )->toBe( ['/about'] );
} );

it( 'flushes every registered URL', function (): void {
    $manager = new PrerenderManager( limit: 10 );
    $manager->register( ['/a', '/b'] );
    $manager->flush();

    expect( $manager->all() )->toBe( [] )
        ->and( $manager->hasUrls() )->toBeFalse();
} );
