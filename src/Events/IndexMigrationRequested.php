<?php

/**
 * Index migration requested event.
 *
 * Dispatched when an operator asks the recommendation engine to generate
 * an index migration for a given (table, columns) pair. Fired by both the
 * Livewire `RecommendationsPanel` and the JSON admin API's
 * `RecommendationsAdminApiController`, so a single listener can bridge
 * both surfaces to a scaffold command or a review step.
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
 * Index migration requested event class.
 *
 *
 * @since      1.0.0
 */
class IndexMigrationRequested
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new event instance.
     *
     * @since 1.0.0
     *
     * @param  string  $table  Target table.
     * @param  array<int, string>  $columns  Columns to index.
     * @param  string  $recommendationId  Id of the recommendation that triggered the request.
     */
    public function __construct(
        public string $table,
        public array $columns,
        public string $recommendationId = '',
    ) {
    }
}
