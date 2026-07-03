<?php

/**
 * Media optimization lifecycle status values.
 *
 * Small value-object-like class that owns the string constants written to
 * the `media.optimization_status` column. Kept as a dedicated class rather
 * than as trait constants because PHP disallows accessing trait constants
 * from outside a class that uses the trait, and both the listener/job
 * layer and consuming applications need to reference these values without
 * `use`-ing the whole trait.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Support;

/**
 * Media optimization status constants.
 *
 *
 * @since      1.0.0
 */
final class MediaOptimizationStatus
{
    /**
     * Not yet processed.
     *
     * @since 1.0.0
     */
    public const PENDING = 'pending';

    /**
     * Currently being processed.
     *
     * @since 1.0.0
     */
    public const PROCESSING = 'processing';

    /**
     * All derivatives produced successfully.
     *
     * @since 1.0.0
     */
    public const COMPLETED = 'completed';

    /**
     * Optimization pipeline failed.
     *
     * @since 1.0.0
     */
    public const FAILED = 'failed';
}
