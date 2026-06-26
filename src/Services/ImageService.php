<?php

/**
 * Image service.
 *
 * Central service for the package's image optimization features. Coordinates
 * format conversion (WebP/AVIF), resizing, srcset generation, dominant color
 * extraction, and placeholder generation. Heavy lifting is delegated to
 * `FormatConverter` and the active driver (GD or Imagick).
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

use ArtisanPackUI\Performance\Events\ImageOptimized;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use GdImage;
use Imagick;
use ImagickException;
use RuntimeException;

/**
 * Image service class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class ImageService
{
	/**
	 * Format converter.
	 *
	 * @since 1.0.0
	 *
	 * @var FormatConverter
	 */
	protected FormatConverter $converter;

	/**
	 * Creates a new image service.
	 *
	 * @since 1.0.0
	 *
	 * @param FormatConverter|null $converter Optional converter override; defaults to a converter using the configured driver.
	 */
	public function __construct( ?FormatConverter $converter = null )
	{
		$this->converter = $converter ?? new FormatConverter();
	}

	/**
	 * Runs the optimization pipeline for the given image.
	 *
	 * Generates the requested format derivatives at each requested width and
	 * returns a structured payload describing every file produced. Fires the
	 * `ImageOptimized` event once the pipeline completes.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path    Absolute path to the source image.
	 * @param array<string, mixed> $options Optional overrides:
	 *                                      - `sizes`   array<int> Widths to generate (defaults to config).
	 *                                      - `formats` array<string> Formats to generate (defaults to enabled formats).
	 *                                      - `quality` int Quality applied to every format.
	 *
	 * @throws RuntimeException When the source image cannot be read.
	 *
	 * @return array<string, mixed> Optimization payload:
	 *                              - `source`     string Path to the source image.
	 *                              - `sizes`      array<int> Widths that were generated.
	 *                              - `formats`    array<string> Formats that were generated.
	 *                              - `variants`   array<int, array{path: string, format: string, width: int}>
	 */
	public function optimize( string $path, array $options = [] ): array
	{
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new RuntimeException( "Source image is not readable: {$path}" );
		}

		$dimensions = getimagesize( $path );

		if ( false === $dimensions ) {
			throw new RuntimeException( "Unable to read image metadata: {$path}" );
		}

		$sourceWidth = (int) $dimensions[0];
		$sizes       = $this->clampSizes( $this->resolveSizes( $options ), $sourceWidth );
		$formats     = $this->resolveFormats( $options );
		$variants    = [];

		foreach ( $sizes as $width ) {
			$resized = $this->resize( $path, $width );

			foreach ( $formats as $format ) {
				if ( ! $this->supportsFormat( $format ) ) {
					continue;
				}

				$quality = $this->resolveQuality( $format, $options );
				$variant = $this->converter->convert( $resized, $format, $quality );

				$variants[] = [
					'path'   => $variant,
					'format' => $format,
					'width'  => $width,
				];
			}

			if ( $resized !== $path ) {
				@unlink( $resized );
			}
		}

		$producedFormats = array_values( array_unique( array_map(
			static fn ( array $variant ): string => $variant['format'],
			$variants,
		) ) );

		if ( ! empty( $variants ) ) {
			ImageOptimized::dispatch( $path, $producedFormats, $sizes );
		}

		return [
			'source'   => $path,
			'sizes'    => $sizes,
			'formats'  => $producedFormats,
			'variants' => $variants,
		];
	}

	/**
	 * Converts the source image to the requested format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path    Absolute path to the source image.
	 * @param string $format  Target format (`webp` or `avif`).
	 * @param int    $quality Output quality (0-100).
	 *
	 * @return string Absolute path to the generated file.
	 */
	public function convertFormat( string $path, string $format, int $quality ): string
	{
		return $this->converter->convert( $path, $format, $quality );
	}

	/**
	 * Resizes the source image to the requested dimensions.
	 *
	 * When `$height` is null the height is computed to preserve the source
	 * aspect ratio. When the source is smaller than the target width the
	 * original path is returned unchanged.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $path   Absolute path to the source image.
	 * @param int      $width  Target width in pixels.
	 * @param int|null $height Optional target height; computed when null.
	 *
	 * @throws RuntimeException When the source image cannot be read.
	 *
	 * @return string Absolute path to the resized image (or original when no resize was needed).
	 */
	public function resize( string $path, int $width, ?int $height = null ): string
	{
		$dimensions = getimagesize( $path );

		if ( false === $dimensions ) {
			throw new RuntimeException( "Unable to read image metadata: {$path}" );
		}

		[ $sourceWidth, $sourceHeight ] = $dimensions;

		// Skip only when the caller asked for the aspect-preserving default AND
		// the source is already small enough — never short-circuit when an
		// explicit height was supplied or we'd silently return wrong dimensions.
		if ( null === $height && $sourceWidth <= $width ) {
			return $path;
		}

		$targetHeight = $height ?? (int) round( ( $width / $sourceWidth ) * $sourceHeight );
		$destination  = $this->resizedPath( $path, $width );

		if ( $this->converter->usesImagick() ) {
			$this->resizeWithImagick( $path, $destination, $width, $targetHeight );

			return $destination;
		}

		$this->resizeWithGd( $path, $destination, $sourceWidth, $sourceHeight, $width, $targetHeight );

		return $destination;
	}

	/**
	 * Generates a responsive `srcset` value for the given widths.
	 *
	 * Each width is generated as a separate file and returned as `path Wn`
	 * entries joined with commas, matching the standard HTML srcset syntax.
	 *
	 * @since 1.0.0
	 *
	 * @param string         $path  Absolute path to the source image.
	 * @param array<int,int> $sizes Widths to generate.
	 *
	 * @return string The srcset attribute value.
	 */
	public function generateSrcset( string $path, array $sizes ): string
	{
		$entries = [];

		foreach ( $sizes as $width ) {
			$resized   = $this->resize( $path, $width );
			$entries[] = $resized . ' ' . $width . 'w';
		}

		return implode( ', ', $entries );
	}

	/**
	 * Extracts the dominant color from the source image.
	 *
	 * Samples the image at low resolution and averages the channels to
	 * produce a 7-character hex string suitable for use as an LQIP background.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the source image.
	 *
	 * @throws RuntimeException When the image cannot be opened.
	 *
	 * @return string Hex color string in the form `#rrggbb`.
	 */
	public function extractDominantColor( string $path ): string
	{
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new RuntimeException( "Source image is not readable: {$path}" );
		}

		if ( $this->converter->usesImagick() ) {
			return $this->dominantColorWithImagick( $path );
		}

		return $this->dominantColorWithGd( $path );
	}

	/**
	 * Generates a placeholder representation of the image.
	 *
	 * Supported types:
	 *  - `dominant_color`: returns the dominant color hex string.
	 *  - `blur`: returns a tiny base64-encoded blurred preview suitable for
	 *            inline `src` attributes (LQIP).
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the source image.
	 * @param string $type Placeholder type (`dominant_color` or `blur`).
	 *
	 * @throws RuntimeException When the placeholder type is unknown.
	 *
	 * @return string Hex color string or base64 data URI depending on type.
	 */
	public function generatePlaceholder( string $path, string $type = 'dominant_color' ): string
	{
		return match ( $type ) {
			'dominant_color' => $this->extractDominantColor( $path ),
			'blur'           => $this->generateBlurPlaceholder( $path ),
			default          => throw new RuntimeException( "Unknown placeholder type: {$type}" ),
		};
	}

	/**
	 * Reports whether the active driver can encode the given format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format Target format (`webp` or `avif`).
	 *
	 * @return bool True when the format is supported.
	 */
	public function supportsFormat( string $format ): bool
	{
		return $this->converter->supports( $format );
	}

	/**
	 * Returns the underlying format converter.
	 *
	 * @since 1.0.0
	 *
	 * @return FormatConverter
	 */
	public function converter(): FormatConverter
	{
		return $this->converter;
	}

	/**
	 * Resolves the widths to generate from options/config.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $options Caller-provided options.
	 *
	 * @return array<int, int> Widths sorted ascending and deduplicated.
	 */
	protected function resolveSizes( array $options ): array
	{
		$sizes = $options['sizes'] ?? config( 'artisanpack.performance.images.sizes', [] );
		$sizes = array_values( array_unique( array_map( 'intval', (array) $sizes ) ) );
		sort( $sizes );

		return $sizes;
	}

	/**
	 * Clamps requested widths to the source width and dedupes.
	 *
	 * Prevents upscaling and ensures each width produces a distinct variant
	 * file (widths greater than the source would otherwise all collide on
	 * the same FormatConverter destination).
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, int> $sizes       Widths requested by the caller.
	 * @param int             $sourceWidth Source image width in pixels.
	 *
	 * @return array<int, int> Effective widths to generate, sorted ascending.
	 */
	protected function clampSizes( array $sizes, int $sourceWidth ): array
	{
		$clamped = array_values( array_unique( array_map(
			static fn ( int $width ): int => min( $width, $sourceWidth ),
			$sizes,
		) ) );

		sort( $clamped );

		return $clamped;
	}

	/**
	 * Resolves the formats to generate from options/config.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $options Caller-provided options.
	 *
	 * @return array<int, string> Format keys in the order they should be generated.
	 */
	protected function resolveFormats( array $options ): array
	{
		if ( isset( $options['formats'] ) ) {
			return array_values( array_map( 'strtolower', (array) $options['formats'] ) );
		}

		$configured = (array) config( 'artisanpack.performance.images.formats', [] );
		$enabled    = [];

		foreach ( $configured as $format => $settings ) {
			if ( ! empty( $settings['enabled'] ) ) {
				$enabled[] = (string) $format;
			}
		}

		return $enabled;
	}

	/**
	 * Resolves the quality for the given format from options/config.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $format  Target format.
	 * @param array<string, mixed> $options Caller-provided options.
	 *
	 * @return int Quality clamped between 0 and 100.
	 */
	protected function resolveQuality( string $format, array $options ): int
	{
		if ( isset( $options['quality'] ) ) {
			return max( 0, min( 100, (int) $options['quality'] ) );
		}

		$default = 'webp' === $format
			? FormatConverter::DEFAULT_WEBP_QUALITY
			: FormatConverter::DEFAULT_AVIF_QUALITY;

		$configured = config( "artisanpack.performance.images.formats.{$format}.quality", $default );

		return max( 0, min( 100, (int) $configured ) );
	}

	/**
	 * Builds the destination path for a resized image.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path  Absolute path to the source image.
	 * @param int    $width Target width.
	 *
	 * @return string Absolute path for the resized derivative.
	 */
	protected function resizedPath( string $path, int $width ): string
	{
		$directory = dirname( $path );
		$basename  = pathinfo( $path, PATHINFO_FILENAME );
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		return $directory . DIRECTORY_SEPARATOR . $basename . '-' . $width . 'w.' . $extension;
	}

	/**
	 * Resizes the source image using Imagick.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path        Absolute path to the source image.
	 * @param string $destination Absolute path to write the resized image.
	 * @param int    $width       Target width.
	 * @param int    $height      Target height.
	 *
	 * @throws RuntimeException When Imagick fails to resize the image.
	 *
	 * @return void
	 */
	protected function resizeWithImagick( string $path, string $destination, int $width, int $height ): void
	{
		$image = null;

		try {
			$image = new Imagick( $path );
			$image->resizeImage( $width, $height, Imagick::FILTER_LANCZOS, 1 );
			$image->writeImage( $destination );
		} catch ( ImagickException $exception ) {
			throw new RuntimeException(
				"Imagick failed to resize {$path}: " . $exception->getMessage(),
				0,
				$exception,
			);
		} finally {
			$image?->clear();
		}
	}

	/**
	 * Resizes the source image using GD.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path         Absolute path to the source image.
	 * @param string $destination  Absolute path to write the resized image.
	 * @param int    $sourceWidth  Source image width.
	 * @param int    $sourceHeight Source image height.
	 * @param int    $width        Target width.
	 * @param int    $height       Target height.
	 *
	 * @throws RuntimeException When GD cannot encode the resized image.
	 *
	 * @return void
	 */
	protected function resizeWithGd(
		string $path,
		string $destination,
		int $sourceWidth,
		int $sourceHeight,
		int $width,
		int $height,
	): void {
		$source = $this->createGdImage( $path );
		$target = imagecreatetruecolor( $width, $height );

		imagealphablending( $target, false );
		imagesavealpha( $target, true );
		$transparent = imagecolorallocatealpha( $target, 0, 0, 0, 127 );
		imagefilledrectangle( $target, 0, 0, $width, $height, $transparent );

		imagecopyresampled( $target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight );

		$result = $this->writeGdImage( $target, $destination, $path );

		imagedestroy( $source );
		imagedestroy( $target );

		if ( false === $result ) {
			throw new RuntimeException( "GD failed to write resized image: {$destination}" );
		}
	}

	/**
	 * Writes a GD image to disk using the encoder matching the destination extension.
	 *
	 * @since 1.0.0
	 *
	 * @param GdImage $image       GD image resource.
	 * @param string  $destination Absolute destination path.
	 * @param string  $sourcePath  Source path (used to pick the encoder when the destination has no extension).
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function writeGdImage( GdImage $image, string $destination, string $sourcePath ): bool
	{
		$extension = strtolower( pathinfo( $destination, PATHINFO_EXTENSION ) );

		if ( '' === $extension ) {
			$extension = strtolower( pathinfo( $sourcePath, PATHINFO_EXTENSION ) );
		}

		return match ( $extension ) {
			'jpg', 'jpeg' => imagejpeg( $image, $destination, 90 ),
			'png'         => imagepng( $image, $destination ),
			'gif'         => imagegif( $image, $destination ),
			'webp'        => imagewebp( $image, $destination, 80 ),
			default       => imagepng( $image, $destination ),
		};
	}

	/**
	 * Extracts the dominant color using Imagick.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the source image.
	 *
	 * @throws RuntimeException When Imagick fails to read the image.
	 *
	 * @return string Hex color string `#rrggbb`.
	 */
	protected function dominantColorWithImagick( string $path ): string
	{
		$image = null;

		try {
			$image = new Imagick( $path );
			$image->resizeImage( 1, 1, Imagick::FILTER_LANCZOS, 1 );
			$pixel = $image->getImagePixelColor( 0, 0 )->getColor();
		} catch ( ImagickException $exception ) {
			throw new RuntimeException(
				"Imagick failed to sample {$path}: " . $exception->getMessage(),
				0,
				$exception,
			);
		} finally {
			$image?->clear();
		}

		return sprintf( '#%02x%02x%02x', $pixel['r'], $pixel['g'], $pixel['b'] );
	}

	/**
	 * Extracts the dominant color using GD.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the source image.
	 *
	 * @return string Hex color string `#rrggbb`.
	 */
	protected function dominantColorWithGd( string $path ): string
	{
		$source = $this->createGdImage( $path );
		$thumb  = imagecreatetruecolor( 1, 1 );

		imagecopyresampled( $thumb, $source, 0, 0, 0, 0, 1, 1, imagesx( $source ), imagesy( $source ) );
		$rgb = imagecolorat( $thumb, 0, 0 );

		imagedestroy( $source );
		imagedestroy( $thumb );

		$r = ( $rgb >> 16 ) & 0xFF;
		$g = ( $rgb >> 8 ) & 0xFF;
		$b = $rgb & 0xFF;

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Generates a small base64-encoded blurred placeholder (LQIP).
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the source image.
	 *
	 * @throws RuntimeException When the placeholder cannot be generated.
	 *
	 * @return string Data URI of the placeholder.
	 */
	protected function generateBlurPlaceholder( string $path ): string
	{
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new RuntimeException( "Source image is not readable: {$path}" );
		}

		$source = $this->createGdImage( $path );
		$thumb  = imagecreatetruecolor( 16, 16 );

		imagecopyresampled( $thumb, $source, 0, 0, 0, 0, 16, 16, imagesx( $source ), imagesy( $source ) );

		ob_start();
		$encoded = imagejpeg( $thumb, null, 30 );
		$bytes   = ob_get_clean();

		imagedestroy( $source );
		imagedestroy( $thumb );

		if ( false === $encoded || false === $bytes || '' === $bytes ) {
			throw new RuntimeException( "GD failed to encode blur placeholder for: {$path}" );
		}

		return 'data:image/jpeg;base64,' . base64_encode( $bytes );
	}

	/**
	 * Creates a GD resource from the source image.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the source image.
	 *
	 * @throws RuntimeException When the source mime type cannot be decoded by GD.
	 *
	 * @return GdImage
	 */
	protected function createGdImage( string $path ): GdImage
	{
		$info = getimagesize( $path );

		if ( false === $info ) {
			throw new RuntimeException( "Unable to read image metadata: {$path}" );
		}

		$image = match ( $info[2] ) {
			IMAGETYPE_JPEG => imagecreatefromjpeg( $path ),
			IMAGETYPE_PNG  => imagecreatefrompng( $path ),
			IMAGETYPE_GIF  => imagecreatefromgif( $path ),
			IMAGETYPE_BMP  => imagecreatefrombmp( $path ),
			IMAGETYPE_WEBP => imagecreatefromwebp( $path ),
			default        => false,
		};

		if ( false === $image ) {
			throw new RuntimeException( "Unsupported image type for GD: {$path}" );
		}

		return $image;
	}
}
