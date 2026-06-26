<?php

/**
 * Performance helper functions.
 *
 * Global helpers exposed by the Performance package. Mirrors the public
 * API on the Performance facade for use in templates and lightweight
 * application code where dependency injection is impractical.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */

use ArtisanPackUI\Performance\Services\PerformanceService;

if ( ! function_exists( 'performance' ) ) {
	/**
	 * Resolves the PerformanceService instance from the container.
	 *
	 * @since 1.0.0
	 *
	 * @return PerformanceService
	 */
	function performance(): PerformanceService
	{
		return app( 'performance' );
	}
}

if ( ! function_exists( 'perfFeatureEnabled' ) ) {
	/**
	 * Reports whether a performance feature is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feature The feature key (e.g. `image_optimization`).
	 *
	 * @return bool
	 */
	function perfFeatureEnabled( string $feature ): bool
	{
		return performance()->isFeatureEnabled( $feature );
	}
}

if ( ! function_exists( 'perfOptimizeImage' ) ) {
	/**
	 * Runs the image optimization pipeline for the given path.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path    Absolute path to the source image.
	 * @param array<string, mixed> $options Optimization overrides forwarded to ImageService.
	 *
	 * @return array<string, mixed>
	 */
	function perfOptimizeImage( string $path, array $options = [] ): array
	{
		return performance()->optimizeImage( $path, $options );
	}
}

if ( ! function_exists( 'perfConvertToWebP' ) ) {
	/**
	 * Converts the given image to WebP.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path    Absolute path to the source image.
	 * @param int    $quality Output quality (0-100).
	 *
	 * @return string
	 */
	function perfConvertToWebP( string $path, int $quality = 80 ): string
	{
		return performance()->convertToWebP( $path, $quality );
	}
}

if ( ! function_exists( 'perfConvertToAvif' ) ) {
	/**
	 * Converts the given image to AVIF.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path    Absolute path to the source image.
	 * @param int    $quality Output quality (0-100).
	 *
	 * @return string
	 */
	function perfConvertToAvif( string $path, int $quality = 70 ): string
	{
		return performance()->convertToAvif( $path, $quality );
	}
}

if ( ! function_exists( 'perfGetDominantColor' ) ) {
	/**
	 * Returns the dominant color of the given image as a hex string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the source image.
	 *
	 * @return string
	 */
	function perfGetDominantColor( string $path ): string
	{
		return performance()->getDominantColor( $path );
	}
}

if ( ! function_exists( 'perfGetResponsiveSrcset' ) ) {
	/**
	 * Generates a responsive `srcset` value for the given image.
	 *
	 * @since 1.0.0
	 *
	 * @param string         $path  Absolute path to the source image.
	 * @param array<int,int> $sizes Widths to include in the srcset.
	 *
	 * @return string
	 */
	function perfGetResponsiveSrcset( string $path, array $sizes ): string
	{
		return performance()->getResponsiveSrcset( $path, $sizes );
	}
}

if ( ! function_exists( 'perfRemember' ) ) {
	/**
	 * Remembers a value in the package's namespaced cache for the given TTL.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $key      The cache key.
	 * @param int     $ttl      Time-to-live in seconds.
	 * @param Closure $callback Callback whose return value is cached.
	 *
	 * @return mixed
	 */
	function perfRemember( string $key, int $ttl, Closure $callback ): mixed
	{
		return performance()->remember( $key, $ttl, $callback );
	}
}

if ( ! function_exists( 'perfRememberForever' ) ) {
	/**
	 * Remembers a value in the package's namespaced cache indefinitely.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $key      The cache key.
	 * @param Closure $callback Callback whose return value is cached.
	 *
	 * @return mixed
	 */
	function perfRememberForever( string $key, Closure $callback ): mixed
	{
		return performance()->rememberForever( $key, $callback );
	}
}

if ( ! function_exists( 'perfInvalidateCache' ) ) {
	/**
	 * Invalidates the given namespaced cache key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key to forget.
	 *
	 * @return bool
	 */
	function perfInvalidateCache( string $key ): bool
	{
		return performance()->invalidateCache( $key );
	}
}

if ( ! function_exists( 'perfFlushCache' ) ) {
	/**
	 * Flushes the package's fragment cache store wholesale.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function perfFlushCache(): bool
	{
		return performance()->flushCache();
	}
}

if ( ! function_exists( 'perfRecordMetric' ) ) {
	/**
	 * Records a single performance metric sample.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $name    Metric name (e.g. `LCP`).
	 * @param float                $value   Metric value.
	 * @param array<string, mixed> $context Optional contextual data.
	 *
	 * @return void
	 */
	function perfRecordMetric( string $name, float $value, array $context = [] ): void
	{
		performance()->recordMetric( $name, $value, $context );
	}
}

if ( ! function_exists( 'perfGetRecommendations' ) ) {
	/**
	 * Returns recommended performance actions for the current configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function perfGetRecommendations(): array
	{
		return performance()->getRecommendations();
	}
}
