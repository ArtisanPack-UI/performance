<?php

/**
 * Abstract cache strategy.
 *
 * Holds the shared behavior every CacheStrategy implementation needs:
 * resolving a Laravel cache Repository for a named store, scoping reads
 * and writes through that repository, and maintaining a per-store tag
 * index for drivers that don't expose native tagging (file). Concrete
 * strategies override `storeName()` so each one resolves the correct
 * Laravel store.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache\Strategies;

use ArtisanPackUI\Performance\Contracts\CacheStrategy;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Abstract cache strategy class.
 *
 *
 * @since      1.0.0
 */
abstract class AbstractCacheStrategy implements CacheStrategy
{
    /**
     * Cache key holding the tag → cache-key index for this strategy.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TAG_INDEX_KEY = 'perf:strategy-tags';

    /**
     * TTL applied to the tag index so it outlives normal cache entries.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const INDEX_TTL = 31536000;

    /**
     * Tags currently scoping this strategy.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected array $scopedTags = [];

    /**
     * Returns the Laravel cache store name this strategy targets.
     *
     * @since 1.0.0
     */
    abstract public function storeName(): string;

    /**
     * {@inheritDoc}
     */
    public function get( string $key ): ?string
    {
        $value = $this->repository()->get( $key );

        return is_string( $value ) ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function put( string $key, string $value, int $ttl ): bool
    {
        if ( $ttl > 0 ) {
            $result = $this->repository()->put( $key, $value, $ttl );
        } else {
            $result = $this->repository()->forever( $key, $value );
        }

        $this->trackTags( $key );

        return (bool) $result;
    }

    /**
     * {@inheritDoc}
     */
    public function forget( string $key ): bool
    {
        return (bool) $this->repository()->forget( $key );
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        $repo = $this->repository();

        if ( ! empty( $this->scopedTags ) && $this->repositorySupportsTags() ) {
            return (bool) $repo->flush();
        }

        if ( ! empty( $this->scopedTags ) ) {
            return $this->flushScopedTags();
        }

        return (bool) Cache::store( $this->storeName() )->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function tags( array $tags ): static
    {
        $clone             = clone $this;
        $clone->scopedTags = array_values( array_unique( array_filter(
            $tags,
            static fn ( $tag ): bool => is_string( $tag ) && '' !== $tag,
        ) ) );

        return $clone;
    }

    /**
     * Returns the tags currently scoping this strategy.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function getScopedTags(): array
    {
        return $this->scopedTags;
    }

    /**
     * Resolves the cache repository used for reads and writes.
     *
     * When the underlying repository supports tags AND the strategy is
     * scoped to a tag list, the tagged proxy is returned so put/get
     * operations route through the driver's native tag index. Otherwise
     * the bare repository is returned and the tag-prefix index handles
     * cleanup in `flushScopedTags()`.
     *
     * @since 1.0.0
     *
     * @return \Illuminate\Cache\TaggedCache|Repository
     */
    protected function repository()
    {
        $store = Cache::store( $this->storeName() );

        if ( ! empty( $this->scopedTags ) && $this->repositorySupportsTags() ) {
            return $store->tags( $this->scopedTags );
        }

        return $store;
    }

    /**
     * Reports whether the underlying store supports native tag scoping.
     *
     * @since 1.0.0
     */
    protected function repositorySupportsTags(): bool
    {
        try {
            $store = Cache::store( $this->storeName() );
            $store->tags( ['probe'] );

            return true;
        } catch ( Throwable ) {
            return false;
        }
    }

    /**
     * Records the cache key against each scoped tag in the side index.
     *
     * Only fires when the strategy is scoped to a tag list AND the
     * underlying store doesn't expose native tag support — drivers with
     * native taggable repositories track their own index, so a second
     * one here would be redundant work.
     *
     * The read-modify-write is wrapped in a cache lock so concurrent
     * writers don't drop each other's index entries; without the lock,
     * two puts against the same tag that read the same snapshot would
     * each write back their own addition, silently orphaning the
     * other's cache key from future `flush()` calls. FragmentCache
     * pioneered this guard for the same reason.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The cache key that was just written.
     */
    protected function trackTags( string $key ): void
    {
        if ( empty( $this->scopedTags ) ) {
            return;
        }

        if ( $this->repositorySupportsTags() ) {
            return;
        }

        $this->withIndexLock( function () use ( $key ): void {
            $index = $this->readTagIndex();

            foreach ( $this->scopedTags as $tag ) {
                $bucket         = $index[ $tag ] ?? [];
                $bucket[ $key ] = true;
                $index[ $tag ]  = $bucket;
            }

            $this->writeTagIndex( $index );
        } );
    }

    /**
     * Flushes every key registered against any of the scoped tags.
     *
     * Wrapped in the same lock as `trackTags()` so a racing put cannot
     * land a new tag-bucket entry against the snapshot we just emptied
     * — without the lock that put would survive the flush and leak its
     * cache entry as an orphan that no future flush can find.
     *
     * @since 1.0.0
     */
    protected function flushScopedTags(): bool
    {
        $this->withIndexLock( function (): void {
            $index   = $this->readTagIndex();
            $store   = Cache::store( $this->storeName() );
            $changed = false;

            foreach ( $this->scopedTags as $tag ) {
                $bucket = $index[ $tag ] ?? [];

                if ( ! is_array( $bucket ) ) {
                    continue;
                }

                foreach ( array_keys( $bucket ) as $cacheKey ) {
                    if ( is_string( $cacheKey ) ) {
                        $store->forget( $cacheKey );
                    }
                }

                unset( $index[ $tag ] );
                $changed = true;
            }

            if ( $changed ) {
                $this->writeTagIndex( $index );
            }
        } );

        return true;
    }

    /**
     * Runs the callback while holding the strategy's index lock.
     *
     * Falls back to executing the callback unguarded when the
     * configured cache store doesn't expose locking (array driver,
     * custom drivers without LockProvider). Those scenarios are either
     * tests — where the race can't fire because PHP is single-threaded
     * inside a test — or explicit operator choice, so the alternative
     * (throwing) would do more damage than a best-effort write.
     *
     * @since 1.0.0
     *
     * @param  callable  $callback  Operation to perform under the lock.
     */
    protected function withIndexLock( callable $callback ): void
    {
        try {
            $lock = Cache::store( $this->storeName() )->lock( self::TAG_INDEX_KEY . ':lock', 5 );
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
     * Returns the current tag index from the underlying store.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, true>>
     */
    protected function readTagIndex(): array
    {
        $index = Cache::store( $this->storeName() )->get( self::TAG_INDEX_KEY, [] );

        return is_array( $index ) ? $index : [];
    }

    /**
     * Persists the tag index back to the underlying store.
     *
     * @since 1.0.0
     *
     * @param  array<string, array<string, true>>  $index  The new tag index.
     */
    protected function writeTagIndex( array $index ): void
    {
        Cache::store( $this->storeName() )->put( self::TAG_INDEX_KEY, $index, self::INDEX_TTL );
    }
}
