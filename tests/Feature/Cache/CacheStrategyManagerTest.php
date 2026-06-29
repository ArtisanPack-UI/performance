<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\CacheStrategyManager;
use ArtisanPackUI\Performance\Cache\Strategies\FileCacheStrategy;
use ArtisanPackUI\Performance\Cache\Strategies\MemcachedCacheStrategy;
use ArtisanPackUI\Performance\Cache\Strategies\RedisCacheStrategy;
use ArtisanPackUI\Performance\Contracts\CacheStrategy;

it( 'resolves built-in drivers by name', function (): void {
    $manager = new CacheStrategyManager;

    expect( $manager->driver( 'file' ) )->toBeInstanceOf( FileCacheStrategy::class );
    expect( $manager->driver( 'redis' ) )->toBeInstanceOf( RedisCacheStrategy::class );
    expect( $manager->driver( 'memcached' ) )->toBeInstanceOf( MemcachedCacheStrategy::class );
} );

it( 'memoizes resolved drivers', function (): void {
    $manager = new CacheStrategyManager;

    $first  = $manager->driver( 'file' );
    $second = $manager->driver( 'file' );

    expect( $first )->toBe( $second );
} );

it( 'falls back to the configured page-cache driver when no name is supplied', function (): void {
    config( [ 'artisanpack.performance.page_cache.driver' => 'redis' ] );

    expect( ( new CacheStrategyManager )->driver() )->toBeInstanceOf( RedisCacheStrategy::class );
} );

it( 'falls back to file when no default driver is configured', function (): void {
    config( [ 'artisanpack.performance.page_cache.driver' => '' ] );

    expect( ( new CacheStrategyManager )->driver() )->toBeInstanceOf( FileCacheStrategy::class );
} );

it( 'allows extending the manager with custom drivers', function (): void {
    $manager = new CacheStrategyManager;
    $custom  = new class extends FileCacheStrategy {
    };

    $manager->extend( 'custom', static fn (): CacheStrategy => $custom );

    expect( $manager->hasDriver( 'custom' ) )->toBeTrue();
    expect( $manager->driver( 'custom' ) )->toBe( $custom );
} );

it( 'throws when asked for an unknown driver', function (): void {
    ( new CacheStrategyManager )->driver( 'mystery' );
} )->throws( InvalidArgumentException::class );

it( 'hasDriver returns false for built-in names that have no matching cache.stores config', function (): void {
    // Strip the redis store from the test app so the probe sees no
    // configured driver. Without the fix, hasDriver('redis') would
    // still return true and callers would crash on first ->get().
    $stores = (array) config( 'cache.stores', [] );
    unset( $stores['redis'] );
    config( [ 'cache.stores' => $stores ] );

    $manager = new CacheStrategyManager;

    expect( $manager->hasDriver( 'redis' ) )->toBeFalse();
} );

it( 'hasDriver returns true for built-in names when the matching cache.stores entry exists', function (): void {
    config( [ 'cache.stores.file' => [ 'driver' => 'array', 'serialize' => false ] ] );

    $manager = new CacheStrategyManager;

    expect( $manager->hasDriver( 'file' ) )->toBeTrue();
} );
