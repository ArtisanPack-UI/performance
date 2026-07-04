<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Events\ImageOptimized;
use ArtisanPackUI\Performance\Jobs\ConvertImageFormatJob;
use ArtisanPackUI\Performance\Jobs\GenerateResponsiveSizesJob;
use ArtisanPackUI\Performance\Jobs\OptimizeImageJob;
use ArtisanPackUI\Performance\Services\ImageService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach( function (): void {
    clearImageFixtures();
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'dispatches OptimizeImageJob onto the configured queue', function (): void {
    Queue::fake();

    config( [
        'artisanpack.performance.images.queue'        => 'media',
        'artisanpack.performance.images.jobs.tries'   => 5,
        'artisanpack.performance.images.jobs.backoff' => 60,
    ] );

    OptimizeImageJob::dispatch( '/tmp/some.jpg', ['sizes' => [200]] );

    Queue::assertPushed( OptimizeImageJob::class, function ( OptimizeImageJob $job ): bool {
        return 'media' === $job->queue
            && 5 === $job->tries
            && 60 === $job->backoff
            && '/tmp/some.jpg' === $job->path
            && ['sizes' => [200]] === $job->options;
    } );
} );

it( 'runs the optimization pipeline when OptimizeImageJob is handled synchronously', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    Event::fake();

    $source = makeTestImage( 'opt-job.jpg', 400, 200 );

    OptimizeImageJob::dispatchSync( $source, [
        'sizes'   => [100],
        'formats' => ['webp'],
    ] );

    Event::assertDispatched( ImageOptimized::class );
} );

it( 'dispatches ConvertImageFormatJob onto the configured queue', function (): void {
    Queue::fake();

    config( ['artisanpack.performance.images.queue' => 'images'] );

    ConvertImageFormatJob::dispatch( '/tmp/h.jpg', 'webp', 80 );

    Queue::assertPushed( ConvertImageFormatJob::class, function ( ConvertImageFormatJob $job ): bool {
        return 'images' === $job->queue
            && '/tmp/h.jpg' === $job->path
            && 'webp' === $job->format
            && 80 === $job->quality;
    } );
} );

it( 'converts a real source when ConvertImageFormatJob is handled synchronously', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source = makeTestImage( 'convert-job.jpg', 100, 100 );

    ConvertImageFormatJob::dispatchSync( $source, 'webp', 80 );

    $expected = dirname( $source ) . DIRECTORY_SEPARATOR . pathinfo( $source, PATHINFO_FILENAME ) . '.webp';

    expect( file_exists( $expected ) )->toBeTrue();
} );

it( 'silently skips ConvertImageFormatJob when the driver cannot encode the requested format', function (): void {
    $source = makeTestImage( 'convert-skip.jpg', 100, 100 );

    app()->bind( ImageService::class, function () {
        return new class extends ImageService {
            public function __construct()
            {
            }

            public function supportsFormat( string $format ): bool
            {
                return false;
            }

            public function convertFormat( string $path, string $format, int $quality ): string
            {
                throw new RuntimeException( 'Should not be called when format is unsupported' );
            }
        };
    } );

    // Asserts the job returns cleanly rather than throwing.
    ConvertImageFormatJob::dispatchSync( $source, 'avif', 70 );

    expect( true )->toBeTrue();
} );

it( 'dispatches GenerateResponsiveSizesJob with retry configuration', function (): void {
    Queue::fake();

    config( [
        'artisanpack.performance.images.jobs.tries'   => 4,
        'artisanpack.performance.images.jobs.backoff' => 45,
    ] );

    GenerateResponsiveSizesJob::dispatch( '/tmp/h.jpg', [320, 640], ['webp'] );

    Queue::assertPushed( GenerateResponsiveSizesJob::class, function ( GenerateResponsiveSizesJob $job ): bool {
        return [320, 640] === $job->sizes
            && ['webp'] === $job->formats
            && 4 === $job->tries
            && 45 === $job->backoff;
    } );
} );

it( 'chains the optimization jobs into a single queued pipeline', function (): void {
    Bus::fake();

    Bus::chain( [
        new ConvertImageFormatJob( '/tmp/h.jpg', 'webp', 80 ),
        new ConvertImageFormatJob( '/tmp/h.jpg', 'avif', 70 ),
        new GenerateResponsiveSizesJob( '/tmp/h.jpg', [320, 640] ),
    ] )->dispatch();

    Bus::assertChained( [
        ConvertImageFormatJob::class,
        ConvertImageFormatJob::class,
        GenerateResponsiveSizesJob::class,
    ]);
});
