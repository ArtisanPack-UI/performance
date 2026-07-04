<?php

/**
 * Cache warmed event.
 *
 * Dispatched after the cache warmer completes a warming pass.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cache warmed event class.
 *
 *
 * @since      1.0.0
 */
class CacheWarmed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new event instance.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $urls  URLs that were warmed.
     * @param  int  $count  Number of cache entries written.
     */
    public function __construct(
        public array $urls,
        public int $count,
    ) {
    }
}
