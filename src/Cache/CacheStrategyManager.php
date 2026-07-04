<?php

/**
 * Cache strategy manager.
 *
 * Resolves a `CacheStrategy` implementation by name. The manager keeps
 * a static map of built-in strategies (file, redis, memcached) and lets
 * applications register custom strategies via `extend()`. Consumers
 * (CachesQueries trait, future PageCacheManager / FragmentCache rewrites)
 * call `driver()` with the configured driver name and receive an
 * instance ready to read/write through the appropriate Laravel store.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Cache;

use ArtisanPackUI\Performance\Cache\Strategies\FileCacheStrategy;
use ArtisanPackUI\Performance\Cache\Strategies\MemcachedCacheStrategy;
use ArtisanPackUI\Performance\Cache\Strategies\RedisCacheStrategy;
use ArtisanPackUI\Performance\Contracts\CacheStrategy;
use Closure;
use InvalidArgumentException;

/**
 * Cache strategy manager class.
 *
 *
 * @since      1.0.0
 */
class CacheStrategyManager
{
    /**
     * The default strategy bindings keyed by driver name.
     *
     * Built-in drivers are bound to closures so the strategy instance
     * is only constructed when first requested. Closures registered via
     * `extend()` win over the built-ins.
     *
     * @since 1.0.0
     *
     * @var array<string, Closure>
     */
    protected array $extensions = [];

    /**
     * Cache of already-resolved strategies keyed by driver name.
     *
     * @since 1.0.0
     *
     * @var array<string, CacheStrategy>
     */
    protected array $resolved = [];

    /**
     * Returns the strategy instance for the given driver name.
     *
     * Falls back to the configured `page_cache.driver` (then `file`)
     * when no driver name is supplied. Resolved instances are memoized
     * so callers reuse a single instance per request.
     *
     * @since 1.0.0
     *
     * @param  string|null  $driver  Driver name (file, redis, memcached, or a registered extension).
     *
     * @throws InvalidArgumentException When the driver is not registered.
     */
    public function driver( ?string $driver = null ): CacheStrategy
    {
        $name = $driver ?? $this->defaultDriver();

        if ( isset( $this->resolved[ $name ] ) ) {
            return $this->resolved[ $name ];
        }

        if ( isset( $this->extensions[ $name ] ) ) {
            return $this->resolved[ $name ] = ( $this->extensions[ $name ] )();
        }

        $builtIn = $this->builtInStrategy( $name );

        if ( null !== $builtIn ) {
            return $this->resolved[ $name ] = $builtIn;
        }

        throw new InvalidArgumentException( "Cache strategy [{$name}] is not registered." );
    }

    /**
     * Registers a custom cache strategy.
     *
     * Custom strategies override the built-in mapping for the same name,
     * which lets applications swap, for example, a hardened Redis
     * implementation in place of the default one without touching call
     * sites.
     *
     * @since 1.0.0
     *
     * @param  string  $driver  Driver name.
     * @param  Closure  $factory  Factory returning a CacheStrategy.
     */
    public function extend( string $driver, Closure $factory ): void
    {
        $this->extensions[ $driver ] = $factory;
        unset( $this->resolved[ $driver ] );
    }

    /**
     * Reports whether the manager can resolve the given driver name.
     *
     * Built-in driver names (file/redis/memcached) only count when
     * Laravel ALSO has a matching `cache.stores.{driver}` entry — the
     * package's strategy can't read from a store the host application
     * didn't provision. Without this check, `hasDriver('redis')` would
     * return true on a fresh app with no redis configured and the
     * first strategy call would throw `InvalidArgumentException: Cache
     * store [redis] is not defined.`
     *
     * @since 1.0.0
     *
     * @param  string  $driver  Driver name to probe.
     */
    public function hasDriver( string $driver ): bool
    {
        if ( isset( $this->extensions[ $driver ] ) ) {
            return true;
        }

        if ( null === $this->builtInStrategy( $driver ) ) {
            return false;
        }

        return $this->storeConfigured( $driver );
    }

    /**
     * Returns the strategy used when no driver name is supplied.
     *
     * Reads `artisanpack.performance.page_cache.driver` first so a single
     * config switch flips the default for callers that don't pass a
     * driver name explicitly. Falls back to `file` to keep the default
     * usable on a stock Laravel install with no Redis/Memcached running.
     *
     * @since 1.0.0
     */
    public function defaultDriver(): string
    {
        $configured = (string) config( 'artisanpack.performance.page_cache.driver', '' );

        return '' !== $configured ? $configured : 'file';
    }

    /**
     * Reports whether Laravel's `cache.stores.{driver}` is configured.
     *
     * Reads the raw config map rather than calling `Cache::store()` so
     * the probe stays cheap (no driver allocation) and doesn't throw
     * for unconfigured names — those are exactly the case we want to
     * report as `false`.
     *
     * @since 1.0.0
     *
     * @param  string  $driver  Driver name.
     */
    protected function storeConfigured( string $driver ): bool
    {
        $stores = (array) config( 'cache.stores', [] );

        return isset( $stores[ $driver ] );
    }

    /**
     * Returns a fresh built-in strategy instance for the given name.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Driver name.
     */
    protected function builtInStrategy( string $name ): ?CacheStrategy
    {
        return match ( $name ) {
            'file'      => new FileCacheStrategy,
            'redis'     => new RedisCacheStrategy,
            'memcached' => new MemcachedCacheStrategy,
            default     => null,
        };
    }
}
