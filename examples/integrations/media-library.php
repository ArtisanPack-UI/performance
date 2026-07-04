<?php

/**
 * Media library integration.
 *
 * When both `artisanpack-ui/media-library` and
 * `artisanpack-ui/performance` are installed, the performance package
 * ships a listener that subscribes to the media library's `MediaUploaded`
 * event and dispatches the image optimization pipeline for the new
 * asset.
 *
 * This example wires the integration explicitly so a host can subclass
 * or intercept the listener.
 */

namespace App\Providers;

use ArtisanPackUI\MediaLibrary\Events\MediaUploaded;
use ArtisanPackUI\Performance\Jobs\OptimizeImageJob;
use ArtisanPackUI\Performance\Listeners\OptimizeUploadedMedia;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MediaIntegrationProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Option 1 — use the shipped listener as-is.
        Event::listen( MediaUploaded::class, OptimizeUploadedMedia::class );

        // Option 2 — inline handler with custom filtering.
        Event::listen( function ( MediaUploaded $event ): void {
            if ( ! str_starts_with( (string) $event->media->mime_type, 'image/' ) ) {
                return;
            }

            OptimizeImageJob::dispatch(
                $event->media->disk,
                $event->media->path,
                sizes: [ 'thumbnail', 'medium', 'large', 'product-thumb' ],
            )->onQueue( 'images' );
        } );
    }
}

/*
 * The media library's Media model gains a `->url('webp')` helper when
 * both packages are installed:
 *
 *   $media = Media::find(42);
 *   $media->url();             // Original.
 *   $media->url('webp');       // WebP variant, generated if missing.
 *   $media->imageUrl('medium', 'avif'); // AVIF medium.
 */
