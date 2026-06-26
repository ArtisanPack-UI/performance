<?php

declare( strict_types=1 );

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
	config( [ 'artisanpack.performance.features.page_cache' => true ] );

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
	config( [ 'artisanpack.performance.fragment_cache.driver' => config( 'cache.default' ) ] );

	$value = app( PerformanceService::class )->remember( 'unit-test', 60, fn () => 'computed' );

	expect( $value )->toBe( 'computed' )
		->and( cache()->store( config( 'cache.default' ) )->get( 'performance:unit-test' ) )->toBe( 'computed' );
} );

it( 'remembers values forever via rememberForever', function (): void {
	config( [ 'artisanpack.performance.fragment_cache.driver' => config( 'cache.default' ) ] );

	$first  = app( PerformanceService::class )->rememberForever( 'forever-key', fn () => 'persisted' );
	$second = app( PerformanceService::class )->rememberForever( 'forever-key', fn () => 'changed' );

	expect( $first )->toBe( 'persisted' )
		->and( $second )->toBe( 'persisted' );
} );

it( 'forgets the namespaced key via invalidateCache', function (): void {
	config( [ 'artisanpack.performance.fragment_cache.driver' => config( 'cache.default' ) ] );

	$service = app( PerformanceService::class );
	$service->remember( 'invalidate-me', 60, fn () => 'cached' );

	expect( $service->invalidateCache( 'invalidate-me' ) )->toBeTrue()
		->and( cache()->store( config( 'cache.default' ) )->get( 'performance:invalidate-me' ) )->toBeNull();
} );

it( 'flushes the store via flushCache', function (): void {
	config( [ 'artisanpack.performance.fragment_cache.driver' => config( 'cache.default' ) ] );

	$service = app( PerformanceService::class );
	$service->remember( 'flush:a', 60, fn () => 'a' );
	$service->remember( 'flush:b', 60, fn () => 'b' );

	expect( $service->flushCache() )->toBeTrue()
		->and( cache()->store( config( 'cache.default' ) )->get( 'performance:flush:a' ) )->toBeNull();
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

	$srcset = $service->getResponsiveSrcset( $source, [ 100 ] );
	expect( $srcset )->toContain( '100w' );
} );

it( 'returns no-op defaults for script and metric and recommendation methods', function (): void {
	$service = app( PerformanceService::class );

	expect( $service->script( '/js/app.js' ) )->toBe( [] )
		->and( $service->getRecommendations() )->toBe( [] );

	// recordMetric is a no-op; just assert it returns void without throwing.
	$service->recordMetric( 'LCP', 1234.5 );
	expect( true )->toBeTrue();
} );
