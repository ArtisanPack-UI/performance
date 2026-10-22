<?php

/**
 * Warm page cache queued job.
 *
 * Runs a page cache warming pass from a queue worker so the HTTP requests
 * the warmer issues against the app's own URLs never hold a web worker
 * while they wait for another one (which can deadlock a small PHP-FPM
 * pool). Dispatched by the admin dashboard surfaces through
 * `PageCacheManager::queueWarmPageCache()`.
 *
 * The job is unique per URL set, so repeated clicks on the dashboard's
 * warm button while a pass is pending don't stack duplicate passes.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Jobs;

use ArtisanPackUI\Performance\Cache\PageCacheManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Warm page cache job class.
 *
 *
 * @since      1.2.0
 */
class WarmPageCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Number of times the job may be attempted.
     *
     * Warming is best-effort and every URL already reports its own failure,
     * so a retry would only re-request URLs that already succeeded.
     *
     * @since 1.2.0
     */
    public int $tries = 1;

    /**
     * Seconds the uniqueness lock is held at most.
     *
     * Bounds the lock if a worker dies mid-pass so warming isn't blocked
     * for the same URL set indefinitely.
     *
     * @since 1.2.0
     */
    public int $uniqueFor = 600;

    /**
     * Seconds the worker may spend on this pass before killing it.
     *
     * Sized to the URL list in the constructor, since URLs are requested
     * one after another and each can use the full request timeout plus
     * the inter-request delay. Overrides `queue:work --timeout`; the queue
     * connection's `retry_after` must stay above it, or a long pass can be
     * released to another worker while it is still running.
     *
     * @since 1.2.0
     */
    public int $timeout;

    /**
     * Creates a new job instance.
     *
     * @since 1.2.0
     *
     * @param  array<int, string>  $urls  Absolute or relative URLs to warm.
     */
    public function __construct(
        public array $urls,
    ) {
        $connection = config( 'artisanpack.performance.cache_warming.connection' );

        if ( is_string( $connection ) && '' !== $connection ) {
            $this->onConnection( $connection );
        }

        $this->onQueue( (string) config( 'artisanpack.performance.cache_warming.queue', 'default' ) );

        $perUrlSeconds = max( 1, (int) config( 'artisanpack.performance.cache_warming.timeout', 10 ) )
            + max( 0, (int) config( 'artisanpack.performance.cache_warming.delay_ms', 0 ) ) / 1000;

        $this->timeout   = (int) ceil( count( $urls ) * $perUrlSeconds ) + 30;
        $this->uniqueFor = max( $this->uniqueFor, $this->timeout + 30 );
    }

    /**
     * Returns the uniqueness key for this URL set.
     *
     * @since 1.2.0
     */
    public function uniqueId(): string
    {
        return sha1( implode( '|', $this->urls ) );
    }

    /**
     * Executes the job.
     *
     * Paces requests with `cache_warming.delay_ms` so a warming pass does
     * not flood the origin the worker is requesting.
     *
     * @since 1.2.0
     *
     * @param  PageCacheManager  $manager  Page cache manager resolved from the container.
     */
    public function handle( PageCacheManager $manager ): void
    {
        $delayMs = max( 0, (int) config( 'artisanpack.performance.cache_warming.delay_ms', 0 ) );

        $manager->warmPageCache(
            $this->urls,
            static function () use ( $delayMs ): void {
                if ( $delayMs > 0 ) {
                    usleep( $delayMs * 1000 );
                }
            },
        );
    }
}
