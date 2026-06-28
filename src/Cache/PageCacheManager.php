<?php

/**
 * Page cache manager.
 *
 * Caches full HTML responses keyed by request path + vary headers and serves
 * them back on subsequent matching requests. Pattern-based invalidation is
 * supported on every driver via a per-store key index, since most Laravel
 * cache stores (file, database, array) don't expose native pattern deletion.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache;

use ArtisanPackUI\Performance\Events\CachePurged;
use ArtisanPackUI\Performance\Events\CacheWarmed;
use ArtisanPackUI\Performance\Speculative\UrlPatternMatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Page cache manager class.
 *
 *
 * @since      1.0.0
 */
class PageCacheManager
{
    /**
     * Cache key prefix used to namespace every entry the manager writes.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const KEY_PREFIX = 'perf:page:';

    /**
     * Cache key holding the manager's pattern-invalidation index.
     *
     * The index is a flat map of `cacheKey => path` so pattern-based
     * invalidation can scan only the keys this manager owns instead of
     * trying to glob the entire cache store (which most drivers can't
     * do anyway).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const INDEX_KEY = 'perf:page-index';

    /**
     * Maximum number of seconds the key index lives for.
     *
     * Set to one year so the index outlives any reasonable page-cache TTL
     * — the index entries are only removed when the underlying cache entry
     * is invalidated, so the index itself must not silently expire under it.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const INDEX_TTL = 31536000;

    /**
     * Stores a rendered response for the given request.
     *
     * No-ops when the request is not cacheable (POST/PUT/DELETE, authenticated
     * users when configured, excluded paths). The cached entry holds the
     * response status, the body, and a small subset of safe headers so the
     * exact response can be reconstructed on a HIT.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     * @param  Response  $response  Outgoing response.
     */
    public function cacheResponse( Request $request, Response $response ): void
    {
        if ( ! $this->isRequestCacheable( $request ) ) {
            return;
        }

        if ( ! $this->isResponseCacheable( $response ) ) {
            return;
        }

        $key     = $this->cacheKeyFor( $request );
        $payload = [
            'status'  => $response->getStatusCode(),
            'content' => (string) $response->getContent(),
            'headers' => $this->preservableHeaders( $response ),
        ];

        $this->store()->put( $key, $payload, $this->ttl() );

        $this->trackKey( $key, ltrim( $request->path(), '/' ) );
    }

    /**
     * Returns the cached response payload for the given request, if any.
     *
     * Returns `null` when no entry exists or when the request itself is not
     * cacheable. The caller (the PageCache middleware) is responsible for
     * reconstructing a `Response` from the payload — keeping that boundary
     * here means tests can inspect the raw payload without touching HTTP
     * symfony objects.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     *
     * @return array{status: int, content: string, headers: array<string, string>}|null
     */
    public function getCachedResponse( Request $request ): ?array
    {
        if ( ! $this->isRequestCacheable( $request ) ) {
            return null;
        }

        $payload = $this->store()->get( $this->cacheKeyFor( $request ) );

        return is_array( $payload ) ? $payload : null;
    }

    /**
     * Invalidates cached pages matching the given pattern.
     *
     * The pattern matches against the request path (without the leading
     * slash). Wildcards are delegated to `UrlPatternMatcher`, so callers can
     * use globs like `products/*` or `*.html`. Returns the number of entries
     * removed and dispatches `CachePurged` with the cache keys that were
     * forgotten.
     *
     * @since 1.0.0
     *
     * @param  string  $pattern  Path pattern (no leading slash required).
     *
     * @return int Number of entries removed.
     */
    public function invalidatePageCache( string $pattern ): int
    {
        $normalized = ltrim( $pattern, '/' );

        if ( '' === $normalized ) {
            return 0;
        }

        $purged = [];

        $this->withIndexLock( function () use ( $normalized, &$purged ): void {
            $index   = $this->readIndex();
            $updated = $index;

            foreach ( $index as $cacheKey => $path ) {
                if ( ! is_string( $cacheKey ) || ! is_string( $path ) ) {
                    continue;
                }

                if ( UrlPatternMatcher::matches( $path, $normalized ) ) {
                    $this->store()->forget( $cacheKey );
                    $purged[] = $cacheKey;
                    unset( $updated[ $cacheKey ] );
                }
            }

            if ( ! empty( $purged ) ) {
                $this->writeIndex( $updated );
            }
        } );

        if ( empty( $purged ) ) {
            return 0;
        }

        CachePurged::dispatch( $purged, "page-cache:pattern:{$normalized}" );

        return count( $purged );
    }

    /**
     * Flushes every page cache entry written by the manager.
     *
     * Walks the key index rather than flushing the whole store so neighboring
     * cache data (fragments, sessions, query cache) is left untouched. Returns
     * the number of entries removed and dispatches `CachePurged`.
     *
     * @since 1.0.0
     */
    public function flushPageCache(): int
    {
        $purged = [];

        $this->withIndexLock( function () use ( &$purged ): void {
            $index = $this->readIndex();

            if ( empty( $index ) ) {
                return;
            }

            $store = $this->store();

            foreach ( array_keys( $index ) as $cacheKey ) {
                if ( ! is_string( $cacheKey ) ) {
                    continue;
                }

                $store->forget( $cacheKey );
                $purged[] = $cacheKey;
            }

            $this->writeIndex( [] );
        } );

        if ( empty( $purged ) ) {
            return 0;
        }

        CachePurged::dispatch( $purged, 'page-cache:flush' );

        return count( $purged );
    }

    /**
     * Warms the page cache by issuing HTTP GET requests for each URL.
     *
     * The warmer drives requests through the public HTTP stack rather than
     * synthesizing requests internally — that way the regular middleware
     * pipeline (including this manager's PageCache middleware) runs and the
     * cached entries written are byte-identical to what a real client would
     * receive. Returns a result map keyed by URL so callers (the warming
     * command, schedulers) can report success / failure per URL.
     *
     * A `CacheWarmed` event is dispatched ONCE per `warmPageCache()` call
     * with the full URL list, regardless of batch size. The optional
     * `$onProgress` callback fires after each URL so CLI consumers can
     * advance a progress bar without duplicating the HTTP loop (and without
     * fanning the event listeners by calling `warmPageCache()` per URL).
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $urls  Absolute or relative URLs to warm.
     * @param  callable|null  $onProgress  Optional callback `(string $url, array $result): void`.
     *
     * @return array<string, array{status: int|null, ok: bool, error: string|null}>
     */
    public function warmPageCache( array $urls, ?callable $onProgress = null ): array
    {
        $results = [];
        $warmed  = 0;

        foreach ( $urls as $url ) {
            $url = is_string( $url ) ? trim( $url ) : '';

            if ( '' === $url ) {
                continue;
            }

            $absolute = $this->absoluteUrl( $url );

            try {
                $response = Http::get( $absolute );

                $entry = [
                    'status' => $response->status(),
                    'ok'     => $response->successful(),
                    'error'  => null,
                ];

                if ( $response->successful() ) {
                    $warmed++;
                }
            } catch ( Throwable $exception ) {
                $entry = [
                    'status' => null,
                    'ok'     => false,
                    'error'  => $exception->getMessage(),
                ];
            }

            $results[ $url ] = $entry;

            if ( null !== $onProgress ) {
                $onProgress( $url, $entry );
            }
        }

        CacheWarmed::dispatch( array_keys( $results ), $warmed );

        return $results;
    }

    /**
     * Builds the cache key for the given request.
     *
     * The key incorporates the request method, full path with query string
     * (when query-string caching is enabled), and a digest of the configured
     * `vary_by` header values so requests that differ only in `Accept-Encoding`
     * land on distinct entries.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    public function cacheKeyFor( Request $request ): string
    {
        $path = ltrim( $request->path(), '/' );

        $components = [ $request->getMethod(), $path ];

        if ( (bool) config( 'artisanpack.performance.page_cache.cache_query_strings', false ) ) {
            $query = $request->getQueryString();

            if ( null !== $query && '' !== $query ) {
                // Sort the query string by key so /products?b=2&a=1 and
                // /products?a=1&b=2 collapse to the same cache entry.
                parse_str( $query, $parsed );
                ksort( $parsed );
                $components[] = http_build_query( $parsed );
            }
        }

        $varyBy = (array) config( 'artisanpack.performance.page_cache.vary_by', [] );

        foreach ( $varyBy as $header ) {
            if ( ! is_string( $header ) || '' === $header ) {
                continue;
            }

            $components[] = $header . '=' . (string) $request->headers->get( $header, '' );
        }

        return self::KEY_PREFIX . sha1( implode( '|', $components ) );
    }

    /**
     * Reports whether the given request is eligible for caching.
     *
     * Honors:
     * - Method allow-list (GET / HEAD only).
     * - `page_cache.exclude_routes` glob list.
     * - `page_cache.exclude_when` rules (authenticated, has_flash).
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    public function isRequestCacheable( Request $request ): bool
    {
        $method = strtoupper( $request->getMethod() );

        if ( ! in_array( $method, ['GET', 'HEAD'], true ) ) {
            return false;
        }

        if ( $this->matchesExcludedRoute( $request ) ) {
            return false;
        }

        if ( $this->matchesExcludeWhen( $request ) ) {
            return false;
        }

        return true;
    }

    /**
     * Reports whether the given response is eligible for caching.
     *
     * Page cache is HTML-only by design — JSON / XML / binary responses are
     * out of scope. The eligibility rules:
     * - 2xx status (caching error pages would lock users out of recovery).
     * - Content-Type starts with `text/html` or `application/xhtml`. JSON is
     *   excluded specifically because the default cache key omits query
     *   strings, which would cause `/api/me?user=1` and `?user=2` to collide
     *   and leak across users.
     * - Cache-Control must not include `no-store`. This is the strongest
     *   "do not persist anywhere" directive — and unlike `private` /
     *   `no-cache`, it isn't part of Symfony's default response header set,
     *   so honoring it doesn't accidentally disable caching for every plain
     *   Laravel response. Routes that need finer-grained opt-out should set
     *   `Cache-Control: no-store` explicitly or use the `exclude_routes` /
     *   `exclude_when` config.
     * - Body must be non-empty. Empty-body 200 responses (a transient bug or
     *   a misconfigured route) would otherwise be served as blank pages for
     *   the full TTL.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     */
    public function isResponseCacheable( Response $response ): bool
    {
        $status = $response->getStatusCode();

        if ( $status < 200 || $status >= 300 ) {
            return false;
        }

        $contentType = (string) $response->headers->get( 'Content-Type', '' );

        if ( '' === $contentType || 1 !== preg_match( '#^(text/html|application/xhtml)#i', $contentType ) ) {
            return false;
        }

        if ( $this->responseOptsOut( $response ) ) {
            return false;
        }

        $body = (string) $response->getContent();

        if ( '' === $body ) {
            return false;
        }

        return true;
    }

    /**
     * Returns the configured TTL for page cache entries.
     *
     * @since 1.0.0
     */
    public function ttl(): int
    {
        return (int) config( 'artisanpack.performance.page_cache.ttl', 3600 );
    }

    /**
     * Reports whether the response's Cache-Control header opts out of caching.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     */
    protected function responseOptsOut( Response $response ): bool
    {
        $cacheControl = strtolower( (string) $response->headers->get( 'Cache-Control', '' ) );

        if ( '' === $cacheControl ) {
            return false;
        }

        // Only honor `no-store`. Symfony's default Response carries
        // `Cache-Control: no-cache, private`, so treating those as opt-out
        // would refuse to cache any standard Laravel response. `no-store`
        // is the unambiguous "never persist" directive.
        return false !== strpos( $cacheControl, 'no-store' );
    }

    /**
     * Reports whether the request path matches a configured exclude pattern.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function matchesExcludedRoute( Request $request ): bool
    {
        $patterns = (array) config( 'artisanpack.performance.page_cache.exclude_routes', [] );

        if ( empty( $patterns ) ) {
            return false;
        }

        $path = ltrim( $request->path(), '/' );

        foreach ( $patterns as $pattern ) {
            if ( ! is_string( $pattern ) || '' === $pattern ) {
                continue;
            }

            if ( UrlPatternMatcher::matches( $path, ltrim( $pattern, '/' ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether any configured "exclude_when" rule applies.
     *
     * Supports the following rule keywords:
     * - `authenticated` — skip caching when an auth user is resolved.
     * - `has_flash`     — skip caching when the session has flashed data.
     *
     * Unknown keywords are ignored rather than throwing, so users can
     * gradually add new rules without the package having to recognize
     * every one up front.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function matchesExcludeWhen( Request $request ): bool
    {
        $rules = (array) config( 'artisanpack.performance.page_cache.exclude_when', [] );

        foreach ( $rules as $rule ) {
            if ( 'authenticated' === $rule && $this->isAuthenticated( $request ) ) {
                return true;
            }

            if ( 'has_flash' === $rule && $this->hasFlashedData( $request ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether the request has an authenticated user.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function isAuthenticated( Request $request ): bool
    {
        try {
            return null !== $request->user();
        } catch ( Throwable ) {
            return false;
        }
    }

    /**
     * Reports whether the request session holds any flashed data.
     *
     * Laravel's session middleware always seeds `_flash.new` and `_flash.old`
     * as arrays even when no flash data was actually written, so a naive
     * "does any `_flash*` key exist" check would treat every browser request
     * as flashed and refuse to cache. The real signal is non-empty content
     * in either bucket: `_flash.new` lists keys flashed for the NEXT request,
     * `_flash.old` lists keys flashed for the CURRENT request (the one the
     * user is about to render). Caching a response with old-flash content
     * would freeze a one-shot success/error banner into the cached page, so
     * either bucket being populated is enough to opt out.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function hasFlashedData( Request $request ): bool
    {
        if ( ! $request->hasSession() ) {
            return false;
        }

        $session = $request->session();

        if ( ! method_exists( $session, 'get' ) ) {
            return false;
        }

        $new = (array) $session->get( '_flash.new', [] );
        $old = (array) $session->get( '_flash.old', [] );

        return ! empty( $new ) || ! empty( $old );
    }

    /**
     * Returns the subset of response headers safe to round-trip through cache.
     *
     * Returns a `header => array<string>` map so multi-value headers (most
     * commonly `Link` for preload/preconnect chains, but also `Vary` when an
     * app emits it as separate header lines instead of comma-joined) survive
     * the round trip. Headers like `Set-Cookie`, `Date`, and `Cache-Control`
     * either change per-request or would override the fresh response's own
     * headers when rehydrated, so they're stripped here.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     *
     * @return array<string, array<int, string>>
     */
    protected function preservableHeaders( Response $response ): array
    {
        $preserved = [];

        $allow = [ 'content-type', 'content-language', 'link', 'vary' ];

        foreach ( $allow as $header ) {
            $values = $response->headers->all( $header );

            if ( ! is_array( $values ) || empty( $values ) ) {
                continue;
            }

            $filtered = array_values( array_filter(
                $values,
                static fn ( $value ): bool => is_string( $value ) && '' !== $value,
            ) );

            if ( ! empty( $filtered ) ) {
                $preserved[ $header ] = $filtered;
            }
        }

        return $preserved;
    }

    /**
     * Builds an absolute URL for the warmer to GET.
     *
     * @since 1.0.0
     *
     * @param  string  $url  Relative or absolute URL.
     */
    protected function absoluteUrl( string $url ): string
    {
        if ( str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' ) ) {
            return $url;
        }

        $base = rtrim( (string) config( 'app.url', '' ), '/' );

        if ( '' === $base ) {
            return $url;
        }

        return $base . '/' . ltrim( $url, '/' );
    }

    /**
     * Records the cache key in the index so it can be located later.
     *
     * Wrapped in a cache lock so concurrent MISSes don't lose each other's
     * index entries — without the lock, two read-modify-writes that race
     * would each insert their own key against the same prior snapshot, and
     * the last writer would silently drop the other's entry, orphaning the
     * underlying cache payload from `flushPageCache()` / `invalidatePageCache()`.
     *
     * @since 1.0.0
     *
     * @param  string  $cacheKey  Cache key written to the store.
     * @param  string  $path  Normalized request path.
     */
    protected function trackKey( string $cacheKey, string $path ): void
    {
        $this->withIndexLock( function () use ( $cacheKey, $path ): void {
            $index              = $this->readIndex();
            $index[ $cacheKey ] = $path;
            $this->writeIndex( $index );
        } );
    }

    /**
     * Runs the callback while holding the package's index lock.
     *
     * Falls back to executing the callback unguarded when the configured
     * cache store doesn't expose locking (`array` driver, custom drivers
     * without `LockProvider`) — those scenarios are either tests (where the
     * race can't fire because PHP is single-threaded inside a test) or
     * explicit operator choice, so the alternative — throwing — would do
     * more damage than a best-effort write.
     *
     * @since 1.0.0
     *
     * @param  callable  $callback  Operation to perform under the lock.
     */
    protected function withIndexLock( callable $callback ): void
    {
        try {
            $lock = $this->store()->lock( self::INDEX_KEY . ':lock', 5 );
        } catch ( Throwable ) {
            $callback();

            return;
        }

        try {
            $lock->block( 3, $callback );
        } catch ( Throwable ) {
            // Lock acquisition timed out or the driver doesn't actually
            // support locks at runtime — fall through to a best-effort
            // unguarded write rather than dropping the index update.
            $callback();
        }
    }

    /**
     * Returns the current key index from cache.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function readIndex(): array
    {
        $index = $this->store()->get( self::INDEX_KEY, [] );

        return is_array( $index ) ? $index : [];
    }

    /**
     * Persists the key index back to cache.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $index  Key index payload.
     */
    protected function writeIndex( array $index ): void
    {
        $this->store()->put( self::INDEX_KEY, $index, self::INDEX_TTL );
    }

    /**
     * Resolves the cache store used for page cache entries.
     *
     * @since 1.0.0
     */
    protected function store(): Repository
    {
        return Cache::store( config( 'artisanpack.performance.page_cache.driver' ) );
    }
}
