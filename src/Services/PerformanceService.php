<?php

/**
 * Performance service.
 *
 * Central service exposing the package's image, scripting, caching, and
 * monitoring APIs. The Performance facade resolves to an instance of this
 * class. Methods are stubbed during Phase 1 scaffolding and filled in by
 * subsequent feature phases (image optimization, caching, monitoring).
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Services;

use ArtisanPackUI\Performance\Cache\CacheInvalidator;
use ArtisanPackUI\Performance\Cache\FragmentCache;
use ArtisanPackUI\Performance\Cache\PageCacheManager;
use ArtisanPackUI\Performance\Css\CriticalCssExtractor;
use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\JavaScript\ScriptManager;
use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;
use ArtisanPackUI\Performance\Output\ResourceHintInjector;
use ArtisanPackUI\Performance\Speculative\PrefetchManager;
use ArtisanPackUI\Performance\Speculative\PrerenderManager;
use ArtisanPackUI\Performance\Speculative\SpeculativeRulesGenerator;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Performance service class.
 *
 * Resolved by the `performance` container binding and the Performance facade.
 *
 *
 * @since      1.0.0
 */
class PerformanceService
{
    /**
     * Image service used for optimization, format conversion, and color extraction.
     *
     * @since 1.0.0
     */
    protected ImageService $images;

    /**
     * Responsive image generator (lazily resolved against `$images`).
     *
     * @since 1.0.0
     */
    protected ?ResponsiveImageGenerator $responsiveImages = null;

    /**
     * Script manager service (lazily instantiated when first accessed).
     *
     * Defaulted to `null` so subclasses that touch the property before
     * `parent::__construct()` runs don't trip the typed-property-uninitialized
     * AccessError — matches the convention used by `$responsiveImages` above.
     *
     * @since 1.0.0
     */
    protected ?ScriptManager $scripts = null;

    /**
     * Critical CSS extractor (lazily instantiated when first accessed).
     *
     * Defaulted to `null` for the same reason as `$scripts`.
     *
     * @since 1.0.0
     */
    protected ?CriticalCssExtractor $criticalCss = null;

    /**
     * Resource hint injector (lazily instantiated when first accessed).
     *
     * @since 1.0.0
     */
    protected ?ResourceHintInjector $resourceHints = null;

    /**
     * Speculative rules generator (lazily instantiated when first accessed).
     *
     * @since 1.0.0
     */
    protected ?SpeculativeRulesGenerator $speculativeRules = null;

    /**
     * Prefetch URL manager (lazily instantiated when first accessed).
     *
     * @since 1.0.0
     */
    protected ?PrefetchManager $prefetchManager = null;

    /**
     * Prerender URL manager (lazily instantiated when first accessed).
     *
     * @since 1.0.0
     */
    protected ?PrerenderManager $prerenderManager = null;

    /**
     * Embed optimizer (lazily instantiated when first accessed).
     *
     * @since 1.0.0
     */
    protected ?EmbedOptimizer $embedOptimizer = null;

    /**
     * Page cache manager (lazily instantiated when first accessed).
     *
     * @since 1.0.0
     */
    protected ?PageCacheManager $pageCache = null;

    /**
     * Fragment cache (lazily instantiated when first accessed).
     *
     * @since 1.0.0
     */
    protected ?FragmentCache $fragmentCache = null;

    /**
     * Cache invalidator (lazily instantiated when first accessed).
     *
     * @since 1.0.0
     */
    protected ?CacheInvalidator $cacheInvalidator = null;

    /**
     * Creates a new performance service.
     *
     * @since 1.0.0
     *
     * @param  ImageService|null  $images  Optional image service override.
     * @param  ResponsiveImageGenerator|null  $responsiveImages  Optional responsive generator override.
     * @param  ScriptManager|null  $scripts  Optional script manager override.
     * @param  CriticalCssExtractor|null  $criticalCss  Optional critical CSS extractor override.
     * @param  ResourceHintInjector|null  $resourceHints  Optional resource hint injector override.
     * @param  SpeculativeRulesGenerator|null  $speculativeRules  Optional rules generator override.
     * @param  PrefetchManager|null  $prefetchManager  Optional prefetch manager override.
     * @param  PrerenderManager|null  $prerenderManager  Optional prerender manager override.
     * @param  EmbedOptimizer|null  $embedOptimizer  Optional embed optimizer override.
     * @param  PageCacheManager|null  $pageCache  Optional page cache manager override.
     * @param  FragmentCache|null  $fragmentCache  Optional fragment cache override.
     * @param  CacheInvalidator|null  $cacheInvalidator  Optional invalidator override.
     */
    public function __construct(
        ?ImageService $images = null,
        ?ResponsiveImageGenerator $responsiveImages = null,
        ?ScriptManager $scripts = null,
        ?CriticalCssExtractor $criticalCss = null,
        ?ResourceHintInjector $resourceHints = null,
        ?SpeculativeRulesGenerator $speculativeRules = null,
        ?PrefetchManager $prefetchManager = null,
        ?PrerenderManager $prerenderManager = null,
        ?EmbedOptimizer $embedOptimizer = null,
        ?PageCacheManager $pageCache = null,
        ?FragmentCache $fragmentCache = null,
        ?CacheInvalidator $cacheInvalidator = null,
    ) {
        $this->images           = $images ?? new ImageService;
        $this->responsiveImages = $responsiveImages;
        $this->scripts          = $scripts;
        $this->criticalCss      = $criticalCss;
        $this->resourceHints    = $resourceHints;
        $this->speculativeRules = $speculativeRules;
        $this->prefetchManager  = $prefetchManager;
        $this->prerenderManager = $prerenderManager;
        $this->embedOptimizer   = $embedOptimizer;
        $this->pageCache        = $pageCache;
        $this->fragmentCache    = $fragmentCache;
        $this->cacheInvalidator = $cacheInvalidator;
    }

    /**
     * Returns the underlying image service.
     *
     * Exposes the image pipeline for callers that need to call methods not
     * proxied by the facade (resize, generateSrcset, generatePlaceholder).
     *
     * @since 1.0.0
     */
    public function images(): ImageService
    {
        return $this->images;
    }

    /**
     * Returns the responsive image generator.
     *
     * @since 1.0.0
     */
    public function responsiveImages(): ResponsiveImageGenerator
    {
        return $this->responsiveImages ??= new ResponsiveImageGenerator( $this->images );
    }

    /**
     * Checks whether a Performance feature is enabled.
     *
     * Reads the `artisanpack.performance.features.<name>` config key. Returns
     * `false` when the feature flag is missing so unknown features always
     * behave as disabled.
     *
     * @since 1.0.0
     *
     * @param  string  $feature  The feature key (e.g. `image_optimization`).
     *
     * @return bool True when the feature is enabled, false otherwise.
     */
    public function isFeatureEnabled( string $feature ): bool
    {
        return (bool) config( "artisanpack.performance.features.{$feature}", false );
    }

    /**
     * Optimizes an image at the given path.
     *
     * Delegates to the underlying `ImageService` which generates the
     * configured format derivatives (WebP/AVIF) at every configured width,
     * fires the `ImageOptimized` event, and returns the produced variants.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  array<string, mixed>  $options  Optional optimization overrides.
     *
     * @return array<string, mixed> The optimization result payload.
     */
    public function optimizeImage( string $path, array $options = [] ): array
    {
        return $this->images->optimize( $path, $options );
    }

    /**
     * Converts the given image to WebP.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  int  $quality  Output quality, 0-100.
     *
     * @return string Path to the generated WebP file.
     */
    public function convertToWebP( string $path, int $quality = 80 ): string
    {
        return $this->images->convertFormat( $path, 'webp', $quality );
    }

    /**
     * Converts the given image to AVIF.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  int  $quality  Output quality, 0-100.
     *
     * @return string Path to the generated AVIF file.
     */
    public function convertToAvif( string $path, int $quality = 70 ): string
    {
        return $this->images->convertFormat( $path, 'avif', $quality );
    }

    /**
     * Extracts the dominant color from the given image.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @return string Hex color string `#rrggbb`.
     */
    public function getDominantColor( string $path ): string
    {
        return $this->images->extractDominantColor( $path );
    }

    /**
     * Generates a responsive srcset string for the given image.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  array<int,int>  $sizes  Widths to include in the srcset.
     *
     * @return string The srcset attribute value.
     */
    public function getResponsiveSrcset( string $path, array $sizes ): string
    {
        return $this->responsiveImages()->generateSrcset( $path, $sizes );
    }

    /**
     * Generates every responsive variant (sizes × formats) for the given image.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  array<int, int>|null  $sizes  Widths to generate.
     * @param  array<int, string>|null  $formats  Formats to convert to.
     *
     * @return array<int, array{width: int, format: string, path: string}>
     */
    public function generateResponsiveVariants( string $path, ?array $sizes = null, ?array $formats = null ): array
    {
        return $this->responsiveImages()->generate( $path, $sizes, $formats );
    }

    /**
     * Returns the script manager, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function scripts(): ScriptManager
    {
        return $this->scripts ??= new ScriptManager;
    }

    /**
     * Returns the critical CSS extractor, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function criticalCss(): CriticalCssExtractor
    {
        return $this->criticalCss ??= new CriticalCssExtractor;
    }

    /**
     * Returns the resource hint injector, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function resourceHints(): ResourceHintInjector
    {
        return $this->resourceHints ??= new ResourceHintInjector;
    }

    /**
     * Returns the speculative rules generator, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function speculativeRules(): SpeculativeRulesGenerator
    {
        return $this->speculativeRules ??= new SpeculativeRulesGenerator;
    }

    /**
     * Returns the prefetch URL manager, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function prefetchManager(): PrefetchManager
    {
        return $this->prefetchManager ??= new PrefetchManager;
    }

    /**
     * Returns the prerender URL manager, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function prerenderManager(): PrerenderManager
    {
        return $this->prerenderManager ??= new PrerenderManager;
    }

    /**
     * Returns the embed optimizer, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function embedOptimizer(): EmbedOptimizer
    {
        return $this->embedOptimizer ??= new EmbedOptimizer;
    }

    /**
     * Returns the page cache manager, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function pageCache(): PageCacheManager
    {
        return $this->pageCache ??= new PageCacheManager;
    }

    /**
     * Returns the fragment cache, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function fragmentCache(): FragmentCache
    {
        return $this->fragmentCache ??= new FragmentCache;
    }

    /**
     * Returns the cache invalidator, instantiating one on first access.
     *
     * @since 1.0.0
     */
    public function cacheInvalidator(): CacheInvalidator
    {
        return $this->cacheInvalidator ??= new CacheInvalidator( $this->pageCache(), $this->fragmentCache() );
    }

    /**
     * Caches a fragment of view output produced by the callback.
     *
     * Proxy for `fragmentCache()->remember()` with the standard `(key, ttl,
     * callback, tags)` signature.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key.
     * @param  int  $ttl  Time-to-live in seconds.
     * @param  Closure  $callback  Callback whose return value is cached.
     * @param  array<int, string>  $tags  Tags to associate with the fragment.
     *
     * @return mixed
     */
    public function fragmentRemember( string $key, int $ttl, Closure $callback, array $tags = [] ): mixed
    {
        return $this->fragmentCache()->remember( $key, $ttl, $callback, $tags );
    }

    /**
     * Invalidates page cache entries matching the given pattern.
     *
     * @since 1.0.0
     *
     * @param  string  $pattern  Path pattern with optional wildcards.
     */
    public function invalidatePageCache( string $pattern ): int
    {
        return $this->cacheInvalidator()->invalidatePagePattern( $pattern );
    }

    /**
     * Flushes every page cache entry written by the package.
     *
     * @since 1.0.0
     */
    public function flushPageCache(): int
    {
        return $this->cacheInvalidator()->flushPageCache();
    }

    /**
     * Invalidates fragment cache entries registered under the given tag.
     *
     * @since 1.0.0
     *
     * @param  string  $tag  Tag name.
     */
    public function invalidateFragmentsByTag( string $tag ): int
    {
        return $this->cacheInvalidator()->invalidateFragmentTag( $tag );
    }

    /**
     * Warms the page cache for the given URLs.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $urls  URLs to warm.
     *
     * @return array<string, array{status: int|null, ok: bool, error: string|null}>
     */
    public function warmPageCache( array $urls ): array
    {
        return $this->pageCache()->warmPageCache( $urls );
    }

    /**
     * Retrieves a cached page payload for the given request, if any.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     *
     * @return array{status: int, content: string, headers: array<string, string>}|null
     */
    public function getCachedPage( Request $request ): ?array
    {
        return $this->pageCache()->getCachedResponse( $request );
    }

    /**
     * Registers URLs for prefetching at the configured eagerness.
     *
     * Proxy for `prefetchManager()->register()`; returns the service so
     * callers can chain further fluent calls (e.g. `prerender(...)`).
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|string  $urls  URL or list of URLs.
     * @param  string  $priority  Priority level (`high`, `medium`, `low`).
     *
     * @return $this
     */
    public function prefetch( string|array $urls, string $priority = PrefetchManager::DEFAULT_PRIORITY ): self
    {
        $this->prefetchManager()->register( $urls, $priority );

        return $this;
    }

    /**
     * Registers URLs for prerendering at the configured eagerness.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|string  $urls  URL or list of URLs.
     * @param  string  $priority  Priority level (`high`, `medium`, `low`).
     *
     * @return $this
     */
    public function prerender( string|array $urls, string $priority = PrerenderManager::DEFAULT_PRIORITY ): self
    {
        $this->prerenderManager()->register( $urls, $priority );

        return $this;
    }

    /**
     * Removes prefetched URLs matching the given pattern.
     *
     * @since 1.0.0
     *
     * @param  string  $pattern  URL or glob pattern.
     *
     * @return $this
     */
    public function clearPrefetch( string $pattern ): self
    {
        $this->prefetchManager()->clear( $pattern );

        return $this;
    }

    /**
     * Removes prerendered URLs matching the given pattern.
     *
     * @since 1.0.0
     *
     * @param  string  $pattern  URL or glob pattern.
     *
     * @return $this
     */
    public function clearPrerender( string $pattern ): self
    {
        $this->prerenderManager()->clear( $pattern );

        return $this;
    }

    /**
     * Registers a script with the script manager and returns its registration.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Script source URL or path.
     */
    public function script( string $src ): ScriptRegistration
    {
        return $this->scripts()->register( $src );
    }

    /**
     * Returns every registered script in priority order.
     *
     * @since 1.0.0
     *
     * @return array<int, ScriptRegistration>
     */
    public function getScripts(): array
    {
        return $this->scripts()->all();
    }

    /**
     * Renders every registered script to an HTML block.
     *
     * @since 1.0.0
     */
    public function renderScripts(): string
    {
        return $this->scripts()->render();
    }

    /**
     * Remembers a value in cache for the given TTL.
     *
     * Routes through the store named by `artisanpack.performance.fragment_cache.driver`
     * (falling back to the framework default when unset) and namespaces every
     * key under the `performance:` prefix.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The cache key.
     * @param  int  $ttl  Time-to-live in seconds.
     * @param  Closure  $callback  Callback whose return value is cached.
     *
     * @return mixed The cached or freshly computed value.
     */
    public function remember( string $key, int $ttl, Closure $callback ): mixed
    {
        $store = config( 'artisanpack.performance.fragment_cache.driver' );

        return cache()->store( $store )->remember( "performance:{$key}", $ttl, $callback );
    }

    /**
     * Remembers a value indefinitely.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The cache key.
     * @param  Closure  $callback  Callback whose return value is cached.
     *
     * @return mixed The cached or freshly computed value.
     */
    public function rememberForever( string $key, Closure $callback ): mixed
    {
        $store = config( 'artisanpack.performance.fragment_cache.driver' );

        return cache()->store( $store )->rememberForever( "performance:{$key}", $callback );
    }

    /**
     * Invalidates a single namespaced cache key.
     *
     * Accepts the bare key (e.g. `products`) and applies the package's
     * `performance:` prefix internally.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The cache key to forget.
     *
     * @return bool True when the key was forgotten.
     */
    public function invalidateCache( string $key ): bool
    {
        $store = config( 'artisanpack.performance.fragment_cache.driver' );

        return (bool) cache()->store( $store )->forget( "performance:{$key}" );
    }

    /**
     * Flushes the entire fragment cache store wholesale.
     *
     * Refuses when the fragment-cache driver resolves to the framework's
     * default cache store, since Laravel's cache contract exposes only
     * store-wide `flush()` (not prefix-scoped deletion) — flushing the
     * default store would also wipe sessions, locks, rate-limiter state,
     * and unrelated app cache entries. Configure
     * `artisanpack.performance.fragment_cache.driver` to a dedicated store
     * (a separate redis db, an isolated file disk, etc.) to opt in.
     *
     * @since 1.0.0
     *
     * @throws RuntimeException When the fragment store would also wipe the framework default cache.
     *
     * @return bool True when the store was flushed.
     */
    public function flushCache(): bool
    {
        $fragmentStore = config( 'artisanpack.performance.fragment_cache.driver' );
        $defaultStore  = config( 'cache.default' );

        // A null fragment driver routes through the default store; an explicit
        // match collapses to the same store. Either way, flushing would nuke
        // unrelated app cache entries.
        if ( null === $fragmentStore || $fragmentStore === $defaultStore ) {
            throw new RuntimeException(
                'Refusing to flush the framework default cache store. Configure '
                . 'artisanpack.performance.fragment_cache.driver to a dedicated store first.',
            );
        }

        return (bool) cache()->store( $fragmentStore )->flush();
    }

    /**
     * Records a single performance metric sample.
     *
     * Phase 1 is a no-op; Phase 8 wires this up to the monitoring collector
     * which aggregates samples into the `performance_metrics` table.
     *
     * @since 1.0.0
     *
     * @param  string  $name  The metric name (e.g. `LCP`).
     * @param  float  $value  The metric value.
     * @param  array<string, mixed>  $context  Optional contextual data.
     */
    public function recordMetric( string $name, float $value, array $context = [] ): void
    {
        // Implemented in Phase 8 (monitoring).
    }

    /**
     * Returns recommended performance actions for the current configuration.
     *
     * Phase 1 returns an empty list; Phase 8 (monitoring) wires this up to
     * the recommendations engine that inspects aggregated metrics, slow
     * queries, and feature toggles to surface concrete suggestions.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>> List of recommendation payloads.
     */
    public function getRecommendations(): array
    {
        return [];
    }
}
