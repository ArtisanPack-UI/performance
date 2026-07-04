<?php

/**
 * Redis cache strategy.
 *
 * Wraps Laravel's `redis` cache store. The Redis driver exposes native
 * taggable repositories, so the abstract base routes scoped writes
 * through `Cache::store('redis')->tags($tags)` and `flush()` invalidates
 * only the scoped tag set.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache\Strategies;

/**
 * Redis cache strategy class.
 *
 *
 * @since      1.0.0
 */
class RedisCacheStrategy extends AbstractCacheStrategy
{
    /**
     * {@inheritDoc}
     */
    public function storeName(): string
    {
        return 'redis';
    }
}
