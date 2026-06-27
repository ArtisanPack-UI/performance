<?php

/**
 * Eloquent stub model used by `HasOptimizedImagesTest`.
 *
 * Lives in `tests/Fixtures` rather than inline in the test file so it owns its
 * own file (per the package's "one class per file" convention) and so the
 * class name doesn't collide if another suite ever needs a similarly-named
 * stub. The model is not backed by a real table — tests hydrate instances
 * via `setRawAttributes()` and never persist.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\Performance\Traits\HasOptimizedImages;
use Illuminate\Database\Eloquent\Model;

/**
 * Stub Eloquent model that exercises `HasOptimizedImages`.
 *
 *
 * @since      1.0.0
 */
class HasOptimizedImagesModelStub extends Model
{
    use HasOptimizedImages;

    public $timestamps = false;

    /**
     * Per-test optimizable image config; populated before each assertion.
     *
     * @since 1.0.0
     *
     * @var array<string, array<string, mixed>>
     */
    public array $imageConfig = [];

    /**
     * Fields the stub should report as changed via `wasChanged()`.
     *
     * Eloquent's real `wasChanged()` only returns true after a successful
     * save; the stub overrides it so tests can drive the trait's auto-optimize
     * code path without actually persisting the model.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public array $changedFields = [];

    protected $guarded = [];

    /**
     * Trait override — returns the per-test image config.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function optimizableImages(): array
    {
        return $this->imageConfig;
    }

    /**
     * Test override for Eloquent's `wasChanged()`.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|string|null  $attributes  Attribute name(s) to test.
     */
    public function wasChanged( $attributes = null ): bool
    {
        if ( null === $attributes ) {
            return ! empty( $this->changedFields );
        }

        $names = is_array( $attributes ) ? $attributes : [$attributes];

        foreach ( $names as $name ) {
            if ( in_array( $name, $this->changedFields, true ) ) {
                return true;
            }
        }

        return false;
    }
}
