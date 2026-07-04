<?php

/**
 * Optimize image queued job.
 *
 * Runs the full image optimization pipeline (resize + format conversion)
 * against a single source image. Designed to be dispatched after upload so
 * the request thread doesn't block on encoding. The pipeline dispatches
 * `ImageOptimized` when it produces at least one variant.
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

/**
 * Optimize image job class.
 *
 *
 * @since      1.0.0
 */
class OptimizeImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     *
     * Resolved from `artisanpack.performance.images.jobs.tries` so applications
     * can tune retry counts without subclassing.
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
     * @param  array<string, mixed>  $options  Optimization overrides forwarded to `ImageService::optimize()`.
     */
    public function __construct(
        public string $path,
        public array $options = [],
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
        $images->optimize( $this->path, $this->options );
    }
}
