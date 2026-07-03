<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Events\ImageOptimized;
use ArtisanPackUI\Performance\Jobs\OptimizeMediaJob;
use ArtisanPackUI\Performance\Services\ImageService;
use ArtisanPackUI\Performance\Support\MediaOptimizationStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\MediaModelStub;

beforeEach( function (): void {
    clearImageFixtures();

    Schema::create( 'media_stubs', function ( $table ): void {
        $table->id();
        $table->string( 'file_path' )->nullable();
        $table->string( 'disk' )->nullable();
        $table->string( 'mime_type' )->nullable();
        $table->string( 'dominant_color', 7 )->nullable();
        $table->string( 'optimization_status' )->default( 'pending' );
        $table->timestamp( 'optimized_at' )->nullable();
        $table->json( 'optimized_formats' )->nullable();
        $table->json( 'optimized_sizes' )->nullable();
        $table->timestamps();
    } );

    Storage::fake( 'public' );
} );

afterEach( function (): void {
    Schema::dropIfExists( 'media_stubs' );
    clearImageFixtures();
} );

it( 'targets the configured queue, tries, and backoff', function (): void {
    config( [
        'artisanpack.performance.images.queue'        => 'media',
        'artisanpack.performance.images.jobs.tries'   => 4,
        'artisanpack.performance.images.jobs.backoff' => 45,
    ] );

    $media = MediaModelStub::create( [
        'file_path' => 'nonexistent.jpg',
        'disk'      => 'public',
        'mime_type' => 'image/jpeg',
    ] );

    $job = new OptimizeMediaJob( $media );

    expect( $job->queue )->toBe( 'media' )
        ->and( $job->tries )->toBe( 4 )
        ->and( $job->backoff )->toBe( 45 );
} );

it( 'marks the row as failed when the source file cannot be resolved', function (): void {
    $media = MediaModelStub::create( [
        'file_path' => 'missing/photo.jpg',
        'disk'      => 'public',
        'mime_type' => 'image/jpeg',
    ] );

    ( new OptimizeMediaJob( $media ) )->handle( app( ImageService::class ) );

    expect( $media->refresh()->optimization_status )->toBe( MediaOptimizationStatus::FAILED );
} );

it( 'writes the optimized formats/sizes back to the media row on success', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    Event::fake();

    // Seed a real image inside the faked public disk so the job's path resolver
    // returns an absolute path.
    $sourceFixture = makeTestImage( 'opt-media.jpg', 400, 300 );
    Storage::disk( 'public' )->put( 'media/1/photo.jpg', file_get_contents( $sourceFixture ) );

    $media = MediaModelStub::create( [
        'file_path' => 'media/1/photo.jpg',
        'disk'      => 'public',
        'mime_type' => 'image/jpeg',
    ] );

    $job = new OptimizeMediaJob( $media, [
        'sizes'                  => [200],
        'formats'                => ['webp'],
        'extract_dominant_color' => true,
    ] );

    $job->handle( app( ImageService::class ) );

    $media->refresh();

    expect( $media->optimization_status )->toBe( MediaOptimizationStatus::COMPLETED )
        ->and( $media->optimized_at )->not->toBeNull()
        ->and( $media->optimized_formats )->toHaveKey( 'webp' )
        ->and( $media->optimized_sizes )->toHaveKey( '200' )
        ->and( $media->dominant_color )->toStartWith( '#' );

    Event::assertDispatched( ImageOptimized::class );
} );

it( 'marks the row as failed when Laravel calls failed()', function (): void {
    $media = MediaModelStub::create( [
        'file_path' => 'media/1/photo.jpg',
        'disk'      => 'public',
        'mime_type' => 'image/jpeg',
    ] );

    ( new OptimizeMediaJob( $media ) )->failed( new RuntimeException( 'boom' ) );

    expect( $media->refresh()->optimization_status )->toBe( MediaOptimizationStatus::FAILED );
} );

it( 'preserves optimized_at when the retry rewrites processing/failed status', function (): void {
    $earlier = Illuminate\Support\Carbon::parse( '2026-06-20 10:00:00' );

    $media = MediaModelStub::create( [
        'file_path'           => 'media/1/photo.jpg',
        'disk'                => 'public',
        'mime_type'           => 'image/jpeg',
        'optimization_status' => MediaOptimizationStatus::COMPLETED,
        'optimized_at'        => $earlier,
    ] );

    $job = new OptimizeMediaJob( $media );

    // A retry that begins reprocessing must not clear the last-successful
    // timestamp before the encoder has produced a new one.
    $reflection = new ReflectionMethod( $job, 'writeStatus' );
    $reflection->setAccessible( true );
    $reflection->invoke( $job, MediaOptimizationStatus::PROCESSING );

    $media->refresh();

    expect( $media->optimization_status )->toBe( MediaOptimizationStatus::PROCESSING )
        ->and( $media->optimized_at->toDateTimeString() )->toBe( $earlier->toDateTimeString() );

    // Same for FAILED — the previously-successful timestamp survives so
    // dashboards can still show "last successful optimization".
    $reflection->invoke( $job, MediaOptimizationStatus::FAILED );

    $media->refresh();

    expect( $media->optimization_status )->toBe( MediaOptimizationStatus::FAILED )
        ->and( $media->optimized_at->toDateTimeString() )->toBe( $earlier->toDateTimeString() );
} );

it( 'logs a warning when the write-back save() throws instead of silently swallowing', function (): void {
    $media = MediaModelStub::create( [
        'file_path'           => 'media/1/photo.jpg',
        'disk'                => 'public',
        'mime_type'           => 'image/jpeg',
        'optimization_status' => MediaOptimizationStatus::PENDING,
    ] );

    // Force the model into a state where save() throws — override the
    // connection to one that rejects writes so the job's catch block fires.
    $failing = new class extends MediaModelStub {
        public function save( array $options = [] ): bool
        {
            throw new RuntimeException( 'simulated DB failure' );
        }
    };
    $failing->exists = true;
    $failing->setRawAttributes( $media->getAttributes(), true );

    Illuminate\Support\Facades\Log::spy();

    $job        = new OptimizeMediaJob( $failing );
    $reflection = new ReflectionMethod( $job, 'safelyUpdateAttributes' );
    $reflection->setAccessible( true );
    $reflection->invoke( $job, [ 'optimization_status' => MediaOptimizationStatus::PROCESSING ] );

    Illuminate\Support\Facades\Log::shouldHaveReceived( 'warning' )
        ->once()
        ->with(
            'artisanpack-ui/performance: media optimization write-back failed',
            Mockery::on( fn ( array $context ): bool => 'simulated DB failure' === $context['error'] ),
        );
} );

it( 'skips the extract_dominant_color option when it is disabled', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $sourceFixture = makeTestImage( 'no-dominant.jpg', 200, 200 );
    Storage::disk( 'public' )->put( 'media/2/photo.jpg', file_get_contents( $sourceFixture ) );

    $media = MediaModelStub::create( [
        'file_path' => 'media/2/photo.jpg',
        'disk'      => 'public',
        'mime_type' => 'image/jpeg',
    ] );

    $job = new OptimizeMediaJob( $media, [
        'sizes'                  => [100],
        'formats'                => ['webp'],
        'extract_dominant_color' => false,
    ] );

    $job->handle( app( ImageService::class ) );

    expect( $media->refresh()->dominant_color )->toBeNull();
} );
