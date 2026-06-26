<?php

/**
 * Responsive image generator.
 *
 * Generates the multiple width variants and modern-format derivatives that
 * power responsive `<picture>` markup. Heavy lifting (resize + format
 * conversion) is delegated to `ImageService` so this class stays focused on
 * deciding which sizes/formats to produce and shaping the result for the
 * Blade components and queued jobs that consume it.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Images;

use ArtisanPackUI\Performance\Services\ImageService;
use RuntimeException;

/**
 * Responsive image generator class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class ResponsiveImageGenerator
{
	/**
	 * Image service used for resize/convert primitives.
	 *
	 * @since 1.0.0
	 *
	 * @var ImageService
	 */
	protected ImageService $images;

	/**
	 * Creates a new generator.
	 *
	 * @since 1.0.0
	 *
	 * @param ImageService|null $images Optional image service override.
	 */
	public function __construct( ?ImageService $images = null )
	{
		$this->images = $images ?? new ImageService();
	}

	/**
	 * Generates resized variants of the source at every requested width.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $path  Absolute path to the source image.
	 * @param array<int, int>|null  $sizes Widths to generate (defaults to config/DEFAULT_SIZES).
	 *
	 * @throws RuntimeException When the source is unreadable.
	 *
	 * @return array<int, string> Map of `width => generated path`, sorted ascending.
	 */
	public function generateSizes( string $path, ?array $sizes = null ): array
	{
		$this->guardReadable( $path );

		$resolved = $this->resolveSizes( $sizes, $path );
		$result   = [];

		foreach ( $resolved as $width ) {
			$result[ $width ] = $this->images->resize( $path, $width );
		}

		return $result;
	}

	/**
	 * Generates resized variants at every requested width and every enabled format.
	 *
	 * Each width is resized once and then converted to each format the active
	 * driver supports. Unsupported formats are skipped without failing so the
	 * caller still gets the original-format derivatives.
	 *
	 * @since 1.0.0
	 *
	 * @param string                  $path    Absolute path to the source image.
	 * @param array<int, int>|null    $sizes   Widths to generate.
	 * @param array<int, string>|null $formats Format keys to convert to (e.g. `['webp', 'avif']`).
	 *                                         Pass `[]` to skip format conversion. Defaults to enabled formats.
	 *
	 * @throws RuntimeException When the source is unreadable.
	 *
	 * @return array<int, array{width: int, format: string, path: string}>
	 */
	public function generate( string $path, ?array $sizes = null, ?array $formats = null ): array
	{
		$this->guardReadable( $path );

		$resolvedSizes   = $this->resolveSizes( $sizes, $path );
		$resolvedFormats = $this->resolveFormats( $formats );
		$variants        = [];

		foreach ( $resolvedSizes as $width ) {
			$resized    = $this->images->resize( $path, $width );
			$originalEx = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

			$variants[] = [
				'width'  => $width,
				'format' => $originalEx,
				'path'   => $resized,
			];

			foreach ( $resolvedFormats as $format ) {
				if ( ! $this->images->supportsFormat( $format ) ) {
					continue;
				}

				$variants[] = [
					'width'  => $width,
					'format' => $format,
					'path'   => $this->images->convertFormat( $resized, $format, $this->qualityFor( $format ) ),
				];
			}
		}

		return $variants;
	}

	/**
	 * Builds a `srcset` value for the given widths, optionally for a specific format.
	 *
	 * When `$format` is null the source-format derivatives are used; otherwise
	 * each width is converted to the requested format first. Returns an empty
	 * string when no variants could be generated (e.g. unsupported format).
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path   Absolute path to the source image.
	 * @param array<int, int>|null $sizes  Widths to include.
	 * @param string|null          $format Optional format to convert variants to before composing the srcset.
	 *
	 * @return string Srcset attribute value, or empty string when nothing was produced.
	 */
	public function generateSrcset( string $path, ?array $sizes = null, ?string $format = null ): string
	{
		$this->guardReadable( $path );

		$resolvedSizes = $this->resolveSizes( $sizes, $path );
		$entries       = [];

		foreach ( $resolvedSizes as $width ) {
			$variant = $this->images->resize( $path, $width );

			if ( null !== $format ) {
				$format = strtolower( $format );

				if ( ! $this->images->supportsFormat( $format ) ) {
					continue;
				}

				$variant = $this->images->convertFormat( $variant, $format, $this->qualityFor( $format ) );
			}

			$entries[] = $variant . ' ' . $width . 'w';
		}

		return implode( ', ', $entries );
	}

	/**
	 * Returns the widths the generator will use for the given source.
	 *
	 * Exposed so components and jobs can introspect the effective sizes
	 * without triggering a generation pass.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path  Absolute path to the source image.
	 * @param array<int, int>|null $sizes Caller-supplied widths.
	 *
	 * @return array<int, int>
	 */
	public function effectiveSizes( string $path, ?array $sizes = null ): array
	{
		$this->guardReadable( $path );

		return $this->resolveSizes( $sizes, $path );
	}

	/**
	 * Returns the underlying image service.
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
	 * Resolves the widths to generate, dedupes them, and clamps to the source width.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, int>|null $sizes Caller-supplied widths.
	 * @param string               $path  Absolute path to the source image.
	 *
	 * @throws RuntimeException When the source dimensions cannot be read.
	 *
	 * @return array<int, int> Effective widths sorted ascending.
	 */
	protected function resolveSizes( ?array $sizes, string $path ): array
	{
		// Fall back to the package config (which is itself defaulted in
		// config/performance.php → images.sizes). Keeping that file as the
		// single source of truth — duplicating the literal here would let
		// the two drift apart on future tweaks.
		$candidates = $sizes ?? config( 'artisanpack.performance.images.sizes', [] );

		$normalized = array_values( array_unique( array_map( 'intval', (array) $candidates ) ) );
		sort( $normalized );

		$dimensions = getimagesize( $path );

		if ( false === $dimensions ) {
			throw new RuntimeException( "Unable to read image metadata: {$path}" );
		}

		$sourceWidth = (int) $dimensions[0];
		$clamped     = array_values( array_unique( array_map(
			static fn ( int $width ): int => max( 1, min( $width, $sourceWidth ) ),
			$normalized,
		) ) );

		sort( $clamped );

		return $clamped;
	}

	/**
	 * Resolves the formats to convert to from the caller or config.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string>|null $formats Caller-supplied formats.
	 *
	 * @return array<int, string> Lowercased format keys.
	 */
	protected function resolveFormats( ?array $formats ): array
	{
		if ( null !== $formats ) {
			return array_values( array_map( 'strtolower', $formats ) );
		}

		$configured = (array) config( 'artisanpack.performance.images.formats', [] );
		$enabled    = [];

		foreach ( $configured as $format => $settings ) {
			if ( ! empty( $settings['enabled'] ) ) {
				$enabled[] = strtolower( (string) $format );
			}
		}

		return $enabled;
	}

	/**
	 * Resolves the configured quality for the given format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format Format key.
	 *
	 * @return int Quality 0-100.
	 */
	protected function qualityFor( string $format ): int
	{
		$default = 'webp' === $format ? 80 : 70;
		$value   = (int) config( "artisanpack.performance.images.formats.{$format}.quality", $default );

		return max( 0, min( 100, $value ) );
	}

	/**
	 * Guards that the source path is readable.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the source image.
	 *
	 * @throws RuntimeException When the source path is unreadable.
	 *
	 * @return void
	 */
	protected function guardReadable( string $path ): void
	{
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new RuntimeException( "Source image is not readable: {$path}" );
		}
	}
}
