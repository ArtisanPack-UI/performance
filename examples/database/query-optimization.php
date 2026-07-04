<?php

/**
 * Query optimization — CachingEloquentBuilder.
 *
 * Opt a specific model into a transparent query-result cache. The
 * builder caches the result of any query, tags it with the model's
 * table, and flushes the tag on save/update/delete via a shipped
 * model observer.
 */

namespace App\Models;

use ArtisanPackUI\Performance\Traits\HasCachingQueryBuilder;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasCachingQueryBuilder;

    /**
     * TTL (seconds) for cached query results for this model.
     * Optional — defaults to `artisanpack.performance.query_cache.default_ttl`.
     */
    protected int $queryCacheTtl = 3600;
}

/*
 * Reads now transparently hit the query cache:
 *
 *   $tree = Category::whereNull('parent_id')
 *       ->with('children')
 *       ->orderBy('name')
 *       ->get(); // First call runs the SQL and caches the result.
 *
 *   Category::query()->orderBy('name')->get(); // Second call hits the cache.
 *
 * Writes invalidate automatically:
 *
 *   Category::create(['name' => 'New', 'parent_id' => 1]); // Cache flushed.
 *
 * Disable per-call when you need fresh data (admin panels, reports):
 *
 *   Category::query()->withoutQueryCache()->orderBy('name')->get();
 */
