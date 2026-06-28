<?php

/**
 * Cache invalidator.
 *
 * Single entry point for purging cached data managed by the package.
 * Delegates the actual invalidation work to `PageCacheManager` and
 * `FragmentCache` so callers (Artisan commands, model event listeners,
 * deploy hooks) don't need to know which subsystem owns which key.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache invalidator class.
 *
 *
 * @since      1.0.0
 */
class CacheInvalidator
{
    /**
     * The page cache manager.
     *
     * @since 1.0.0
     */
    protected PageCacheManager $pageCache;

    /**
     * The fragment cache.
     *
     * @since 1.0.0
     */
    protected FragmentCache $fragmentCache;

    /**
     * Creates a new invalidator instance.
     *
     * @since 1.0.0
     *
     * @param  PageCacheManager  $pageCache  Container-resolved page cache manager.
     * @param  FragmentCache  $fragmentCache  Container-resolved fragment cache.
     */
    public function __construct( PageCacheManager $pageCache, FragmentCache $fragmentCache )
    {
        $this->pageCache     = $pageCache;
        $this->fragmentCache = $fragmentCache;
    }

    /**
     * Invalidates page cache entries matching the given pattern.
     *
     * Returns the number of entries removed and logs the invalidation. The
     * pattern is forwarded verbatim to `PageCacheManager::invalidatePageCache`
     * which delegates wildcard handling to the shared URL pattern matcher.
     *
     * @since 1.0.0
     *
     * @param  string  $pattern  Path pattern.
     */
    public function invalidatePagePattern( string $pattern ): int
    {
        $count = $this->pageCache->invalidatePageCache( $pattern );

        $this->log( 'page-cache:pattern', [
            'pattern' => $pattern,
            'count'   => $count,
        ] );

        return $count;
    }

    /**
     * Invalidates fragment cache entries registered under the given tag.
     *
     * @since 1.0.0
     *
     * @param  string  $tag  Tag name.
     */
    public function invalidateFragmentTag( string $tag ): int
    {
        $count = $this->fragmentCache->invalidateByTag( $tag );

        $this->log( 'fragment-cache:tag', [
            'tag'   => $tag,
            'count' => $count,
        ] );

        return $count;
    }

    /**
     * Flushes every page cache entry the package wrote.
     *
     * @since 1.0.0
     */
    public function flushPageCache(): int
    {
        $count = $this->pageCache->flushPageCache();

        $this->log( 'page-cache:flush', [
            'count' => $count,
        ] );

        return $count;
    }

    /**
     * Purges both page and fragment caches in a single call.
     *
     * Useful as a deploy hook. Returns a map containing the count of entries
     * removed per subsystem so callers can surface a summary.
     *
     * @since 1.0.0
     *
     * @return array{page: int, fragments: int}
     */
    public function purgeAll(): array
    {
        $pages = $this->pageCache->flushPageCache();

        // Iterate over every known tag and forget its entries. We don't expose
        // a `flushAll()` on FragmentCache because the entries can be co-tenant
        // with adjacent app code on the same store, so cleaning by tag keeps
        // the package's deletes scoped to keys it actually tracked.
        $fragments = 0;

        foreach ( $this->fragmentTags() as $tag ) {
            $fragments += $this->fragmentCache->invalidateByTag( $tag );
        }

        $this->log( 'cache:purge-all', [
            'page'      => $pages,
            'fragments' => $fragments,
        ] );

        return [
            'page'      => $pages,
            'fragments' => $fragments,
        ];
    }

    /**
     * Returns every tag currently known to the fragment cache.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function fragmentTags(): array
    {
        $store = Cache::store( config( 'artisanpack.performance.fragment_cache.driver' ) );

        $index = $store->get( FragmentCache::TAG_INDEX_KEY, [] );

        return is_array( $index ) ? array_values( array_filter( array_keys( $index ), 'is_string' ) ) : [];
    }

    /**
     * Emits a structured log line at debug level.
     *
     * @since 1.0.0
     *
     * @param  string  $event  Event name.
     * @param  array<string, mixed>  $context  Event context.
     */
    protected function log( string $event, array $context ): void
    {
        $channel = (string) config(
            'artisanpack.performance.cache_invalidation.log_channel',
            (string) config( 'logging.default', 'stack' ),
        );

        Log::channel( $channel )->debug( "cache-invalidator: {$event}", $context );
    }
}
