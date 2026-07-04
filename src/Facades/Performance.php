<?php

/**
 * Performance facade.
 *
 * Static accessor for the PerformanceService. Resolves the `performance`
 * container binding.
 *
 *
 * @since      1.0.0
 * @see \ArtisanPackUI\Performance\Services\PerformanceService
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Performance facade.
 *
 *
 * @since      1.0.0
 */
class Performance extends Facade
{
    /**
     * Returns the container binding name.
     *
     * @since 1.0.0
     */
    protected static function getFacadeAccessor(): string
    {
        return 'performance';
    }
}
