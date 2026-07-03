<?php

/**
 * Eloquent stub that mimics the artisanpack-ui/media-library `Media` model.
 *
 * Provides just enough surface for the Performance package's listener,
 * job, and trait tests to run without the real media-library package
 * installed as a test-time dependency. The stub is backed by a
 * `media_stubs` table created inline by the tests that need persistence.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\Performance\Traits\HasOptimizedMedia;
use Illuminate\Database\Eloquent\Model;

/**
 * Media stub Eloquent model.
 *
 *
 * @since      1.0.0
 */
class MediaModelStub extends Model
{
    use HasOptimizedMedia;

    protected $table = 'media_stubs';

    protected $guarded = [];

    protected $casts = [
        'optimized_formats' => 'array',
        'optimized_sizes'   => 'array',
        'optimized_at'      => 'datetime',
    ];

    /**
     * Mirrors media-library's `Media::isImage()` helper so the listener's
     * mime-type guard exercises the same code path in tests.
     *
     * @since 1.0.0
     */
    public function isImage(): bool
    {
        return is_string( $this->mime_type ) && str_starts_with( $this->mime_type, 'image/' );
    }
}
