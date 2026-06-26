<?php

/**
 * Performance service.
 *
 * Central service exposing the package's image, scripting, caching, and
 * monitoring APIs. The Performance facade resolves to an instance of this
 * class. Methods are stubbed during Phase 1 scaffolding and filled in by
 * subsequent feature phases (image optimization, caching, monitoring).
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Services;

use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use Closure;
use RuntimeException;

/**
 * Performance service class.
 *
 * Resolved by the `performance` container binding and the Performance facade.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class PerformanceService
{
	/**
	 * Image service used for optimization, format conversion, and color extraction.
	 *
	 * @since 1.0.0
	 *
	 * @var ImageService
	 */
	protected ImageService $images;

	/**
	 * Responsive image generator (lazily resolved against `$images`).
	 *
	 * @since 1.0.0
	 *
	 * @var ResponsiveImageGenerator|null
	 */
	protected ?ResponsiveImageGenerator $responsiveImages = null;

	/**
	 * Creates a new performance service.
	 *
	 * @since 1.0.0
	 *
	 * @param ImageService|null             $images           Optional image service override.
	 * @param ResponsiveImageGenerator|null $responsiveImages Optional responsive generator override.
	 */
	public function __construct(
		?ImageService $images = null,
		?ResponsiveImageGenerator $responsiveImages = null,
	) {
		$this->images           = $images ?? new ImageService();
		$this->responsiveImages = $responsiveImages;
	}

	/**
	 * Returns the underlying image service.
	 *
	 * Exposes the image pipeline for callers that need to call methods not
	 * proxied by the facade (resize, generateSrcset, generatePlaceholder).
	 *
	 * @since 1.0.0
	 *
	 * @return ImageService
	 */
	public function images(): ImageService
	{
		return $this->images;
	}

	/**
	 * Returns the responsive image generator.
	 *
	 * @since 1.0.0
	 *
	 * @return ResponsiveImageGenerator
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
	 * @param string $feature The feature key (e.g. `image_optimization`).
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
	 * @param string               $path    Absolute path to the source image.
	 * @param array<string, mixed> $options Optional optimization overrides.
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
	 * @param string $path    Absolute path to the source image.
	 * @param int    $quality Output quality, 0-100.
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
	 * @param string $path    Absolute path to the source image.
	 * @param int    $quality Output quality, 0-100.
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
	 * @param string $path Absolute path to the source image.
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
	 * @param string         $path  Absolute path to the source image.
	 * @param array<int,int> $sizes Widths to include in the srcset.
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
	 * @param string                  $path    Absolute path to the source image.
	 * @param array<int, int>|null    $sizes   Widths to generate.
	 * @param array<int, string>|null $formats Formats to convert to.
	 *
	 * @return array<int, array{width: int, format: string, path: string}>
	 */
	public function generateResponsiveVariants( string $path, ?array $sizes = null, ?array $formats = null ): array
	{
		return $this->responsiveImages()->generate( $path, $sizes, $formats );
	}

	/**
	 * Registers a script with the package's script manager.
	 *
	 * Phase 1 returns an empty array so callers can't couple to a fabricated
	 * shape. Phase 3 will swap the return type to a fluent ScriptBuilder.
	 *
	 * @since 1.0.0
	 *
	 * @param string $src The script source URL or path.
	 *
	 * @return array<string, mixed> A descriptor for the registered script.
	 */
	public function script( string $src ): array
	{
		return [];
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
	 * @param string  $key      The cache key.
	 * @param int     $ttl      Time-to-live in seconds.
	 * @param Closure $callback Callback whose return value is cached.
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
	 * @param string  $key      The cache key.
	 * @param Closure $callback Callback whose return value is cached.
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
	 * @param string $key The cache key to forget.
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
	 * @param string               $name    The metric name (e.g. `LCP`).
	 * @param float                $value   The metric value.
	 * @param array<string, mixed> $context Optional contextual data.
	 *
	 * @return void
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
