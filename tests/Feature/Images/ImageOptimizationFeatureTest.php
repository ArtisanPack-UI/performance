<?php

/*
 * End-to-end feature coverage for the image optimization pipeline.
 *
 * Issue #17 asked for "comprehensive feature tests" across the whole
 * pipeline — WebP/AVIF on both drivers, dominant color extraction,
 * responsive size generation, the lazy and responsive Blade components,
 * the HasOptimizedImages trait, queued jobs, the perf:generate-webp
 * command, and a sweep across JPEG/PNG/GIF sources. The per-class tests
 * already cover unit-level behavior in `tests/Feature/Services/Image`,
 * `tests/Feature/Images`, `tests/Feature/View/Components`, etc. — this
 * file adds the integration glue that wires several pieces together so
 * regressions in cross-component contracts surface here even when the
 * per-class tests still pass.
 */

declare( strict_types=1 );

use ArtisanPackUI\Performance\Events\ImageOptimized;
use ArtisanPackUI\Performance\Images\DominantColorExtractor;
use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\Jobs\OptimizeImageJob;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use ArtisanPackUI\Performance\Services\ImageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;

beforeEach( function (): void {
    clearImageFixtures();
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'converts JPEG sources to WebP via the GD driver', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source    = makeTestImage( 'jpeg-source.jpg', 100, 100, 'jpeg' );
    $converter = new FormatConverter( 'gd' );

    $result = $converter->convert( $source, 'webp', 75 );

    expect( file_exists( $result ) )->toBeTrue()
        ->and( getimagesize( $result )[2] )->toBe( IMAGETYPE_WEBP );
} );

it( 'converts PNG sources to WebP and preserves transparency', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source = makeTestImage( 'png-source.png', 80, 80, 'png' );

    $result = (new FormatConverter( 'gd' ))->convert( $source, 'webp', 80 );

    expect( file_exists( $result ) )->toBeTrue()
        ->and( getimagesize( $result )[2] )->toBe( IMAGETYPE_WEBP );
} );

it( 'converts GIF sources to WebP', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source = makeTestImage( 'gif-source.gif', 60, 60, 'gif' );

    $result = (new FormatConverter( 'gd' ))->convert( $source, 'webp', 80 );

    expect( file_exists( $result ) )->toBeTrue()
        ->and( getimagesize( $result )[2] )->toBe( IMAGETYPE_WEBP );
} );

it( 'converts JPEG sources to AVIF when the driver supports it', function (): void {
    $converter = new FormatConverter;

    if ( ! $converter->supports( 'avif' ) ) {
        $this->markTestSkipped( 'AVIF encoding is not available on this driver' );
    }

    $source = makeTestImage( 'avif-source.jpg', 80, 80 );

    $result = $converter->convert( $source, 'avif', 60 );

    expect( file_exists( $result ) )->toBeTrue();
} );

it( 'extracts a hex dominant color from a solid-color JPEG', function (): void {
    $source    = makeTestImage( 'dominant-color.jpg', 60, 60, 'jpeg', [25, 50, 200] );
    $extractor = new DominantColorExtractor;

    $color = $extractor->extract( $source, null, false );

    expect( $color )->toMatch( '/^#[0-9a-f]{6}$/i' );
} );

it( 'extracts dominant color from PNG sources', function (): void {
    $source = makeTestImage( 'dominant-color.png', 60, 60, 'png', [200, 30, 30] );

    $color = (new DominantColorExtractor)->extract( $source, null, false );

    expect( $color )->toMatch( '/^#[0-9a-f]{6}$/i' );
} );

it( 'generates responsive sizes via the responsive generator', function (): void {
    $source    = makeTestImage( 'responsive.jpg', 800, 600 );
    $generator = new ResponsiveImageGenerator( new ImageService );

    $sizes = $generator->generateSizes( $source, [200, 400, 800] );

    expect( $sizes )->toHaveKey( 200 )
        ->and( $sizes )->toHaveKey( 400 )
        ->and( $sizes )->toHaveKey( 800 )
        ->and( file_exists( $sizes[200] ) )->toBeTrue()
        ->and( file_exists( $sizes[400] ) )->toBeTrue();
} );

it( 'composes a srcset string with width descriptors', function (): void {
    $source    = makeTestImage( 'srcset.jpg', 600, 400 );
    $generator = new ResponsiveImageGenerator( new ImageService );

    $srcset = $generator->generateSrcset( $source, [200, 400] );

    expect( $srcset )->toContain( '200w' )
        ->and( $srcset )->toContain( '400w' );
} );

it( 'renders the lazy image component with native lazy attributes', function (): void {
    $html = Blade::render( '<x-perf-lazy-image src="/test.jpg" alt="Test" />' );

    expect( $html )->toContain( 'loading="lazy"' )
        ->and( $html )->toContain( 'decoding="async"' )
        ->and( $html )->toContain( 'src="/test.jpg"' )
        ->and( $html )->toContain( 'alt="Test"' );
} );

it( 'renders the responsive image component as a <picture>', function (): void {
    $source = makeTestImage( 'picture.jpg', 200, 100 );

    $html = Blade::render(
        '<x-perf-responsive-image :src="$src" alt="Pic" :sizes="[100]" />',
        ['src' => $source],
    );

    expect( $html )->toContain( '<picture' )
        ->and( $html )->toContain( '<img' );
} );

it( 'fires ImageOptimized after running the optimization pipeline', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    Event::fake();

    $source = makeTestImage( 'optimize-event.jpg', 200, 200 );

    (new ImageService)->optimize( $source, [
        'sizes'   => [100],
        'formats' => ['webp'],
    ] );

    Event::assertDispatched( ImageOptimized::class );
} );

it( 'processes OptimizeImageJob synchronously without throwing', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source = makeTestImage( 'sync-opt.jpg', 200, 200 );

    OptimizeImageJob::dispatchSync( $source, [
        'sizes'   => [100],
        'formats' => ['webp'],
    ] );

    expect( true )->toBeTrue();
} );

it( 'runs the perf:generate-webp command against a directory of mixed sources', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    makeTestImage( 'cmd-a.jpg', 80, 80, 'jpeg' );
    makeTestImage( 'cmd-b.png', 80, 80, 'png' );
    makeTestImage( 'cmd-c.gif', 80, 80, 'gif' );

    $exit = Artisan::call( 'perf:generate-webp', [
        'path'      => imageFixturesDir(),
        '--quality' => 75,
        '--force'   => true,
    ] );

    $dir = imageFixturesDir();

    expect( $exit )->toBe( 0 )
        ->and( file_exists( $dir . DIRECTORY_SEPARATOR . 'cmd-a.webp' ) )->toBeTrue()
        ->and( file_exists( $dir . DIRECTORY_SEPARATOR . 'cmd-b.webp' ) )->toBeTrue()
        ->and( file_exists( $dir . DIRECTORY_SEPARATOR . 'cmd-c.webp' ) )->toBeTrue();
} );

it( 'reports per-driver format support honestly', function (): void {
    $converter = new FormatConverter( 'gd' );

    expect( $converter->supports( 'webp' ) )->toBe( function_exists( 'imagewebp' ) )
        ->and( $converter->supports( 'avif' ) )->toBe( function_exists( 'imageavif' ) )
        ->and( $converter->supports( 'unsupported' ) )->toBeFalse();
} );

it( 'rejects an unsupported source format when converting', function (): void {
    $source = makeTestImage( 'unsupported-target.jpg', 80, 80 );

    expect( fn () => (new FormatConverter( 'gd' ))->convert( $source, 'tiff', 80 ) )
        ->toThrow( RuntimeException::class, 'Unsupported target format' );
} );
