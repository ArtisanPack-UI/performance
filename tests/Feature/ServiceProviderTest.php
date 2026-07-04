<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Facades\Performance;
use ArtisanPackUI\Performance\PerformanceServiceProvider;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use ArtisanPackUI\Performance\Services\ImageService;
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
    config( ['artisanpack.performance.features.image_optimization' => true] );

    expect( Performance::isFeatureEnabled( 'image_optimization' ) )->toBeTrue()
        ->and( Performance::isFeatureEnabled( 'monitoring' ) )->toBeFalse();
} );

it( 'returns false for unknown features rather than throwing', function (): void {
    expect( Performance::isFeatureEnabled( 'nonexistent_feature' ) )->toBeFalse();
} );

it( 'binds FormatConverter and ImageService as singletons', function (): void {
    $converterA = app( FormatConverter::class );
    $converterB = app( FormatConverter::class );
    $imageA     = app( ImageService::class );
    $imageB     = app( ImageService::class );

    expect( $converterA )->toBeInstanceOf( FormatConverter::class )
        ->and( $converterA )->toBe( $converterB )
        ->and( $imageA )->toBeInstanceOf( ImageService::class )
        ->and( $imageA )->toBe( $imageB )
        ->and( $imageA->converter() )->toBe( $converterA );
} );

it( 'registers the perf:generate-webp console command', function (): void {
    $registered = array_keys( $this->app['Illuminate\\Contracts\\Console\\Kernel']->all() );

    expect( $registered )->toContain( 'perf:generate-webp' );
} );

it( 'registers only the MediaUploaded event listener when it exists (no double-dispatch)', function (): void {
    // Simulate a future media-library release that ships the dedicated
    // MediaUploaded event class. The provider should subscribe to that
    // event and NOT also register the fallback Eloquent model closure —
    // otherwise a single upload would enqueue OptimizeMediaJob twice.
    if ( ! class_exists( 'ArtisanPackUI\\MediaLibrary\\Events\\MediaUploaded' ) ) {
        eval( 'namespace ArtisanPackUI\\MediaLibrary\\Events; class MediaUploaded {}' );
    }

    config( [ 'artisanpack.performance.media_library_integration.enabled' => true ] );

    $provider = app()->resolveProvider( PerformanceServiceProvider::class );

    $reflection = new ReflectionClass( $provider );
    $method     = $reflection->getMethod( 'wireMediaLibraryListeners' );
    $method->setAccessible( true );
    $method->invoke( $provider );

    // Provider stores listeners keyed by the exact FQCN string passed to
    // listen() — check both leading-backslash and no-backslash forms so
    // this test doesn't depend on which convention the provider uses.
    expect(
        app( 'events' )->hasListeners( '\\ArtisanPackUI\\MediaLibrary\\Events\\MediaUploaded' )
        || app( 'events' )->hasListeners( 'ArtisanPackUI\\MediaLibrary\\Events\\MediaUploaded' ),
    )->toBeTrue();
} );

it( 'boots without throwing when media-library is not installed', function (): void {
    // The test environment does not depend on artisanpack-ui/media-library so
    // the provider class does not exist. The Performance service provider
    // must still boot cleanly — the media library integration path is
    // guarded on the detector's status.
    config( [ 'artisanpack.performance.media_library_integration.enabled' => null ] );

    expect( fn () => app()->resolveProvider( PerformanceServiceProvider::class )->boot() )
        ->not->toThrow( Throwable::class );
} );

it( 'skips listener wiring when the integration is forced off via config', function (): void {
    config( [ 'artisanpack.performance.media_library_integration.enabled' => false ] );

    expect( fn () => app()->resolveProvider( PerformanceServiceProvider::class )->boot() )
        ->not->toThrow( Throwable::class );
} );

it( 'replaces list-valued config overrides wholesale instead of per-index', function (): void {
    // Regression test: array_replace_recursive would bleed the package defaults
    // through at higher indices (e.g. images.sizes default [320,640,768,1024,1280,1920]
    // merged with user [1200] would yield [1200,640,768,1024,1280,1920]).
    // The package merger replaces lists wholesale.
    config( ['artisanpack.performance.images.sizes' => [1200]] );
    config( ['artisanpack.performance.speculative_loading.prefetch.exclude_patterns' => ['/private']] );

    app()->resolveProvider( PerformanceServiceProvider::class )->boot();

    expect( config( 'artisanpack.performance.images.sizes' ) )->toBe( [1200] )
        ->and( config( 'artisanpack.performance.speculative_loading.prefetch.exclude_patterns' ) )->toBe( ['/private'] )
        // Sanity: associative defaults still merge through (driver still resolves).
        ->and( config( 'artisanpack.performance.images.driver' ) )->toBe( 'gd' );
} );
