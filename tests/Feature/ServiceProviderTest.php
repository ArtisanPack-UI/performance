<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Facades\Performance;
use ArtisanPackUI\Performance\Services\PerformanceService;

it( 'merges package configuration under the artisanpack.performance namespace', function (): void {
	expect( config( 'artisanpack.performance' ) )
		->toBeArray()
		->and( config( 'artisanpack.performance.features' ) )->toBeArray()
		->and( config( 'artisanpack.performance.features.image_optimization' ) )->toBeFalse()
		->and( config( 'artisanpack.performance.features.monitoring' ) )->toBeFalse()
		->and( config( 'artisanpack.performance.images.driver' ) )->toBe( 'gd' )
		->and( config( 'artisanpack.performance.dashboard.route_prefix' ) )->toBe( 'admin/performance' );
} );

it( 'binds the PerformanceService singleton on the performance key', function (): void {
	$first  = app( 'performance' );
	$second = app( 'performance' );

	expect( $first )
		->toBeInstanceOf( PerformanceService::class )
		->and( $first )->toBe( $second );
} );

it( 'resolves the Performance facade to the bound service', function (): void {
	expect( Performance::getFacadeRoot() )
		->toBeInstanceOf( PerformanceService::class );
} );

it( 'lets user overrides take precedence over package defaults', function (): void {
	config( [ 'artisanpack.performance.features.image_optimization' => true ] );

	expect( Performance::isFeatureEnabled( 'image_optimization' ) )->toBeTrue()
		->and( Performance::isFeatureEnabled( 'monitoring' ) )->toBeFalse();
} );

it( 'returns false for unknown features rather than throwing', function (): void {
	expect( Performance::isFeatureEnabled( 'nonexistent_feature' ) )->toBeFalse();
} );

it( 'replaces list-valued config overrides wholesale instead of per-index', function (): void {
	// Regression test: array_replace_recursive would bleed the package defaults
	// through at higher indices (e.g. images.sizes default [320,640,768,1024,1280,1920]
	// merged with user [1200] would yield [1200,640,768,1024,1280,1920]).
	// The package merger replaces lists wholesale.
	config( [ 'artisanpack.performance.images.sizes' => [ 1200 ] ] );
	config( [ 'artisanpack.performance.speculative_loading.prefetch.exclude_patterns' => [ '/private' ] ] );

	app()->resolveProvider( ArtisanPackUI\Performance\PerformanceServiceProvider::class )->boot();

	expect( config( 'artisanpack.performance.images.sizes' ) )->toBe( [ 1200 ] )
		->and( config( 'artisanpack.performance.speculative_loading.prefetch.exclude_patterns' ) )->toBe( [ '/private' ] )
		// Sanity: associative defaults still merge through (driver still resolves).
		->and( config( 'artisanpack.performance.images.driver' ) )->toBe( 'gd' );
} );
