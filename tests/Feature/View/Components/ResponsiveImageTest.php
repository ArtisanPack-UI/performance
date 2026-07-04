<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use ArtisanPackUI\Performance\Services\ImageService;
use ArtisanPackUI\Performance\View\Components\ResponsiveImage;
use Illuminate\Support\Facades\Blade;

/**
 * Stage path used by tests that need to write source/variant files under
 * public_path() so the component can resolve a srcset URL prefix. Each test
 * uses a dedicated subdirectory that is wiped before AND after every test,
 * so a mid-test failure or crash cannot leak stale JPEGs into the next run.
 */
function perfRespTestStageDir(): string
{
    $dir = public_path( 'perf-resp-test' );

    if ( ! is_dir( $dir ) ) {
        mkdir( $dir, 0777, true );
    }

    return $dir;
}

function perfRespTestClearStage(): void
{
    $dir = public_path( 'perf-resp-test' );

    if ( ! is_dir( $dir ) ) {
        return;
    }

    foreach ( glob( $dir . DIRECTORY_SEPARATOR . '*' ) as $entry ) {
        if ( is_file( $entry ) ) {
            @unlink( $entry );
        }
    }
}

beforeEach( function (): void {
    clearImageFixtures();
    perfRespTestClearStage();
} );

afterEach( function (): void {
    clearImageFixtures();
    perfRespTestClearStage();
} );

it( 'renders a picture element with an img fallback', function (): void {
    $html = Blade::render(
        '<x-perf-responsive-image src="/img/hero.jpg" alt="Hero" />',
    );

    expect( $html )->toContain( '<picture' )
        ->and( $html )->toContain( '<img' )
        ->and( $html )->toContain( 'alt="Hero"' );
} );

it( 'emits source elements for AVIF and WebP when generation succeeds', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    // Stage under public_path() so the component can resolve a URL prefix
    // for the srcset entries (filesystem paths outside public_path are now
    // correctly refused — see the "refuses to leak filesystem paths" test).
    $stageDir = perfRespTestStageDir();
    $source   = $stageDir . DIRECTORY_SEPARATOR . 'multi-fmt.jpg';

    $image = imagecreatetruecolor( 600, 300 );
    imagefilledrectangle( $image, 0, 0, 600, 300, imagecolorallocate( $image, 50, 100, 150 ) );
    imagejpeg( $image, $source, 90 );
    imagedestroy( $image );

    config( [
        'artisanpack.performance.images.formats' => [
            'webp' => ['enabled' => true, 'quality' => 70],
            'avif' => ['enabled' => false, 'quality' => 60],
        ],
    ] );

    app()->instance(
        ResponsiveImageGenerator::class,
        new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) ),
    );

    $html = Blade::render(
        '<x-perf-responsive-image src="/perf-resp-test/multi-fmt.jpg" alt="Hero" :sizes="[150, 300]" />',
    );

    expect( $html )->toContain( 'type="image/webp"' )
        ->and( $html )->not->toContain( 'type="image/avif"' );
} );

it( 'forwards the sizes attribute to every source element', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $stageDir = perfRespTestStageDir();
    $source   = $stageDir . DIRECTORY_SEPARATOR . 'sizes-attr.jpg';

    $image = imagecreatetruecolor( 600, 300 );
    imagefilledrectangle( $image, 0, 0, 600, 300, imagecolorallocate( $image, 80, 120, 40 ) );
    imagejpeg( $image, $source, 90 );
    imagedestroy( $image );

    config( [
        'artisanpack.performance.images.formats' => [
            'webp' => ['enabled' => true, 'quality' => 70],
            'avif' => ['enabled' => false, 'quality' => 60],
        ],
    ] );

    app()->instance(
        ResponsiveImageGenerator::class,
        new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) ),
    );

    $html = Blade::render(
        '<x-perf-responsive-image src="/perf-resp-test/sizes-attr.jpg" alt="Hero" :sizes="[150, 300]" sizes-attr="(max-width: 640px) 100vw, 50vw" />',
    );

    expect( $html )->toContain( 'sizes="(max-width: 640px) 100vw, 50vw"' );
} );

it( 'forwards the picture class to the picture element', function (): void {
    $html = Blade::render( '<x-perf-responsive-image src="/img/hero.jpg" alt="Hero" class="hero" />' );

    expect( $html )->toContain( 'class="perf-responsive-image hero"' );
} );

it( 'rewrites srcset entries so they use a web URL prefix instead of the filesystem path', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    // Stage the source under the shared perf-resp-test subdirectory so the
    // beforeEach/afterEach hooks clean it up even if assertions throw.
    $stageDir = perfRespTestStageDir();
    $source   = $stageDir . DIRECTORY_SEPARATOR . 'stage.jpg';

    $image = imagecreatetruecolor( 600, 300 );
    imagefilledrectangle( $image, 0, 0, 600, 300, imagecolorallocate( $image, 50, 100, 150 ) );
    imagejpeg( $image, $source, 90 );
    imagedestroy( $image );

    config( [
        'artisanpack.performance.images.formats' => [
            'webp' => ['enabled' => true, 'quality' => 70],
            'avif' => ['enabled' => false, 'quality' => 60],
        ],
    ] );

    app()->instance(
        ResponsiveImageGenerator::class,
        new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) ),
    );

    $component = new ResponsiveImage( '/perf-resp-test/stage.jpg', 'Stage', [150, 300] );

    expect( $component->webpSrcset )->toContain( '/perf-resp-test/stage-150w.webp 150w' )
        ->and( $component->webpSrcset )->toContain( '/perf-resp-test/stage-300w.webp 300w' )
        ->and( $component->webpSrcset )->not->toContain( $stageDir )
        ->and( $component->fallbackSrcset )->toContain( '/perf-resp-test/stage-150w.jpg 150w' );
} );

it( 'refuses to leak filesystem paths into srcset when src is an absolute path outside public_path', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    // Source at a `/`-prefixed location that does NOT live under public_path —
    // the component must decline to compute a URL prefix and degrade to a
    // bare <img> rather than emit a srcset of `/tmp/...` filesystem paths.
    $source = makeTestImage( 'fs-leak.jpg', 400, 200 );

    app()->instance(
        ResponsiveImageGenerator::class,
        new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) ),
    );

    $component = new ResponsiveImage( $source, 'Outside public_path', [100, 200] );

    expect( $component->webpSrcset )->toBe( '' )
        ->and( $component->fallbackSrcset )->toBe( '' )
        ->and( $component->fallbackSrc )->toBe( $source );
} );

it( 'preserves the root slash when src is at the web root', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    // This test deliberately writes to public_path() root (NOT the
    // perf-resp-test subdirectory) because the bug we're guarding against
    // only triggers when `dirname($src)` resolves to '/'. Clean up explicitly
    // via try/finally so the file is removed even on assertion failure.
    $publicRoot = public_path();
    $source     = $publicRoot . DIRECTORY_SEPARATOR . 'stage-root.jpg';
    $cleanup    = function () use ( $publicRoot ): void {
        foreach ( glob( $publicRoot . DIRECTORY_SEPARATOR . 'stage-root*' ) as $f ) {
            @unlink( $f );
        }
    };

    try {
        $image = imagecreatetruecolor( 300, 150 );
        imagefilledrectangle( $image, 0, 0, 300, 150, imagecolorallocate( $image, 30, 60, 90 ) );
        imagejpeg( $image, $source, 90 );
        imagedestroy( $image );

        app()->instance(
            ResponsiveImageGenerator::class,
            new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) ),
        );

        $component = new ResponsiveImage( '/stage-root.jpg', 'Web root', [100, 200] );

        // Every entry must start with a single leading slash. Split on commas
        // and assert each individually so the test catches both "//foo" and "foo".
        $entries = array_map( 'trim', explode( ',', $component->fallbackSrcset ) );

        expect( $entries )->toHaveCount( 2 )
            ->and( $entries[0] )->toBe( '/stage-root-100w.jpg 100w' )
            ->and( $entries[1] )->toBe( '/stage-root-200w.jpg 200w' )
            ->and( $component->fallbackSrcset )->not->toContain( '//' );
    } finally {
        $cleanup();
    }
} );

it( 'falls back to a bare img when the source is a remote URL', function (): void {
    $html = Blade::render( '<x-perf-responsive-image src="https://cdn.example.com/h.jpg" alt="Hero" />' );

    expect( $html )->toContain( '<picture' )
        ->and( $html )->toContain( 'src="https://cdn.example.com/h.jpg"' )
        ->and( $html )->not->toContain( 'type="image/webp"' )
        ->and( $html )->not->toContain( 'type="image/avif"' );
} );

it( 'degrades to a bare img when generation throws', function (): void {
    // Pin a generator whose underlying ImageService throws on resize — the
    // component must catch and emit a usable picture element rather than
    // 500. Stage the source under public_path() so the path-safety guard
    // doesn't short-circuit before generation is even attempted.
    $stageDir = perfRespTestStageDir();
    $source   = $stageDir . DIRECTORY_SEPARATOR . 'stage-throws.jpg';

    $image = imagecreatetruecolor( 200, 100 );
    imagefilledrectangle( $image, 0, 0, 200, 100, imagecolorallocate( $image, 10, 10, 10 ) );
    imagejpeg( $image, $source, 90 );
    imagedestroy( $image );

    $brokenImages = new class extends ImageService {
        public function __construct()
        {
        } // bypass FormatConverter wiring

        public function resize( string $path, int $width, ?int $height = null ): string
        {
            throw new RuntimeException( 'boom' );
        }

        public function supportsFormat( string $format ): bool
        {
            return true;
        }
    };

    app()->instance(
        ResponsiveImageGenerator::class,
        new ResponsiveImageGenerator( $brokenImages ),
    );

    $component = new ResponsiveImage( '/perf-resp-test/stage-throws.jpg', 'Hero' );

    expect( $component->avifSrcset )->toBe( '' )
        ->and( $component->webpSrcset )->toBe( '' )
        ->and( $component->fallbackSrcset )->toBe( '' )
        ->and( $component->fallbackSrc )->toBe( '/perf-resp-test/stage-throws.jpg');
});
