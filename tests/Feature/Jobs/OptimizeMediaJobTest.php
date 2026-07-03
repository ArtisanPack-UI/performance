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
