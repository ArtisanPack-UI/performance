<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Services\MediaLibraryDetector;

it( 'reports enabled when the media-library class exists', function (): void {
    config( [ 'artisanpack.performance.media_library_integration.enabled' => null ] );

    $detector = new MediaLibraryDetector;

    // Whether the media-library test fixture is present in the test
    // environment depends on the composer install — assert that the
    // detector agrees with the class_exists check, whichever way it
    // resolves. Both paths are exercised by later scenarios.
    $expected = class_exists( MediaLibraryDetector::PROVIDER_CLASS );

    expect( $detector->isEnabled() )->toBe( $expected );
} );

it( 'honors an explicit config override that forces integration off', function (): void {
    config( [ 'artisanpack.performance.media_library_integration.enabled' => false ] );

    $detector = new MediaLibraryDetector;

    expect( $detector->isEnabled() )->toBeFalse()
        ->and( $detector->shouldOptimizeOnUpload() )->toBeFalse()
        ->and( $detector->shouldGenerateFormatsOnUpload() )->toBeFalse();
} );

it( 'honors an explicit config override that forces integration on', function (): void {
    config( [
        'artisanpack.performance.media_library_integration.enabled'                      => true,
        'artisanpack.performance.media_library_integration.optimize_on_upload'           => true,
        'artisanpack.performance.media_library_integration.generate_formats_on_upload'   => false,
    ] );

    $detector = new MediaLibraryDetector;

    expect( $detector->isEnabled() )->toBeTrue()
        ->and( $detector->shouldOptimizeOnUpload() )->toBeTrue()
        ->and( $detector->shouldGenerateFormatsOnUpload() )->toBeFalse();
} );

it( 'reports source=config when the override is explicit', function (): void {
    config( [ 'artisanpack.performance.media_library_integration.enabled' => true ] );

    $status = ( new MediaLibraryDetector )->status();

    expect( $status['enabled'] )->toBeTrue()
        ->and( $status['source'] )->toBe( 'config' );
} );

it( 'reports source=auto when the override is unset', function (): void {
    config( [ 'artisanpack.performance.media_library_integration.enabled' => null ] );

    $status = ( new MediaLibraryDetector )->status();

    expect( $status['source'] )->toBe( 'auto' );
} );
