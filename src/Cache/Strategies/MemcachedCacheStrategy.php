<?php

/**
 * Memcached cache strategy.
 *
 * Wraps Laravel's `memcached` cache store. The Memcached driver exposes
 * native taggable repositories.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache\Strategies;

/**
 * Memcached cache strategy class.
 *
 *
 * @since      1.0.0
 */
class MemcachedCacheStrategy extends AbstractCacheStrategy
{
    /**
     * {@inheritDoc}
     */
    public function storeName(): string
    {
        return 'memcached';
    }
}
