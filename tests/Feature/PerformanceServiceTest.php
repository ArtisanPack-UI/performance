<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;
use ArtisanPackUI\Performance\Services\ImageService;
use ArtisanPackUI\Performance\Services\PerformanceService;

beforeEach( function (): void {
    clearImageFixtures();
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'reports unknown features as disabled', function (): void {
    expect( app( PerformanceService::class )->isFeatureEnabled( 'missing' ) )->toBeFalse();
} );

it( 'reports configured features as enabled', function (): void {
    config( ['artisanpack.performance.features.page_cache' => true] );

    expect( app( PerformanceService::class )->isFeatureEnabled( 'page_cache' ) )->toBeTrue();
} );

it( 'returns the same instance for the helper, facade root, and container binding', function (): void {
    $helper    = performance();
    $container = app( 'performance' );
    $typed     = app( PerformanceService::class );

    expect( $helper )->toBe( $container )
        ->and( $helper )->toBe( $typed );
} );

it( 'exposes the bound ImageService via the images() accessor', function (): void {
    expect( app( PerformanceService::class )->images() )
        ->toBeInstanceOf( ImageService::class )
        ->toBe( app( ImageService::class ) );
} );

it( 'remembers values via the configured fragment-cache store under the performance namespace', function (): void {
    // Force the fragment-cache driver to the framework default so the test
    // stays independent of host config (CACHE_STORE in phpunit.xml).
    config( ['artisanpack.performance.fragment_cache.driver' => config( 'cache.default' )] );

    $value = app( PerformanceService::class )->remember( 'unit-test', 60, fn () => 'computed' );

    expect( $value )->toBe( 'computed' )
        ->and( cache()->store( config( 'cache.default' ) )->get( 'performance:unit-test' ) )->toBe( 'computed' );
} );

it( 'remembers values forever via rememberForever', function (): void {
    config( ['artisanpack.performance.fragment_cache.driver' => config( 'cache.default' )] );

    $first  = app( PerformanceService::class )->rememberForever( 'forever-key', fn () => 'persisted' );
    $second = app( PerformanceService::class )->rememberForever( 'forever-key', fn () => 'changed' );

    expect( $first )->toBe( 'persisted' )
        ->and( $second )->toBe( 'persisted' );
} );

it( 'forgets the namespaced key via invalidateCache', function (): void {
    config( ['artisanpack.performance.fragment_cache.driver' => config( 'cache.default' )] );

    $service = app( PerformanceService::class );
    $service->remember( 'invalidate-me', 60, fn () => 'cached' );

    expect( $service->invalidateCache( 'invalidate-me' ) )->toBeTrue()
        ->and( cache()->store( config( 'cache.default' ) )->get( 'performance:invalidate-me' ) )->toBeNull();
} );

it( 'flushes the store via flushCache when a dedicated store is configured', function (): void {
    // Register a dedicated store so the safeguard lets the flush through.
    config( [
        'cache.stores.perf_test'                        => ['driver' => 'array'],
        'artisanpack.performance.fragment_cache.driver' => 'perf_test',
    ] );

    $service = app( PerformanceService::class );
    $service->remember( 'flush:a', 60, fn () => 'a' );
    $service->remember( 'flush:b', 60, fn () => 'b' );

    expect( $service->flushCache() )->toBeTrue()
        ->and( cache()->store( 'perf_test' )->get( 'performance:flush:a' ) )->toBeNull();
} );

it( 'refuses to flushCache when the fragment driver matches the framework default store', function (): void {
    // Regression: flush() is store-wide; if the package's fragment store is also
    // the framework default, flushCache would wipe sessions/locks/app data.
    config( ['artisanpack.performance.fragment_cache.driver' => config( 'cache.default' )] );

    expect( fn () => app( PerformanceService::class )->flushCache() )
        ->toThrow( RuntimeException::class, 'Refusing to flush the framework default cache store' );
} );

it( 'refuses to flushCache when the fragment driver is null', function (): void {
    config( ['artisanpack.performance.fragment_cache.driver' => null] );

    expect( fn () => app( PerformanceService::class )->flushCache() )
        ->toThrow( RuntimeException::class, 'Refusing to flush the framework default cache store' );
} );

it( 'delegates image methods to the ImageService', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source  = makeTestImage( 'delegate.jpg', 200, 100 );
    $service = app( PerformanceService::class );

    $webp = $service->convertToWebP( $source, 70 );
    expect( file_exists( $webp ) )->toBeTrue();

    $color = $service->getDominantColor( $source );
    expect( $color )->toMatch( '/^#[0-9a-f]{6}$/' );

    $srcset = $service->getResponsiveSrcset( $source, [100] );
    expect( $srcset )->toContain( '100w' );
} );

it( 'returns no-op defaults for metric and recommendation methods', function (): void {
    $service = app( PerformanceService::class );

    expect( $service->getRecommendations() )->toBe( [] );

    // recordMetric is a no-op; just assert it returns void without throwing.
    $service->recordMetric( 'LCP', 1234.5 );
    expect( true )->toBeTrue();
});

it( 'returns a ScriptRegistration from the script() facade entry point', function (): void {
    $service = app( PerformanceService::class);

    $registration = $service->script( '/js/app.js');

    expect( $registration)->toBeInstanceOf( ScriptRegistration::class)
        ->and( $registration->src)->toBe( '/js/app.js')
        ->and( $service->getScripts())->toContain( $registration);
});
