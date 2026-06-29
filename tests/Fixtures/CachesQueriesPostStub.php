<?php

/**
 * Eloquent stub model used by `CachesQueriesTest`.
 *
 * Backed by the `caches_queries_posts` table created on the fly in
 * the test's `beforeEach` hook so the trait can exercise its full
 * save/delete invalidation path against a real connection.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\Performance\Traits\CachesQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Stub Eloquent model that exercises `CachesQueries`.
 *
 *
 * @since      1.0.0
 */
class CachesQueriesPostStub extends Model
{
    use CachesQueries;

    public $timestamps = false;

    protected $table = 'caches_queries_posts';

    protected $guarded = [];
}
