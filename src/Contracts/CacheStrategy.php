<?php

/**
 * Cache strategy contract.
 *
 * Defines a uniform read/write/forget/flush API the Performance package
 * uses to interact with its various caching backends (file, Redis,
 * Memcached). Strategies wrap the underlying Laravel cache repository so
 * callers can swap backends per feature (page cache, fragment cache,
 * query cache) via configuration without touching call sites.
 *
 * The contract intentionally uses string payloads and integer TTLs so
 * implementations remain free to delegate to drivers without native
 * serialization (raw Redis strings, file contents) while higher-level
 * callers (CachesQueries trait, FragmentCache) handle serialization at
 * their boundary.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Contracts;

/**
 * Cache strategy contract.
 *
 *
 * @since      1.0.0
 */
interface CacheStrategy
{
    /**
     * Retrieves the raw cached value for the given key.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key.
     *
     * @return string|null The raw cached value, or null when missing.
     */
    public function get( string $key ): ?string;

    /**
     * Persists the value under the given key for the given TTL.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key.
     * @param  string  $value  Raw value to store.
     * @param  int  $ttl  Time-to-live in seconds. Zero defers to the driver default.
     *
     * @return bool True when the write succeeded.
     */
    public function put( string $key, string $value, int $ttl ): bool;

    /**
     * Removes the entry for the given key.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key.
     *
     * @return bool True when the entry existed and was removed.
     */
    public function forget( string $key ): bool;

    /**
     * Flushes every entry the strategy can reach.
     *
     * @since 1.0.0
     *
     * @return bool True when the flush succeeded.
     */
    public function flush(): bool;

    /**
     * Returns a derivative strategy scoped to the given tags.
     *
     * Implementations that wrap a taggable repository delegate to
     * `Cache::store(...)->tags($tags)`; non-taggable strategies fall
     * back to a tag-prefix index so the higher-level API remains
     * uniform across drivers. Implementations must return `static`
     * (a new instance) rather than mutating self so the original
     * strategy stays usable for non-tag callers.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $tags  Tags to attach.
     */
    public function tags( array $tags ): static;
}
