<?php

/**
 * Caches queries trait.
 *
 * Mix this trait into an Eloquent model to opt the model into the
 * Performance package's query cache. The trait wires the model's
 * `newEloquentBuilder()` method to return `CachingEloquentBuilder`,
 * which adds `cacheFor()` / `cacheTags()` to the fluent builder API
 * and routes terminal query methods (`get`, `first`, `count`, etc.)
 * through the configured cache strategy.
 *
 * Save and delete events flush the model's table tag so any cached
 * query referencing the model invalidates automatically. Tag-based
 * invalidation works on every Laravel cache driver — drivers without
 * native tag support (file, database) fall back to the cache
 * strategy's tag-prefix index.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Traits;

use ArtisanPackUI\Performance\Cache\CacheStrategyManager;
use ArtisanPackUI\Performance\Contracts\CacheStrategy;
use ArtisanPackUI\Performance\Database\CachingEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Caches queries trait.
 *
 *
 * @since      1.0.0
 */
trait CachesQueries
{
    /**
     * Boots the trait.
     *
     * Registers model events so every save / delete invalidates the
     * tag associated with the model's table. Subsequent reads
     * recompute and refill the cache transparently to call sites.
     *
     * When the save / delete happens inside an open transaction the
     * flush is DEFERRED to after-commit. Otherwise a concurrent reader
     * racing between the flush and the COMMIT would refill the cache
     * with pre-commit state (or with no row at all under READ
     * COMMITTED), leaving the cache permanently stale until the next
     * write. After-commit also means the flush silently no-ops when
     * the transaction rolls back — which is the right behavior, since
     * the data the would-be flush was reacting to never actually
     * landed.
     *
     * @since 1.0.0
     */
    public static function bootCachesQueries(): void
    {
        static::saved( static function ( Model $model ): void {
            static::scheduleQueryCacheFlush( $model );
        } );

        static::deleted( static function ( Model $model ): void {
            static::scheduleQueryCacheFlush( $model );
        } );
    }

    /**
     * Flushes immediately or queues an after-commit callback.
     *
     * Reads the model's connection transaction level (rather than the
     * default connection) so a model bound to a different connection
     * still defers correctly. Falls back to immediate flush when the
     * connection check throws — the caller would rather double-flush
     * than skip invalidation entirely.
     *
     * @since 1.0.0
     *
     * @param  Model  $model  The model whose tag should be flushed.
     */
    public static function scheduleQueryCacheFlush( Model $model ): void
    {
        try {
            $connection = DB::connection( $model->getConnectionName() );
            $level      = $connection->transactionLevel();
        } catch ( Throwable ) {
            static::flushQueryCacheFor( $model );

            return;
        }

        if ( $level > 0 ) {
            $connection->afterCommit( static function () use ( $model ): void {
                static::flushQueryCacheFor( $model );
            } );

            return;
        }

        static::flushQueryCacheFor( $model );
    }

    /**
     * Returns the Eloquent builder used by the model.
     *
     * @since 1.0.0
     *
     * @param  QueryBuilder  $query  The base query builder instance.
     */
    public function newEloquentBuilder( $query ): CachingEloquentBuilder
    {
        return new CachingEloquentBuilder( $query );
    }

    /**
     * Returns the cache tag for the given model.
     *
     * The tag is derived from the table name so subclasses sharing a
     * table collapse to the same tag — which is the intended behavior
     * for STI and table-shared queries.
     *
     * @since 1.0.0
     *
     * @param  Model  $model  The Eloquent model instance.
     */
    public static function queryCacheTagFor( Model $model ): string
    {
        return 'model:' . $model->getTable();
    }

    /**
     * Flushes every cached query tagged for the given model.
     *
     * @since 1.0.0
     *
     * @param  Model  $model  The model whose cache tag should be flushed.
     */
    public static function flushQueryCacheFor( Model $model ): void
    {
        static::queryCacheStrategy()
            ->tags( [ static::queryCacheTagFor( $model ) ] )
            ->flush();
    }

    /**
     * Returns the strategy used to read/write query cache entries.
     *
     * The driver is resolved from `artisanpack.performance.database.query_cache.driver`,
     * falling back to the strategy manager's default driver when the
     * config value is empty or names an unregistered driver.
     *
     * @since 1.0.0
     */
    public static function queryCacheStrategy(): CacheStrategy
    {
        $driver  = (string) config( 'artisanpack.performance.database.query_cache.driver', '' );
        $manager = app( CacheStrategyManager::class );

        return '' !== $driver && $manager->hasDriver( $driver )
            ? $manager->driver( $driver )
            : $manager->driver();
    }
}
