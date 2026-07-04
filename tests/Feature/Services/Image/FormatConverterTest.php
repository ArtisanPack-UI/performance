<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Services\Image\FormatConverter;

beforeEach( function (): void {
    clearImageFixtures();
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'reports supported formats based on the GD driver capabilities', function (): void {
    $converter = new FormatConverter( 'gd' );

    expect( $converter->supports( 'webp' ) )->toBe( function_exists( 'imagewebp' ) )
        ->and( $converter->supports( 'avif' ) )->toBe( function_exists( 'imageavif' ) )
        ->and( $converter->supports( 'unknown' ) )->toBeFalse();
} );

it( 'reports supported formats based on the Imagick driver capabilities', function (): void {
    if ( ! class_exists( Imagick::class ) ) {
        $this->markTestSkipped( 'Imagick extension is not installed' );
    }

    $converter = new FormatConverter( 'imagick' );
    $queryWebp = (new Imagick)->queryFormats( 'WEBP' );
    $queryAvif = (new Imagick)->queryFormats( 'AVIF' );

    expect( $converter->supports( 'webp' ) )->toBe( ! empty( $queryWebp ) )
        ->and( $converter->supports( 'avif' ) )->toBe( ! empty( $queryAvif ) );
} );

it( 'converts a JPEG to WebP using the GD driver', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source    = makeTestImage( 'source.jpg', 80, 80, 'jpeg' );
    $converter = new FormatConverter( 'gd' );

    $destination = $converter->toWebp( $source, 75 );

    expect( $destination )->toEndWith( '.webp' )
        ->and( file_exists( $destination ) )->toBeTrue()
        ->and( getimagesize( $destination )[2] )->toBe( IMAGETYPE_WEBP );
} );

it( 'converts a PNG to WebP and preserves transparency', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source    = makeTestImage( 'transparent.png', 40, 40, 'png' );
    $converter = new FormatConverter( 'gd' );

    $destination = $converter->toWebp( $source );

    expect( file_exists( $destination ) )->toBeTrue()
        ->and( getimagesize( $destination )[2] )->toBe( IMAGETYPE_WEBP );
} );

it( 'converts a JPEG to AVIF using Imagick when available', function (): void {
    if ( ! class_exists( Imagick::class ) ) {
        $this->markTestSkipped( 'Imagick is not installed' );
    }

    $converter = new FormatConverter( 'imagick' );

    if ( ! $converter->supports( 'avif' ) ) {
        $this->markTestSkipped( 'Imagick AVIF support not available' );
    }

    $source      = makeTestImage( 'avif-source.jpg', 60, 60, 'jpeg' );
    $destination = $converter->toAvif( $source, 60 );

    expect( $destination )->toEndWith( '.avif' )
        ->and( file_exists( $destination ) )->toBeTrue();
} );

it( 'throws when the source image cannot be read', function (): void {
    $converter = new FormatConverter( 'gd' );

    expect( fn () => $converter->toWebp( '/does/not/exist.jpg' ) )
        ->toThrow( RuntimeException::class, 'Source image is not readable' );
} );

it( 'throws on an unsupported target format', function (): void {
    $source    = makeTestImage( 'unsupported.jpg' );
    $converter = new FormatConverter( 'gd' );

    expect( fn () => $converter->convert( $source, 'bmp', 80 ) )
        ->toThrow( RuntimeException::class, 'Unsupported target format' );
} );

it( 'throws when the active driver cannot encode the requested format', function (): void {
    // Force a synthetic driver name so supports() returns false for everything.
    $source    = makeTestImage( 'unencodable.jpg' );
    $converter = new FormatConverter( 'nonexistent' );

    expect( fn () => $converter->toWebp( $source ) )
        ->toThrow( RuntimeException::class, "Driver 'nonexistent' cannot encode webp" );
} );

it( 'clamps quality values to the 0-100 range', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source    = makeTestImage( 'clamp.jpg' );
    $converter = new FormatConverter( 'gd' );

    // Should not throw, regardless of out-of-range quality.
    $destination = $converter->convert( $source, 'webp', 250 );

    expect( file_exists( $destination ) )->toBeTrue();

    $destination = $converter->convert( $source, 'webp', -10 );

    expect( file_exists( $destination ) )->toBeTrue();
} );

it( 'defaults the driver to the configured value', function (): void {
    config( ['artisanpack.performance.images.driver' => 'imagick'] );

    $converter = new FormatConverter;

    expect( $converter->driver() )->toBe( 'imagick' );
} );

it( 'reads the driver from config on every call so runtime swaps take effect', function (): void {
    // Regression: previously the driver was captured in the constructor, so a
    // singleton-bound converter would silently ignore subsequent config swaps.
    config( ['artisanpack.performance.images.driver' => 'gd'] );
    $converter = new FormatConverter;
    expect( $converter->driver() )->toBe( 'gd' );

    config( ['artisanpack.performance.images.driver' => 'imagick'] );
    expect( $converter->driver() )->toBe( 'imagick' );
} );

it( 'pins the driver when constructed with an explicit override', function (): void {
    $converter = new FormatConverter( 'imagick' );

    config( ['artisanpack.performance.images.driver' => 'gd'] );

    expect( $converter->driver() )->toBe( 'imagick' );
} );

it( 'reports usesImagick only when the driver is imagick and the extension is loaded', function (): void {
    $gd      = new FormatConverter( 'gd' );
    $imagick = new FormatConverter( 'imagick' );

    expect( $gd->usesImagick() )->toBeFalse()
        ->and( $imagick->usesImagick() )->toBe( class_exists( Imagick::class));
});
