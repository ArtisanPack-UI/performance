<?php

/**
 * Fragment cache.
 *
 * Caches the output of expensive view partials and supports tag-based
 * invalidation. Tags are tracked through a per-store side index so the
 * feature works on every cache driver (file, database, redis, memcached)
 * — not just the ones whose contracts expose native taggable APIs.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache;

use ArtisanPackUI\Performance\Events\CachePurged;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Fragment cache class.
 *
 *
 * @since      1.0.0
 */
class FragmentCache
{
    /**
     * Namespace prefix applied to every fragment cache key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const KEY_PREFIX = 'perf:fragment:';

    /**
     * Cache key holding the tag → cache-key index.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TAG_INDEX_KEY = 'perf:fragment-tags';

    /**
     * Maximum TTL applied to the tag index.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const INDEX_TTL = 31536000;

    /**
     * Caches the result of the callback under the given key.
     *
     * Returns the cached value on subsequent calls until the TTL elapses
     * or the entry is invalidated by tag. Tags are optional; when supplied
     * the cache key is registered against each tag so `invalidateByTag()`
     * can find it later.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key (unqualified — the prefix is added internally).
     * @param  int  $ttl  Time-to-live in seconds. `0` defaults to the configured fragment TTL.
     * @param  Closure  $callback  Callback whose return value is cached.
     * @param  array<int, string>  $tags  Tags the entry should be registered against.
     *
     * @return mixed The cached or freshly computed value.
     */
    public function remember( string $key, int $ttl, Closure $callback, array $tags = [] ): mixed
    {
        $cacheKey  = $this->qualifyKey( $key );
        $effective = $ttl > 0 ? $ttl : (int) config( 'artisanpack.performance.fragment_cache.default_ttl', 3600 );

        $value = $this->store()->remember( $cacheKey, $effective, $callback );

        $this->trackTags( $cacheKey, $tags );

        return $value;
    }

    /**
     * Returns the cached value for the given key, or null when missing.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key (unqualified).
     */
    public function get( string $key ): mixed
    {
        return $this->store()->get( $this->qualifyKey( $key ) );
    }

    /**
     * Stores a value under the given key for the given TTL.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key (unqualified).
     * @param  mixed  $value  Value to cache.
     * @param  int  $ttl  Time-to-live in seconds. `0` defaults to the configured fragment TTL.
     * @param  array<int, string>  $tags  Tags the entry should be registered against.
     */
    public function put( string $key, mixed $value, int $ttl = 0, array $tags = [] ): void
    {
        $cacheKey  = $this->qualifyKey( $key );
        $effective = $ttl > 0 ? $ttl : (int) config( 'artisanpack.performance.fragment_cache.default_ttl', 3600 );

        $this->store()->put( $cacheKey, $value, $effective );

        $this->trackTags( $cacheKey, $tags );
    }

    /**
     * Invalidates a single fragment by key.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key (unqualified).
     */
    public function forget( string $key ): bool
    {
        $cacheKey = $this->qualifyKey( $key );

        $forgotten = (bool) $this->store()->forget( $cacheKey );

        $this->withIndexLock( fn () => $this->removeFromIndex( [ $cacheKey ] ) );

        if ( $forgotten ) {
            CachePurged::dispatch( [ $cacheKey ], 'fragment-cache:forget' );
        }

        return $forgotten;
    }

    /**
     * Invalidates every fragment registered under the given tag.
     *
     * Returns the number of entries removed and dispatches a single
     * `CachePurged` event with the keys that were forgotten.
     *
     * @since 1.0.0
     *
     * @param  string  $tag  Tag name.
     */
    public function invalidateByTag( string $tag ): int
    {
        $tag = trim( $tag );

        if ( '' === $tag ) {
            return 0;
        }

        $purged = [];

        $this->withIndexLock( function () use ( $tag, &$purged ): void {
            $index = $this->readIndex();

            if ( ! isset( $index[ $tag ] ) || ! is_array( $index[ $tag ] ) ) {
                return;
            }

            $store = $this->store();

            foreach ( $index[ $tag ] as $cacheKey ) {
                if ( ! is_string( $cacheKey ) ) {
                    continue;
                }

                $store->forget( $cacheKey );
                $purged[] = $cacheKey;
            }

            unset( $index[ $tag ] );

            // The cache keys we just purged might also live under OTHER
            // tags. Strip them from those entries too — done inside the
            // same critical section so the two writes don't race.
            foreach ( $index as $otherTag => $keys ) {
                if ( ! is_array( $keys ) ) {
                    continue;
                }

                $filtered = array_values( array_diff( $keys, $purged ) );

                if ( empty( $filtered ) ) {
                    unset( $index[ $otherTag ] );
                } else {
                    $index[ $otherTag ] = $filtered;
                }
            }

            $this->writeIndex( $index );
        } );

        if ( empty( $purged ) ) {
            return 0;
        }

        CachePurged::dispatch( $purged, "fragment-cache:tag:{$tag}" );

        return count( $purged );
    }

    /**
     * Returns the cache keys currently registered under the given tag.
     *
     * Useful in tests and dashboards; not used on the hot read path.
     *
     * @since 1.0.0
     *
     * @param  string  $tag  Tag name.
     *
     * @return array<int, string>
     */
    public function keysForTag( string $tag ): array
    {
        $index = $this->readIndex();

        if ( ! isset( $index[ $tag ] ) || ! is_array( $index[ $tag ] ) ) {
            return [];
        }

        return array_values( array_filter( $index[ $tag ], 'is_string' ) );
    }

    /**
     * Applies the package's namespace prefix to the supplied key.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Caller-supplied key.
     */
    public function qualifyKey( string $key ): string
    {
        return self::KEY_PREFIX . $key;
    }

    /**
     * Registers the cache key under each tag in the index.
     *
     * Wrapped in the package's cache lock so concurrent `remember()` /
     * `put()` calls don't lose each other's tag entries — without the lock
     * two writers reading the same snapshot would clobber each other's
     * additions and the lost entries would survive any subsequent
     * `invalidateByTag()` sweep, serving stale fragments past their owner's
     * expectation.
     *
     * @since 1.0.0
     *
     * @param  string  $cacheKey  Qualified cache key.
     * @param  array<int, string>  $tags  Tags to register the key under.
     */
    protected function trackTags( string $cacheKey, array $tags ): void
    {
        $tags = array_values( array_filter( $tags, static fn ( $value ): bool => is_string( $value ) && '' !== trim( $value ) ) );

        if ( empty( $tags ) ) {
            return;
        }

        $this->withIndexLock( function () use ( $cacheKey, $tags ): void {
            $index = $this->readIndex();

            foreach ( $tags as $tag ) {
                $tag = trim( $tag );

                if ( ! isset( $index[ $tag ] ) || ! is_array( $index[ $tag ] ) ) {
                    $index[ $tag ] = [];
                }

                if ( ! in_array( $cacheKey, $index[ $tag ], true ) ) {
                    $index[ $tag ][] = $cacheKey;
                }
            }

            $this->writeIndex( $index );
        } );
    }

    /**
     * Runs the callback while holding the package's index lock.
     *
     * See `PageCacheManager::withIndexLock()` for rationale — falls back to
     * an unguarded execution when the configured store doesn't support
     * locking so the array driver (tests) and lock-less custom drivers
     * stay functional.
     *
     * @since 1.0.0
     *
     * @param  callable  $callback  Operation to perform under the lock.
     */
    protected function withIndexLock( callable $callback ): void
    {
        try {
            $lock = $this->store()->lock( self::TAG_INDEX_KEY . ':lock', 5 );
        } catch ( Throwable ) {
            $callback();

            return;
        }

        try {
            $lock->block( 3, $callback );
        } catch ( Throwable ) {
            $callback();
        }
    }

    /**
     * Removes the given cache keys from every tag entry in the index.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $cacheKeys  Cache keys to remove.
     */
    protected function removeFromIndex( array $cacheKeys ): void
    {
        if ( empty( $cacheKeys ) ) {
            return;
        }

        $index   = $this->readIndex();
        $changed = false;

        foreach ( $index as $tag => $keys ) {
            if ( ! is_array( $keys ) ) {
                continue;
            }

            $filtered = array_values( array_diff( $keys, $cacheKeys ) );

            if ( $filtered !== $keys ) {
                $changed       = true;
                $index[ $tag ] = $filtered;
            }

            if ( empty( $index[ $tag ] ) ) {
                unset( $index[ $tag ] );
            }
        }

        if ( $changed ) {
            $this->writeIndex( $index );
        }
    }

    /**
     * Returns the current tag index from cache.
     *
     * @since 1.0.0
     *
     * @return array<string, array<int, string>>
     */
    protected function readIndex(): array
    {
        $index = $this->store()->get( self::TAG_INDEX_KEY, [] );

        return is_array( $index ) ? $index : [];
    }

    /**
     * Persists the tag index back to cache.
     *
     * @since 1.0.0
     *
     * @param  array<string, array<int, string>>  $index  Tag index payload.
     */
    protected function writeIndex( array $index ): void
    {
        $this->store()->put( self::TAG_INDEX_KEY, $index, self::INDEX_TTL );
    }

    /**
     * Resolves the cache store used for fragment cache entries.
     *
     * @since 1.0.0
     */
    protected function store(): Repository
    {
        return Cache::store( config( 'artisanpack.performance.fragment_cache.driver' ) );
    }
}
