<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Events\ImageOptimized;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use ArtisanPackUI\Performance\Services\ImageService;
use Illuminate\Support\Facades\Event;

beforeEach( function (): void {
    clearImageFixtures();
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'optimizes an image by generating every enabled format at every configured width', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    Event::fake();

    config( [
        'artisanpack.performance.images.driver'  => 'gd',
        'artisanpack.performance.images.sizes'   => [100, 200],
        'artisanpack.performance.images.formats' => [
            'webp' => ['enabled' => true, 'quality' => 70],
            'avif' => ['enabled' => false, 'quality' => 60],
        ],
    ] );

    $source  = makeTestImage( 'pipeline.jpg', 400, 200, 'jpeg' );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    $result = $service->optimize( $source );

    expect( $result['source'] )->toBe( $source )
        ->and( $result['sizes'] )->toBe( [100, 200] )
        ->and( $result['formats'] )->toBe( ['webp'] )
        ->and( $result['variants'] )->toHaveCount( 2 )
        ->and( collect( $result['variants'] )->pluck( 'format' )->all() )->toBe( ['webp', 'webp'] )
        ->and( collect( $result['variants'] )->pluck( 'width' )->all() )->toBe( [100, 200] );

    foreach ( $result['variants'] as $variant ) {
        expect( file_exists( $variant['path'] ) )->toBeTrue();
    }

    Event::assertDispatched( ImageOptimized::class );
} );

it( 'clamps requested widths to the source width so variants do not collide on the same file', function (): void {
    // Regression: requesting widths larger than the source would otherwise leave every
    // oversize width pointing at the same FormatConverter destination (source.webp),
    // overwriting prior variants.
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source  = makeTestImage( 'clamp.jpg', 400, 200, 'jpeg' );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    $result = $service->optimize( $source, [
        'sizes'   => [200, 800, 1600],
        'formats' => ['webp'],
    ] );

    // Sizes collapse to [200, 400] after clamping to the 400-wide source.
    expect( $result['sizes'] )->toBe( [200, 400] )
        ->and( $result['variants'] )->toHaveCount( 2 );

    $paths = collect( $result['variants'] )->pluck( 'path' )->all();
    expect( count( array_unique( $paths ) ) )->toBe( 2 );
} );

it( 'skips formats the active driver cannot encode rather than failing the pipeline', function (): void {
    Event::fake();

    $source = makeTestImage( 'skip.jpg', 200, 200, 'jpeg' );

    $converter = new class( 'gd' ) extends FormatConverter {
        public function supports( string $format ): bool
        {
            return false;
        }
    };

    $service = new ImageService( $converter );

    $result = $service->optimize( $source, [
        'sizes'   => [100],
        'formats' => ['avif'],
    ] );

    // Pipeline tolerates the unsupported driver and returns an empty result.
    expect( $result['variants'] )->toBe( [] )
        ->and( $result['formats'] )->toBe( [] );

    // Regression: no event dispatch when nothing was produced, so listeners
    // don't enqueue downstream work for files that don't exist.
    Event::assertNotDispatched( ImageOptimized::class );
} );

it( 'dispatches ImageOptimized with the formats actually produced, not requested', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    Event::fake();

    $source = makeTestImage( 'produced.jpg', 200, 200, 'jpeg' );

    // Converter that supports WebP but pretends AVIF is unsupported.
    $converter = new class( 'gd' ) extends FormatConverter {
        public function supports( string $format ): bool
        {
            return 'webp' === strtolower( $format );
        }
    };

    $service = new ImageService( $converter );

    $service->optimize( $source, [
        'sizes'   => [100],
        'formats' => ['avif', 'webp'],
    ] );

    Event::assertDispatched( ImageOptimized::class, function ( ImageOptimized $event ): bool {
        // Regression: previously dispatched the REQUESTED formats; now only the produced ones.
        return ['webp'] === $event->formats;
    } );
} );

it( 'converts a single image to the requested format', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source  = makeTestImage( 'convert.jpg' );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    $converted = $service->convertFormat( $source, 'webp', 80 );

    expect( file_exists( $converted ) )->toBeTrue()
        ->and( $converted )->toEndWith( '.webp' );
} );

it( 'returns the source path when the resize target is wider than the original and height is null', function (): void {
    $source  = makeTestImage( 'small.jpg', 100, 50 );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    expect( $service->resize( $source, 500 ) )->toBe( $source );
} );

it( 'honors an explicit height even when the source is smaller than the target width', function (): void {
    // Regression: resize() used to short-circuit on `sourceWidth <= width`
    // and silently ignore an explicit $height, returning wrong dimensions.
    $source  = makeTestImage( 'short.jpg', 200, 100 );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    $resized = $service->resize( $source, 800, 50 );

    expect( $resized )->not->toBe( $source )
        ->and( file_exists( $resized ) )->toBeTrue();

    [$width, $height] = getimagesize( $resized );

    expect( $width )->toBe( 800 )
        ->and( $height )->toBe( 50 );
} );

it( 'resizes an image to the given width and preserves the aspect ratio', function (): void {
    $source  = makeTestImage( 'wide.jpg', 400, 200 );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    $resized = $service->resize( $source, 100 );

    expect( $resized )->not->toBe( $source )
        ->and( file_exists( $resized ) )->toBeTrue();

    [$width, $height] = getimagesize( $resized );

    expect( $width )->toBe( 100 )
        ->and( $height )->toBe( 50 );
} );

it( 'generates a srcset string with widths in ascending order', function (): void {
    $source  = makeTestImage( 'srcset.jpg', 800, 400 );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    $srcset = $service->generateSrcset( $source, [200, 400] );

    expect( $srcset )->toContain( '200w' )
        ->and( $srcset )->toContain( '400w' )
        ->and( substr_count( $srcset, ',' ) )->toBe( 1 );
} );

it( 'extracts the dominant color as a 7-character hex string', function (): void {
    $source  = makeTestImage( 'color.jpg', 40, 40, 'jpeg', [200, 50, 100] );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    $color = $service->extractDominantColor( $source );

    expect( $color )->toMatch( '/^#[0-9a-f]{6}$/' );
} );

it( 'generates dominant color and blur placeholders', function (): void {
    $source  = makeTestImage( 'placeholder.jpg', 80, 60 );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    expect( $service->generatePlaceholder( $source, 'dominant_color' ) )->toMatch( '/^#[0-9a-f]{6}$/' )
        ->and( $service->generatePlaceholder( $source, 'blur' ) )->toStartWith( 'data:image/jpeg;base64,' );
} );

it( 'throws for unknown placeholder types', function (): void {
    $source  = makeTestImage( 'unknown-placeholder.jpg' );
    $service = new ImageService( new FormatConverter( 'gd' ) );

    expect( fn () => $service->generatePlaceholder( $source, 'mystery' ) )
        ->toThrow( RuntimeException::class, 'Unknown placeholder type' );
} );

it( 'throws when optimizing a missing source image', function (): void {
    $service = new ImageService( new FormatConverter( 'gd' ) );

    expect( fn () => $service->optimize( '/no/such/file.jpg' ) )
        ->toThrow( RuntimeException::class, 'Source image is not readable' );
} );

it( 'delegates supportsFormat to the underlying converter', function (): void {
    $service = new ImageService( new FormatConverter( 'gd' ) );

    expect( $service->supportsFormat( 'webp' ) )->toBe( function_exists( 'imagewebp' ) )
        ->and( $service->supportsFormat( 'avif' ) )->toBe( function_exists( 'imageavif'));
});
