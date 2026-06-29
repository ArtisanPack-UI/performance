<?php

/**
 * File cache strategy.
 *
 * Wraps Laravel's `file` cache store. The file driver does not support
 * native taggable repositories, so the abstract base falls back to its
 * tag-prefix index for tag-scoped invalidation.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache\Strategies;

/**
 * File cache strategy class.
 *
 *
 * @since      1.0.0
 */
class FileCacheStrategy extends AbstractCacheStrategy
{
    /**
     * {@inheritDoc}
     */
    public function storeName(): string
    {
        return 'file';
    }
}
