<?php

/**
 * Listener that runs the optimization pipeline against uploaded media rows.
 *
 * Wired from `PerformanceServiceProvider::registerMediaLibraryIntegration()`
 * to fire whenever the artisanpack-ui/media-library package publishes a
 * new `Media` row. The listener guards on the media_library_integration
 * config surface so operators can force the wiring off without needing to
 * uninstall the media-library package; it then dispatches
 * `OptimizeMediaJob` to run the actual encoding work off the request
 * thread.
 *
 * The `__invoke` entrypoint accepts either an Eloquent-created `Media`
 * model (the standard Laravel model-event payload) or a hypothetical
 * future `MediaUploaded` event exposing a `media` property so the same
 * class can serve both integration paths.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Listeners;

use ArtisanPackUI\Performance\Jobs\OptimizeMediaJob;
use ArtisanPackUI\Performance\Services\MediaLibraryDetector;
use Illuminate\Database\Eloquent\Model;

/**
 * Listener class for post-upload media optimization.
 *
 *
 * @since      1.0.0
 */
class OptimizeUploadedMedia
{
    /**
     * Media library detector.
     *
     * @since 1.0.0
     */
    protected MediaLibraryDetector $detector;

    /**
     * Creates a new listener instance.
     *
     * @since 1.0.0
     */
    public function __construct( MediaLibraryDetector $detector )
    {
        $this->detector = $detector;
    }

    /**
     * Handles the payload.
     *
     * Accepts either a bare Media model (from Eloquent's `created` model
     * event) or an event object exposing a `media` property. Non-image
     * media rows are ignored — the Performance package has nothing useful
     * to do with a video or PDF upload at this layer.
     *
     * @since 1.0.0
     *
     * @param  Model|object  $payload  Media model instance or wrapping event.
     */
    public function __invoke( object $payload ): void
    {
        if ( ! $this->detector->shouldOptimizeOnUpload() ) {
            return;
        }

        $media = $this->resolveMedia( $payload );

        if ( null === $media ) {
            return;
        }

        if ( ! $this->isImageMedia( $media ) ) {
            return;
        }

        OptimizeMediaJob::dispatch( $media, $this->buildJobOptions() );
    }

    /**
     * Alias for `__invoke` so the class works with `Event::listen(..., handle)`.
     *
     * Laravel's event dispatcher accepts either an invokable class or a
     * `handle` method — exposing both means the listener can be registered
     * with either style without extra glue in the service provider.
     *
     * @since 1.0.0
     *
     * @param  Model|object  $payload  Media model instance or wrapping event.
     */
    public function handle( object $payload ): void
    {
        $this->__invoke( $payload );
    }

    /**
     * Resolves the concrete Media model from the incoming payload.
     *
     * @since 1.0.0
     */
    protected function resolveMedia( object $payload ): ?Model
    {
        if ( $payload instanceof Model ) {
            return $payload;
        }

        if ( property_exists( $payload, 'media' ) && $payload->media instanceof Model ) {
            return $payload->media;
        }

        return null;
    }

    /**
     * Reports whether the given media row represents an image.
     *
     * Prefers the model's own `isImage()` helper (media-library ships one);
     * falls back to a mime-type inspection when the helper isn't available
     * so consumers using a custom model still get the guard.
     *
     * @since 1.0.0
     */
    protected function isImageMedia( Model $media ): bool
    {
        if ( method_exists( $media, 'isImage' ) ) {
            return (bool) $media->isImage();
        }

        $mime = $media->getAttribute( 'mime_type' );

        return is_string( $mime ) && str_starts_with( $mime, 'image/' );
    }

    /**
     * Builds the option array forwarded to `OptimizeMediaJob`.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function buildJobOptions(): array
    {
        $sizes = (array) config( 'artisanpack.performance.images.sizes', [] );

        $formats = [];

        foreach ( (array) config( 'artisanpack.performance.images.formats', [] ) as $key => $settings ) {
            if ( ! empty( $settings['enabled'] ) ) {
                $formats[] = (string) $key;
            }
        }

        $extract = (bool) config( 'artisanpack.performance.images.dominant_color.enabled', true );

        return [
            'sizes'                  => array_values( array_map( 'intval', $sizes ) ),
            'formats'                => $formats,
            'extract_dominant_color' => $extract,
        ];
    }
}
