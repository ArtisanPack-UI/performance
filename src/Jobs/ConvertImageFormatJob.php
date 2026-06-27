<?php

/**
 * Convert image format queued job.
 *
 * Converts a single source image to one target format (WebP or AVIF) via
 * `ImageService::convertFormat`. Designed to be chained with other jobs in
 * the package's optimization pipeline.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Jobs;

use ArtisanPackUI\Performance\Services\ImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Convert image format job class.
 *
 *
 * @since      1.0.0
 */
class ConvertImageFormatJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     *
     * @since 1.0.0
     */
    public int $tries;

    /**
     * Seconds between retry attempts.
     *
     * @since 1.0.0
     */
    public int $backoff;

    /**
     * Creates a new job instance.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  string  $format  Target format (`webp` or `avif`).
     * @param  int|null  $quality  Output quality (0-100). Defaults to the configured format quality.
     */
    public function __construct(
        public string $path,
        public string $format,
        public ?int $quality = null,
    ) {
        $this->onQueue( (string) config( 'artisanpack.performance.images.queue', 'default' ) );
        $this->tries   = (int) config( 'artisanpack.performance.images.jobs.tries', 3 );
        $this->backoff = (int) config( 'artisanpack.performance.images.jobs.backoff', 30 );
    }

    /**
     * Executes the job.
     *
     * @since 1.0.0
     *
     * @param  ImageService  $images  Image service resolved from the container.
     */
    public function handle( ImageService $images ): void
    {
        $format = strtolower( $this->format );

        if ( ! $images->supportsFormat( $format ) ) {
            // Driver can't encode this format on this host — skip rather than
            // fail so the rest of the chain still runs. Emit an info log so
            // operators see the skip rather than a silent no-op (otherwise
            // the chain looks like it ran successfully).
            Log::info( 'ConvertImageFormatJob skipped: driver cannot encode format', [
                'path'   => $this->path,
                'format' => $format,
            ] );

            return;
        }

        $quality = $this->quality
            ?? (int) config( "artisanpack.performance.images.formats.{$format}.quality", 80 );

        $images->convertFormat( $this->path, $format, $quality);
    }
}
