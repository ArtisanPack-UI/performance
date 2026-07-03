<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Jobs\OptimizeMediaJob;
use ArtisanPackUI\Performance\Listeners\OptimizeUploadedMedia;
use ArtisanPackUI\Performance\Services\MediaLibraryDetector;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\MediaModelStub;

beforeEach( function (): void {
    Queue::fake();
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

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertNothingPushed();
} );

it( 'skips when optimize_on_upload is toggled off but integration is on', function (): void {
    config( [ 'artisanpack.performance.media_library_integration.optimize_on_upload' => false ] );

    $media            = new MediaModelStub;
    $media->mime_type = 'image/jpeg';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertNothingPushed();
} );

it( 'skips non-image media rows', function (): void {
    $media            = new MediaModelStub;
    $media->mime_type = 'application/pdf';

    ( new OptimizeUploadedMedia( new MediaLibraryDetector ) )( $media );

    Queue::assertNothingPushed();
} );

it( 'unwraps a wrapping event object exposing a `media` property', function (): void {
    $media            = new MediaModelStub;
    $media->mime_type = 'image/png';

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

    $listener = new OptimizeUploadedMedia( new MediaLibraryDetector );
    $listener->handle( $media );

    Queue::assertPushed( OptimizeMediaJob::class );
} );
