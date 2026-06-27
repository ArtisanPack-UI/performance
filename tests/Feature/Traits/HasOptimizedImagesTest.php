<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\Jobs\OptimizeImageJob;
use ArtisanPackUI\Performance\Services\ImageService;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\HasOptimizedImagesModelStub as TraitModelStub;

beforeEach( function (): void {
    clearImageFixtures();
    // Trait URL derivation requires sources to live under public_path() — the
    // trait now returns null for anything outside it (so a raw fs path can't
    // masquerade as a public URL). Point public_path at the fixtures dir so
    // the per-test images are URL-mappable without having to wire a real
    // public storage tree.
    app()->usePublicPath( imageFixturesDir() );
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'returns null when the field is not declared as optimizable', function (): void {
    $model = new TraitModelStub;

    expect( $model->getOptimizedImageUrl( 'missing', 'webp', 320 ) )->toBeNull()
        ->and( $model->getImageSrcset( 'missing' ) )->toBe( '' )
        ->and( $model->getImageDominantColor( 'missing' ) )->toBeNull();
} );

it( 'returns null when the attribute value cannot be resolved on disk', function (): void {
    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['sizes' => [100]]];
    $model->setRawAttributes( ['photo' => '/does/not/exist.jpg'] );

    expect( $model->getOptimizedImageUrl( 'photo', 'webp', 100 ) )->toBeNull()
        ->and( $model->getImageSrcset( 'photo' ) )->toBe( '' )
        ->and( $model->getImageDominantColor( 'photo' ) )->toBeNull();
} );

it( 'produces an optimized image URL when the active driver supports the format', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source             = makeTestImage( 'trait-photo.jpg', 400, 300 );
    $model              = new TraitModelStub;
    $model->imageConfig = [
        'photo' => [
            'sizes'   => [200],
            'formats' => ['webp'],
            'quality' => 70,
        ],
    ];
    $model->setRawAttributes( ['photo' => $source] );

    $url = $model->getOptimizedImageUrl( 'photo', 'webp', 200 );

    expect( $url )->not->toBeNull()
        ->and( $url )->toEndWith( '.webp' );
} );

it( 'returns null when the active driver cannot encode the requested format', function (): void {
    $source             = makeTestImage( 'unsupported.jpg', 80, 80 );
    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['sizes' => [80]]];
    $model->setRawAttributes( ['photo' => $source] );

    // Inject a stub ImageService that always returns false from supportsFormat.
    app()->instance( ImageService::class, new class extends ImageService {
        public function __construct()
        {
        }

        public function supportsFormat( string $format ): bool
        {
            return false;
        }
    } );
    app()->instance( ResponsiveImageGenerator::class, new ResponsiveImageGenerator(
        app( ImageService::class ),
    ) );

    expect( $model->getOptimizedImageUrl( 'photo', 'webp', 80 ) )->toBeNull();
} );

it( 'returns the srcset for the configured sizes', function (): void {
    $source             = makeTestImage( 'srcset-photo.jpg', 600, 400 );
    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['sizes' => [320, 640]]];
    $model->setRawAttributes( ['photo' => $source] );

    $srcset = $model->getImageSrcset( 'photo' );

    expect( $srcset )->not->toBe( '' )
        ->and( $srcset )->toContain( '320w' )
        ->and( $srcset )->toContain( '600w' ); // 640 clamped to source width 600
} );

it( 'returns a dominant color hex string when extraction is enabled', function (): void {
    $source             = makeTestImage( 'color-photo.jpg', 60, 60, 'jpeg', [10, 200, 50] );
    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['sizes' => [60], 'extract_dominant_color' => true]];
    $model->setRawAttributes( ['photo' => $source] );

    $color = $model->getImageDominantColor( 'photo' );

    expect( $color )->not->toBeNull()
        ->and( $color )->toMatch( '/^#[0-9a-f]{6}$/i' );
} );

it( 'returns null for dominant color when extraction is disabled', function (): void {
    $source             = makeTestImage( 'no-color.jpg', 60, 60 );
    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['extract_dominant_color' => false]];
    $model->setRawAttributes( ['photo' => $source] );

    expect( $model->getImageDominantColor( 'photo' ) )->toBeNull();
} );

it( 'dispatches OptimizeImageJob for changed fields when auto_optimize is enabled', function (): void {
    Queue::fake();

    $source             = makeTestImage( 'auto-opt.jpg', 200, 200 );
    $model              = new TraitModelStub;
    $model->imageConfig = [
        'photo' => [
            'sizes'         => [100],
            'formats'       => ['webp'],
            'auto_optimize' => true,
        ],
    ];
    $model->setRawAttributes( ['photo' => $source] );
    $model->changedFields = ['photo'];

    $model->autoOptimizeChangedImages();

    Queue::assertPushed( OptimizeImageJob::class );
} );

it( 'does not dispatch OptimizeImageJob when auto_optimize is false', function (): void {
    Queue::fake();

    $source             = makeTestImage( 'no-auto.jpg', 200, 200 );
    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['auto_optimize' => false]];
    $model->setRawAttributes( ['photo' => $source] );

    $model->autoOptimizeChangedImages();

    Queue::assertNotPushed( OptimizeImageJob::class );
} );

it( 'does not leak absolute filesystem paths as public URLs', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    // Regression: an attribute whose value happens to start with "/" but
    // isn't actually under public_path() must not be returned as a URL —
    // using its directory as a URL prefix would leak the host filesystem
    // layout AND produce a 404 in the browser. Point public_path at a
    // separate directory so the fixture is provably NOT under it.
    $leakyDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'perf-trait-leak-source';
    @mkdir( $leakyDir, 0777, true );

    $source = $leakyDir . DIRECTORY_SEPARATOR . 'fs-leak.jpg';
    $image  = imagecreatetruecolor( 200, 200 );
    imagefilledrectangle( $image, 0, 0, 200, 200, imagecolorallocate( $image, 100, 100, 100 ) );
    imagejpeg( $image, $source, 90 );
    imagedestroy( $image );

    // public_path is set in beforeEach to the fixtures dir, NOT $leakyDir.
    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['sizes' => [100], 'formats' => ['webp']]];
    $model->setRawAttributes( ['photo' => $source] );

    $url    = $model->getOptimizedImageUrl( 'photo', 'webp', 100 );
    $srcset = $model->getImageSrcset( 'photo' );

    // The trait must refuse to expose the fs path — getOptimizedImageUrl
    // returns null and getImageSrcset returns empty rather than emitting
    // the leaked path.
    expect( $url )->toBeNull()
        ->and( $srcset )->toBe( '' );

    @unlink( $source );
    @unlink( $leakyDir . DIRECTORY_SEPARATOR . 'fs-leak-100w.jpg' );
    @unlink( $leakyDir . DIRECTORY_SEPARATOR . 'fs-leak-100w.webp' );
    @rmdir( $leakyDir );
} );

it( 'rejects remote URL values', function (): void {
    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['sizes' => [100]]];
    $model->setRawAttributes( ['photo' => 'https://example.com/foo.jpg'] );

    expect( $model->getOptimizedImageUrl( 'photo', 'webp', 100 ) )->toBeNull()
        ->and( $model->getImageDominantColor( 'photo' ) )->toBeNull();
} );

it( 'falls back to public_path resolution when the value is web-relative', function (): void {
    $publicDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'perf-trait-public';
    @mkdir( $publicDir, 0777, true );

    $source = $publicDir . DIRECTORY_SEPARATOR . 'web-relative.jpg';
    $image  = imagecreatetruecolor( 200, 100 );
    imagefilledrectangle( $image, 0, 0, 200, 100, imagecolorallocate( $image, 0, 0, 200 ) );
    imagejpeg( $image, $source, 90 );
    imagedestroy( $image );

    // Rebind public_path to point at our temp dir. usePublicPath() updates
    // both the container binding and the private Application::$publicPath
    // property that public_path() actually consults.
    app()->usePublicPath( $publicDir );

    $model              = new TraitModelStub;
    $model->imageConfig = ['photo' => ['sizes' => [100]]];
    $model->setRawAttributes( ['photo' => '/web-relative.jpg']);

    $srcset = $model->getImageSrcset( 'photo');

    expect( $srcset)->toContain( '100w')
        ->and( $srcset)->toStartWith( '/');

    @unlink( $source);
    @rmdir( $publicDir);
});
