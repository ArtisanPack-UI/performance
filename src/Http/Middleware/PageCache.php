<?php

/**
 * Page cache middleware.
 *
 * Wraps the request lifecycle around the `PageCacheManager` to serve cached
 * responses for cacheable requests and store fresh responses on the way out.
 * Applied per-route or via a route group; the package does not register the
 * middleware globally so applications keep control over which routes opt in.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Middleware;

use ArtisanPackUI\Performance\Cache\PageCacheManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page cache middleware class.
 *
 *
 * @since      1.0.0
 */
class PageCache
{
    /**
     * The page cache manager.
     *
     * @since 1.0.0
     */
    protected PageCacheManager $manager;

    /**
     * Creates a new middleware instance.
     *
     * @since 1.0.0
     *
     * @param  PageCacheManager  $manager  Container-resolved manager.
     */
    public function __construct( PageCacheManager $manager )
    {
        $this->manager = $manager;
    }

    /**
     * Handles the request.
     *
     * Skips entirely when the page-cache feature is off so applications that
     * leave the middleware wired up but disable the feature mid-incident
     * don't pay any cache lookup cost.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The incoming request.
     * @param  Closure  $next  Next middleware in the pipeline.
     */
    public function handle( Request $request, Closure $next ): Response
    {
        if ( ! $this->isFeatureEnabled() ) {
            return $next( $request );
        }

        if ( ! $this->manager->isRequestCacheable( $request ) ) {
            return $next( $request );
        }

        $cached = $this->manager->getCachedResponse( $request );

        if ( null !== $cached ) {
            $this->logHit( $request );

            return $this->buildHitResponse( $cached );
        }

        $response = $next( $request );

        $this->manager->cacheResponse( $request, $response );

        $response->headers->set( 'X-Page-Cache', 'MISS' );

        $this->logMiss( $request );

        return $response;
    }

    /**
     * Reports whether the page-cache feature toggle is on.
     *
     * @since 1.0.0
     */
    protected function isFeatureEnabled(): bool
    {
        return (bool) config( 'artisanpack.performance.features.page_cache', false )
            && (bool) config( 'artisanpack.performance.page_cache.enabled', true );
    }

    /**
     * Reconstructs a response from a cached payload.
     *
     * The header bag stored by `PageCacheManager::preservableHeaders()` is a
     * `header => list<string>` map. Multi-value headers (`Link` for
     * preload/preconnect chains, `Vary` when emitted line-per-line) are
     * replayed by `set($name, $value, replace: false)` for every entry after
     * the first so they survive the round trip intact.
     *
     * @since 1.0.0
     *
     * @param  array{status: int, content: string, headers: array<string, array<int, string>|string>}  $payload  Cached response payload.
     */
    protected function buildHitResponse( array $payload ): Response
    {
        $response = new IlluminateResponse(
            $payload['content'] ?? '',
            $payload['status'] ?? 200,
        );

        foreach ( $payload['headers'] ?? [] as $name => $value ) {
            if ( ! is_string( $name ) ) {
                continue;
            }

            $values = is_array( $value ) ? $value : [ $value ];
            $first  = true;

            foreach ( $values as $entry ) {
                if ( ! is_string( $entry ) ) {
                    continue;
                }

                $response->headers->set( $name, $entry, $first );
                $first = false;
            }
        }

        $response->headers->set( 'X-Page-Cache', 'HIT' );

        return $response;
    }

    /**
     * Logs a cache hit at debug level.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function logHit( Request $request ): void
    {
        Log::channel( $this->logChannel() )->debug( 'page-cache: HIT', [
            'path' => $request->path(),
        ] );
    }

    /**
     * Logs a cache miss at debug level.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function logMiss( Request $request ): void
    {
        Log::channel( $this->logChannel() )->debug( 'page-cache: MISS', [
            'path' => $request->path(),
        ] );
    }

    /**
     * Returns the log channel to write cache events to.
     *
     * Defaults to the application's default channel when no dedicated
     * channel has been configured.
     *
     * @since 1.0.0
     */
    protected function logChannel(): string
    {
        return (string) config(
            'artisanpack.performance.page_cache.log_channel',
            (string) config( 'logging.default', 'stack' ),
        );
    }
}
