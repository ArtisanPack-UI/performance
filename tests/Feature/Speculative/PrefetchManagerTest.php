<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Speculative\PrefetchManager;

it( 'registers a single URL', function (): void {
    $manager = new PrefetchManager;
    $manager->register( '/about' );

    expect( $manager->all() )->toBe( ['/about'] )
        ->and( $manager->hasUrls() )->toBeTrue()
        ->and( $manager->count() )->toBe( 1 );
} );

it( 'registers a list of URLs in one call', function (): void {
    $manager = new PrefetchManager;
    $manager->register( ['/about', '/contact', '/pricing'] );

    expect( $manager->all() )->toBe( ['/about', '/contact', '/pricing'] );
} );

it( 'sorts URLs by priority weight and falls back to insertion order on ties', function (): void {
    $manager = new PrefetchManager;
    $manager->register( '/medium', 'medium' );
    $manager->register( '/high', 'high' );
    $manager->register( '/medium-second', 'medium' );
    $manager->register( '/low', 'low' );

    expect( $manager->all() )->toBe( ['/high', '/medium', '/medium-second', '/low'] );
} );

it( 'replaces an existing entry when re-registered at a new priority', function (): void {
    $manager = new PrefetchManager;
    $manager->register( '/products/popular', 'low' );
    $manager->register( '/products/popular', 'high' );

    expect( $manager->all() )->toBe( ['/products/popular'] )
        ->and( $manager->priorityFor( '/products/popular' ) )->toBe( 'high' );
} );

it( 'defaults to medium priority for unknown values', function (): void {
    $manager = new PrefetchManager;
    $manager->register( '/about', 'absurd' );

    expect( $manager->priorityFor( '/about' ) )->toBe( PrefetchManager::DEFAULT_PRIORITY );
} );

it( 'removes URLs that match exact patterns via clear()', function (): void {
    $manager = new PrefetchManager;
    $manager->register( ['/about', '/contact'] );
    $manager->clear( '/about' );

    expect( $manager->all() )->toBe( ['/contact'] );
} );

it( 'removes URLs that match glob patterns via clear()', function (): void {
    $manager = new PrefetchManager;
    $manager->register( ['/temporary/promo-1', '/temporary/promo-2', '/permanent/home'] );
    $manager->clear( '/temporary/*' );

    expect( $manager->all() )->toBe( ['/permanent/home'] );
} );

it( 'ignores empty URLs at registration', function (): void {
    $manager = new PrefetchManager;
    $manager->register( ['', '   ']);

    expect( $manager->hasUrls())->toBeFalse();
});
