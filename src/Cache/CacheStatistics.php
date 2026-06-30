<?php

/**
 * Cache statistics service.
 *
 * Exposes a read-only summary of the page and fragment caches managed
 * by the package — entry counts and tag lists drawn from the existing
 * pattern-invalidation indexes, plus a sample list of entry keys for
 * dashboard display. Size and hit/miss rates are not tracked by the
 * underlying managers and are surfaced here as `null` rather than
 * fabricated.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Cache statistics class.
 *
 *
 * @since      1.0.0
 */
class CacheStatistics
{
    /**
     * Returns the page cache summary.
     *
     * `entries` is the count of cached responses derived from the
     * pattern-invalidation index. `size_bytes` is null because the
     * underlying manager does not track byte sizes (most cache stores
     * do not expose them either). `hit_rate` is null for the same
     * reason — adding counters would require touching the hot read
     * path, which is out of scope for the dashboard's read-only view.
     *
     * @since 1.0.0
     *
     * @return array{entries: int, size_bytes: int|null, hit_rate: float|null}
     */
    public function pageSummary(): array
    {
        $index = $this->readPageIndex();

        return [
            'entries'    => count( $index ),
            'size_bytes' => null,
            'hit_rate'   => null,
        ];
    }

    /**
     * Returns the fragment cache summary.
     *
     * `tags` counts the distinct tags currently registered against
     * fragment entries; `entries` is the deduplicated count of cache
     * keys across every tag. Both come from the tag side-index that
     * `FragmentCache` maintains for invalidation lookups.
     *
     * @since 1.0.0
     *
     * @return array{entries: int, tags: int, size_bytes: int|null, hit_rate: float|null}
     */
    public function fragmentSummary(): array
    {
        $index = $this->readFragmentIndex();

        $keys = [];

        foreach ( $index as $tag => $tagKeys ) {
            if ( ! is_array( $tagKeys ) ) {
                continue;
            }

            foreach ( $tagKeys as $key ) {
                if ( is_string( $key ) ) {
                    $keys[ $key ] = true;
                }
            }
        }

        return [
            'entries'    => count( $keys ),
            'tags'       => count( $index ),
            'size_bytes' => null,
            'hit_rate'   => null,
        ];
    }

    /**
     * Returns a sample of cached page entries for display.
     *
     * The dashboard renders these as a table; the sample size is
     * bounded so dashboards on large caches stay responsive. The
     * returned shape is `[{ key, path }, …]` so the UI can show the
     * cache key alongside the request path it serves.
     *
     * @since 1.0.0
     *
     * @param  int  $limit  Maximum number of entries to return.
     *
     * @return array<int, array{key: string, path: string}>
     */
    public function pageEntries( int $limit = 50 ): array
    {
        $index   = $this->readPageIndex();
        $entries = [];
        $i       = 0;

        foreach ( $index as $key => $path ) {
            if ( $i >= $limit ) {
                break;
            }

            if ( ! is_string( $key ) ) {
                continue;
            }

            $entries[] = [
                'key'  => $key,
                'path' => is_string( $path ) ? $path : '',
            ];

            $i++;
        }

        return $entries;
    }

    /**
     * Returns the distinct tags currently registered against fragments.
     *
     * The returned array has the shape `[{ tag, entry_count }, …]` so
     * the dashboard can show how broad each tag's invalidation reach is.
     *
     * @since 1.0.0
     *
     * @return array<int, array{tag: string, entry_count: int}>
     */
    public function fragmentTags(): array
    {
        $index = $this->readFragmentIndex();
        $tags  = [];

        foreach ( $index as $tag => $keys ) {
            if ( ! is_string( $tag ) || ! is_array( $keys ) ) {
                continue;
            }

            $tags[] = [
                'tag'         => $tag,
                'entry_count' => count( array_filter( $keys, 'is_string' ) ),
            ];
        }

        return $tags;
    }

    /**
     * Reads the page cache pattern-invalidation index.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function readPageIndex(): array
    {
        $store = Cache::store( config( 'artisanpack.performance.page_cache.driver' ) );

        $index = $store->get( PageCacheManager::INDEX_KEY, [] );

        return is_array( $index ) ? $index : [];
    }

    /**
     * Reads the fragment cache tag side-index.
     *
     * @since 1.0.0
     *
     * @return array<string, array<int, string>>
     */
    protected function readFragmentIndex(): array
    {
        $store = Cache::store( config( 'artisanpack.performance.fragment_cache.driver' ) );

        $index = $store->get( FragmentCache::TAG_INDEX_KEY, [] );

        return is_array( $index ) ? $index : [];
    }
}
