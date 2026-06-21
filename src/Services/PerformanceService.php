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

use Closure;

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
	 * Stubbed in Phase 1. The image optimization pipeline is implemented in
	 * Phase 2 and will return the generated derivatives (paths, sizes,
	 * formats) keyed by variant.
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
		return [];
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
		return $path;
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
		return $path;
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
}
