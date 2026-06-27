<?php

/**
 * N+1 query detected event.
 *
 * Dispatched when the N+1 detector observes more occurrences of a
 * normalized query in a single request than the configured threshold.
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
 * N+1 query detected event class.
 *
 *
 * @since      1.0.0
 */
class N1QueryDetected
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new event instance.
     *
     * @since 1.0.0
     *
     * @param  string  $queryNormalized  The normalized query signature.
     * @param  int  $count  Number of times the query ran in the request.
     * @param  string  $route  The HTTP route the request was bound to.
     */
    public function __construct(
        public string $queryNormalized,
        public int $count,
        public string $route = '',
    ) {
    }
}
