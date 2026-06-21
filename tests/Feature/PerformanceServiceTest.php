<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Services\PerformanceService;

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

it( 'remembers values via the configured fragment-cache store under the performance namespace', function (): void {
	// Force the fragment-cache driver to the framework default so the test
	// stays independent of host config (CACHE_STORE in phpunit.xml).
	config( [ 'artisanpack.performance.fragment_cache.driver' => config( 'cache.default' ) ] );

	$value = app( PerformanceService::class )->remember( 'unit-test', 60, fn () => 'computed' );

	expect( $value )->toBe( 'computed' )
		->and( cache()->store( config( 'cache.default' ) )->get( 'performance:unit-test' ) )->toBe( 'computed' );
} );

it( 'returns no-op defaults for stubbed image and metric methods', function (): void {
	$service = app( PerformanceService::class );

	expect( $service->optimizeImage( '/img.jpg' ) )->toBe( [] )
		->and( $service->convertToWebP( '/img.jpg' ) )->toBe( '/img.jpg' )
		->and( $service->convertToAvif( '/img.jpg' ) )->toBe( '/img.jpg' )
		->and( $service->script( '/js/app.js' ) )->toBe( [] );

	// recordMetric is a no-op; just assert it returns void without throwing.
	$service->recordMetric( 'LCP', 1234.5 );
	expect( true )->toBeTrue();
} );
