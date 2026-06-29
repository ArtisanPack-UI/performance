<?php

/**
 * Caching Eloquent builder.
 *
 * A drop-in Eloquent builder subclass that intercepts terminal query
 * methods (`get`, `first`, `count`, `pluck`, `paginate`, `find`, etc.)
 * and routes them through the Performance package's query cache when
 * the caller has opted in via `cacheFor()`. Without the cache flag the
 * builder behaves exactly like the stock Eloquent builder, so the trait
 * is safe to mix into existing models without changing default
 * behavior.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Database;

use ArtisanPackUI\Performance\Cache\CacheStrategyManager;
use ArtisanPackUI\Performance\Contracts\CacheStrategy;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Caching Eloquent builder class.
 *
 *
 * @since      1.0.0
 */
class CachingEloquentBuilder extends Builder
{
    /**
     * Cache key prefix applied to every query cache entry written by the builder.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const KEY_PREFIX = 'perf:query:';

    /**
     * TTL the caller asked for via `cacheFor()`, in seconds.
     *
     * @since 1.0.0
     */
    protected ?int $cacheTtl = null;

    /**
     * Optional custom cache key supplied via `cacheFor()`.
     *
     * @since 1.0.0
     */
    protected ?string $cacheCustomKey = null;

    /**
     * Additional tags applied via `cacheTags()`.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected array $cacheExtraTags = [];

    /**
     * Marks the pending query as cached for the given TTL.
     *
     * Returning `$this` keeps the fluent builder API intact so callers
     * can write `Post::cacheFor(3600)->popular()->get()` as the issue
     * spec describes.
     *
     * @since 1.0.0
     *
     * @param  int  $ttl  Time-to-live in seconds. Non-positive values disable caching for this query.
     * @param  string|null  $key  Optional custom cache key.
     */
    public function cacheFor( int $ttl, ?string $key = null ): static
    {
        $this->cacheTtl       = $ttl > 0 ? $ttl : null;
        $this->cacheCustomKey = ( null !== $key && '' !== $key ) ? $key : null;

        return $this;
    }

    /**
     * Adds tags to the pending query cache entry.
     *
     * Tags are merged with the model's default table tag (added by the
     * trait) so callers compose targeted invalidation without losing
     * the auto-invalidation hook.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $tags  Tags to attach.
     */
    public function cacheTags( array $tags ): static
    {
        $filtered = array_values( array_filter(
            $tags,
            static fn ( $tag ): bool => is_string( $tag ) && '' !== $tag,
        ) );

        $this->cacheExtraTags = array_values( array_unique( array_merge(
            $this->cacheExtraTags,
            $filtered,
        ) ) );

        return $this;
    }

    /**
     * Returns the TTL currently flagged on the builder.
     *
     * @since 1.0.0
     */
    public function getCacheTtl(): ?int
    {
        return $this->cacheTtl;
    }

    /**
     * Returns the custom cache key currently flagged on the builder.
     *
     * @since 1.0.0
     */
    public function getCacheCustomKey(): ?string
    {
        return $this->cacheCustomKey;
    }

    /**
     * Returns the extra tags currently flagged on the builder.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function getCacheExtraTags(): array
    {
        return $this->cacheExtraTags;
    }

    /**
     * {@inheritDoc}
     *
     * Routes through the query cache when `cacheFor()` set a TTL.
     *
     * @since 1.0.0
     */
    public function get( $columns = [ '*' ] )
    {
        return $this->withQueryCache(
            __FUNCTION__,
            [ $columns ],
            fn () => parent::get( $columns ),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function pluck( $column, $key = null )
    {
        return $this->withQueryCache(
            __FUNCTION__,
            [ $column, $key ],
            fn () => parent::pluck( $column, $key ),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function count( $columns = '*' )
    {
        return $this->withQueryCache(
            __FUNCTION__,
            [ $columns ],
            fn () => parent::count( $columns ),
        );
    }

    /**
     * {@inheritDoc}
     *
     * Resolves the current page from the request when `$page` is null so
     * the cache key reflects the page actually being rendered. Without
     * this step, every `?page=N` URL would collide on a single cache
     * entry and silently serve the first paginator a worker observed.
     *
     * @since 1.0.0
     */
    public function paginate( $perPage = null, $columns = [ '*' ], $pageName = 'page', $page = null, $total = null ): LengthAwarePaginator
    {
        $resolvedPage = $page ?? Paginator::resolveCurrentPage( $pageName );

        return $this->withQueryCache(
            __FUNCTION__,
            [ $perPage, $columns, $pageName, $resolvedPage, $total ],
            fn () => parent::paginate( $perPage, $columns, $pageName, $page, $total ),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function exists()
    {
        return $this->withQueryCache(
            __FUNCTION__,
            [],
            fn () => parent::exists(),
        );
    }

    /**
     * Returns the strategy used to read/write query cache entries.
     *
     * Resolution order: `database.query_cache.driver` → manager default →
     * `file`. The manager is resolved through the container so tests can
     * bind a fake without touching the trait.
     *
     * @since 1.0.0
     */
    public function cacheStrategy(): CacheStrategy
    {
        $driver  = (string) config( 'artisanpack.performance.database.query_cache.driver', '' );
        $manager = app( CacheStrategyManager::class );

        return '' !== $driver && $manager->hasDriver( $driver )
            ? $manager->driver( $driver )
            : $manager->driver();
    }

    /**
     * Reads the cached payload or runs the callback on MISS.
     *
     * Method-name + argument signature are mixed into the cache key so
     * `->get()`, `->pluck('id')`, and `->count()` against the same SQL
     * land on distinct entries — Laravel emits the same underlying SQL
     * for all three but their result shapes differ, so collapsing them
     * would corrupt the cache.
     *
     * @since 1.0.0
     *
     * @param  string  $method  The terminal method being called.
     * @param  array<int, mixed>  $args  The arguments passed to that method.
     * @param  Closure  $callback  Callback that executes the underlying query on MISS.
     */
    protected function withQueryCache( string $method, array $args, Closure $callback ): mixed
    {
        if ( null === $this->cacheTtl ) {
            return $callback();
        }

        $strategy = $this->cacheStrategy();
        $tags     = $this->resolveTags();
        $key      = $this->buildCacheKey( $method, $args );

        $scoped = $strategy->tags( $tags );
        $hit    = $scoped->get( $key );

        if ( is_string( $hit ) ) {
            return unserialize( $hit, [ 'allowed_classes' => true ] );
        }

        $value = $callback();

        $scoped->put( $key, serialize( $value ), $this->cacheTtl );

        return $value;
    }

    /**
     * Returns the tags applied to the pending query cache entry.
     *
     * The model's table tag is always included so the trait's
     * save/delete hooks can invalidate every cached query touching the
     * model in one call.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function resolveTags(): array
    {
        $tags = [ 'model:' . $this->getModel()->getTable() ];

        return array_values( array_unique( array_merge( $tags, $this->cacheExtraTags ) ) );
    }

    /**
     * Builds the cache key for the pending query + method + args.
     *
     * The custom-key path still mixes the underlying SQL + bindings into
     * the hash so two callers that pick the same friendly name on
     * different chains don't silently collide and serve each other's
     * data. The custom key narrows the namespace; the SQL discriminator
     * preserves correctness.
     *
     * Eager-load constraints are captured by `spl_object_hash` on each
     * registered closure rather than by relation name alone, so two
     * calls that pass different constraint closures land on distinct
     * keys. Two callers passing structurally identical but distinct
     * closures pay a small extra cache-miss penalty — the alternative
     * (collapsing them by name) would let `with(['c' => fn($q) => $q->where('approved', true)])`
     * and `with(['c' => fn($q) => $q->where('approved', false)])` HIT
     * each other's entries, which is a correctness bug.
     *
     * @since 1.0.0
     *
     * @param  string  $method  Terminal method name.
     * @param  array<int, mixed>  $args  Terminal method arguments.
     */
    protected function buildCacheKey( string $method, array $args ): string
    {
        $base    = $this->getQuery();
        $payload = [
            'class'      => get_class( $this->getModel() ),
            'connection' => $base->getConnection()->getName(),
            'sql'        => $base->toSql(),
            'bindings'   => $base->getBindings(),
            'method'     => $method,
            'args'       => $args,
            'eager'      => $this->eagerLoadFingerprint(),
            'custom'     => $this->cacheCustomKey,
        ];

        return self::KEY_PREFIX . sha1( serialize( $payload ) );
    }

    /**
     * Returns a deterministic fingerprint of the registered eager loads.
     *
     * Each relation name is paired with `spl_object_hash` of its
     * constraint closure (or an empty string when the relation has no
     * custom constraint), so two `with()` calls that pass different
     * closures get different keys.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function eagerLoadFingerprint(): array
    {
        $fingerprint = [];

        foreach ( $this->getEagerLoads() as $relation => $constraint ) {
            $fingerprint[ (string) $relation ] = $constraint instanceof Closure
                ? spl_object_hash( $constraint )
                : '';
        }

        return $fingerprint;
    }
}
