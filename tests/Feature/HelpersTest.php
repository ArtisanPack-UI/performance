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

it( 'exposes the performance helper that resolves the service from the container', function (): void {
	expect( performance() )->toBeInstanceOf( PerformanceService::class )
		->and( performance() )->toBe( app( 'performance' ) );
} );

it( 'reports feature flags through perfFeatureEnabled', function (): void {
	config( [ 'artisanpack.performance.features.image_optimization' => true ] );

	expect( perfFeatureEnabled( 'image_optimization' ) )->toBeTrue()
		->and( perfFeatureEnabled( 'nonexistent' ) )->toBeFalse();
} );

it( 'returns the source path from perfConvertToWebP when the active driver cannot encode WebP', function (): void {
	// Regression: previously threw RuntimeException on encoder-less hosts,
	// which would 500 any Blade template using {{ perfConvertToWebP($path) }}.
	$source = makeTestImage( 'helper-webp-fallback.jpg' );

	// Swap in an ImageService whose converter reports no WebP support.
	$converter = new class( 'gd' ) extends ArtisanPackUI\Performance\Services\Image\FormatConverter {
		public function supports( string $format ): bool
		{
			return false;
		}
	};

	app()->instance( ImageService::class, new ImageService( $converter ) );
	app()->instance( 'performance', new PerformanceService( app( ImageService::class ) ) );

	expect( perfConvertToWebP( $source ) )->toBe( $source );
} );

it( 'returns the source path from perfConvertToAvif when the active driver cannot encode AVIF', function (): void {
	$source = makeTestImage( 'helper-avif-fallback.jpg' );

	$converter = new class( 'gd' ) extends ArtisanPackUI\Performance\Services\Image\FormatConverter {
		public function supports( string $format ): bool
		{
			return false;
		}
	};

	app()->instance( ImageService::class, new ImageService( $converter ) );
	app()->instance( 'performance', new PerformanceService( app( ImageService::class ) ) );

	expect( perfConvertToAvif( $source ) )->toBe( $source );
} );

it( 'delegates image helpers to the PerformanceService', function (): void {
	if ( ! function_exists( 'imagewebp' ) ) {
		$this->markTestSkipped( 'GD WebP support is not available' );
	}

	$source = makeTestImage( 'helper.jpg', 80, 80 );

	$webp = perfConvertToWebP( $source, 80 );
	expect( file_exists( $webp ) )->toBeTrue();

	$color = perfGetDominantColor( $source );
	expect( $color )->toMatch( '/^#[0-9a-f]{6}$/' );

	$srcset = perfGetResponsiveSrcset( $source, [ 40 ] );
	expect( $srcset )->toContain( '40w' );
} );

it( 'runs the optimization pipeline via perfOptimizeImage', function (): void {
	if ( ! function_exists( 'imagewebp' ) ) {
		$this->markTestSkipped( 'GD WebP support is not available' );
	}

	$source = makeTestImage( 'pipeline-helper.jpg', 300, 150 );

	$result = perfOptimizeImage( $source, [
		'sizes'   => [ 75 ],
		'formats' => [ 'webp' ],
	] );

	expect( $result['variants'] )->toHaveCount( 1 )
		->and( $result['variants'][0]['format'] )->toBe( 'webp' );
} );

it( 'stores and retrieves values through perfRemember', function (): void {
	config( [ 'artisanpack.performance.fragment_cache.driver' => config( 'cache.default' ) ] );

	$first = perfRemember( 'helpers:value', 60, fn () => 'computed' );
	expect( $first )->toBe( 'computed' );

	$second = perfRemember( 'helpers:value', 60, fn () => 'recomputed' );
	expect( $second )->toBe( 'computed' );
} );

it( 'stores values indefinitely through perfRememberForever', function (): void {
	config( [ 'artisanpack.performance.fragment_cache.driver' => config( 'cache.default' ) ] );

	$first = perfRememberForever( 'helpers:forever', fn () => 'persisted' );
	expect( $first )->toBe( 'persisted' );

	$second = perfRememberForever( 'helpers:forever', fn () => 'changed' );
	expect( $second )->toBe( 'persisted' );
} );

it( 'invalidates the namespaced key via perfInvalidateCache', function (): void {
	config( [ 'artisanpack.performance.fragment_cache.driver' => config( 'cache.default' ) ] );

	perfRemember( 'helpers:invalidate', 60, fn () => 'cached' );

	expect( perfInvalidateCache( 'helpers:invalidate' ) )->toBeTrue()
		->and( cache()->store( config( 'cache.default' ) )->get( 'performance:helpers:invalidate' ) )->toBeNull();
} );

it( 'flushes the entire store via perfFlushCache when a dedicated store is configured', function (): void {
	config( [
		'cache.stores.perf_helpers'                       => [ 'driver' => 'array' ],
		'artisanpack.performance.fragment_cache.driver'   => 'perf_helpers',
	] );

	perfRemember( 'helpers:flush:a', 60, fn () => 'a' );
	perfRemember( 'helpers:flush:b', 60, fn () => 'b' );

	expect( perfFlushCache() )->toBeTrue()
		->and( cache()->store( 'perf_helpers' )->get( 'performance:helpers:flush:a' ) )->toBeNull()
		->and( cache()->store( 'perf_helpers' )->get( 'performance:helpers:flush:b' ) )->toBeNull();
} );

it( 'records metrics without throwing via perfRecordMetric', function (): void {
	perfRecordMetric( 'LCP', 1234.5, [ 'route' => 'home' ] );

	expect( true )->toBeTrue();
} );

it( 'returns recommendations via perfGetRecommendations', function (): void {
	expect( perfGetRecommendations() )->toBe( [] );
} );

it( 'has every documented perf helper available', function (): void {
	$expected = [
		'perfFeatureEnabled',
		'perfOptimizeImage',
		'perfConvertToWebP',
		'perfConvertToAvif',
		'perfGetDominantColor',
		'perfGetResponsiveSrcset',
		'perfRemember',
		'perfRememberForever',
		'perfInvalidateCache',
		'perfFlushCache',
		'perfRecordMetric',
		'perfGetRecommendations',
	];

	foreach ( $expected as $function ) {
		expect( function_exists( $function ) )->toBeTrue( "Expected helper {$function} to exist" );
	}
} );

it( 'resolves perfOptimizeImage and the facade to the same ImageService instance', function (): void {
	// Regression: helpers must route through the container so swapping ImageService
	// in tests (or in app code) takes effect for all helper calls.
	expect( performance()->images() )->toBeInstanceOf( ImageService::class )
		->and( performance()->images() )->toBe( app( ImageService::class ) );
} );
