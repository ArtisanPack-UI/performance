<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Jobs\OptimizeMediaJob;
use ArtisanPackUI\Performance\Listeners\OptimizeUploadedMedia;
use ArtisanPackUI\Performance\Services\MediaLibraryDetector;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\MediaModelStub;

beforeEach( function (): void {
    Queue::fake();
    Storage::fake( 'public' );

    // Every image-media path in these tests points at this file so the
    // listener's file-exists guard passes.
    Storage::disk( 'public' )->put( 'media/1/photo.jpg', 'fake-image-bytes' );

    config( [
        'artisanpack.performance.media_library_integration.enabled'            => true,
        'artisanpack.performance.media_library_integration.optimize_on_upload' => true,
        'artisanpack.performance.images.sizes'                                 => [320, 640],
        'artisanpack.performance.images.formats'                               => [
            'webp' => ['enabled' => true, 'quality' => 80],
            'avif' => ['enabled' => false, 'quality' => 70],
        ],
        'artisanpack.performance.images.dominant_color.enabled' => true,
    ] );
} );

it( 'dispatches OptimizeMediaJob for an image media row', function (): void {
    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    $listener = new OptimizeUploadedMedia( new MediaLibraryDetector );
    $listener( $media );

    Queue::assertPushed( OptimizeMediaJob::class, function ( OptimizeMediaJob $job ) use ( $media ): bool {
        return $job->media === $media
            && [320, 640] === $job->options['sizes']
            && ['webp'] === $job->options['formats']
            && true === $job->options['extract_dominant_color'];
    } );
} );

it( 'skips when media_library_integration is disabled entirely', function (): void {
    config( [ 'artisanpack.performance.media_library_integration.enabled' => false ] );

    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertNothingPushed();
} );

it( 'skips when optimize_on_upload is toggled off but integration is on', function (): void {
    config( [ 'artisanpack.performance.media_library_integration.optimize_on_upload' => false ] );

    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertNothingPushed();
} );

it( 'skips non-image media rows', function (): void {
    $media            = new MediaModelStub;
    $media->mime_type = 'application/pdf';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertNothingPushed();
} );

it( 'skips rows whose file_path does not exist on disk (factory / seeder guard)', function (): void {
    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';
    $media->file_path = 'seeded/does-not-exist.jpg';
    $media->disk      = 'public';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertNothingPushed();
} );

it( 'unwraps a wrapping event object exposing a `media` property', function (): void {
    $media            = new MediaModelStub;
    $media->mime_type = 'image/png';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    $event        = new stdClass;
    $event->media = $media;

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $event );

    Queue::assertPushed( OptimizeMediaJob::class );
} );

it( 'ignores payloads that carry no resolvable media model', function (): void {
    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( new stdClass );

    Queue::assertNothingPushed();
} );

it( 'exposes both `handle` and __invoke as entrypoints', function (): void {
    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    $listener = new OptimizeUploadedMedia( new MediaLibraryDetector );
    $listener->handle( $media );

    Queue::assertPushed( OptimizeMediaJob::class );
} );

it( 'accepts a flat-list formats config (["webp","avif"]) as well as the associative shape', function (): void {
    config( [
        'artisanpack.performance.images.formats' => ['webp', 'avif'],
    ] );

    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertPushed( OptimizeMediaJob::class, function ( OptimizeMediaJob $job ): bool {
        return ['webp', 'avif'] === $job->options['formats'];
    } );
} );

it( 'dedupes formats when both shapes overlap', function (): void {
    // Weird mixed config: normally you'd pick one shape, but the loop
    // should dedupe when the operator has drifted.
    config( [
        'artisanpack.performance.images.formats' => [
            'webp',
            'avif' => ['enabled' => true],
            'WEBP',
        ],
    ] );

    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertPushed( OptimizeMediaJob::class, function ( OptimizeMediaJob $job ): bool {
        return ['webp', 'avif'] === $job->options['formats'];
    } );
} );

it( 'defers the dispatch until the outer transaction commits', function (): void {
    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';
    $media->file_path = 'media/1/photo.jpg';
    $media->disk      = 'public';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertPushed( OptimizeMediaJob::class, function ( OptimizeMediaJob $job ): bool {
        // The Illuminate\Foundation\Bus\PendingDispatch::afterCommit() flag
        // is captured on the pending dispatch object, but by the time the
        // job lands on the fake queue the transaction wrapper has already
        // resolved it. We assert the job has the property set to true so
        // the wiring is unmistakable in the test transcript.
        return true === $job->afterCommit;
    } );
} );
