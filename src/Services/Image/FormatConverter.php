<?php

/**
 * Image format converter.
 *
 * Converts source images (JPEG, PNG, GIF, BMP) to modern delivery formats
 * (WebP, AVIF) using the GD or Imagick extension. Transparency is preserved
 * for PNG sources, conversion errors are surfaced as `RuntimeException`s,
 * and unsupported format/driver combinations are reported via `supports()`
 * so callers can fall back rather than fail.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Services\Image;

use GdImage;
use Imagick;
use ImagickException;
use RuntimeException;

/**
 * Format converter class.
 *
 *
 * @since      1.0.0
 */
class FormatConverter
{
    /**
     * Default quality for WebP output.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const DEFAULT_WEBP_QUALITY = 80;

    /**
     * Default quality for AVIF output.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const DEFAULT_AVIF_QUALITY = 70;

    /**
     * Supported target formats.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SUPPORTED_FORMATS = ['webp', 'avif'];

    /**
     * Drivers this converter knows how to dispatch to.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SUPPORTED_DRIVERS = ['gd', 'imagick'];

    /**
     * Optional driver override pinned at construction time.
     *
     * Null means "always read from config", which is the production path.
     * Tests can pin a specific driver via the constructor without touching
     * config state.
     *
     * @since 1.0.0
     */
    protected ?string $driverOverride;

    /**
     * Creates a new converter.
     *
     * @since 1.0.0
     *
     * @param  string|null  $driver  Optional driver override; when null the driver
     *                               is read from `artisanpack.performance.images.driver`
     *                               on every call so runtime config changes take effect.
     */
    public function __construct( ?string $driver = null )
    {
        $this->driverOverride = $driver;
    }

    /**
     * Converts a source image to WebP.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  int  $quality  Output quality (0-100).
     *
     * @throws RuntimeException When the source is unreadable or the driver cannot encode WebP.
     *
     * @return string Absolute path to the generated WebP file.
     */
    public function toWebp( string $path, int $quality = self::DEFAULT_WEBP_QUALITY ): string
    {
        return $this->convert( $path, 'webp', $quality );
    }

    /**
     * Converts a source image to AVIF.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  int  $quality  Output quality (0-100).
     *
     * @throws RuntimeException When the source is unreadable or the driver cannot encode AVIF.
     *
     * @return string Absolute path to the generated AVIF file.
     */
    public function toAvif( string $path, int $quality = self::DEFAULT_AVIF_QUALITY ): string
    {
        return $this->convert( $path, 'avif', $quality );
    }

    /**
     * Converts a source image to the given target format.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  string  $format  Target format (`webp` or `avif`).
     * @param  int  $quality  Output quality (0-100).
     *
     * @throws RuntimeException When the source is unreadable, the format is unsupported, or encoding fails.
     *
     * @return string Absolute path to the generated file.
     */
    public function convert( string $path, string $format, int $quality ): string
    {
        $format = strtolower( $format );
        $driver = $this->driver();

        if ( ! in_array( $format, self::SUPPORTED_FORMATS, true ) ) {
            throw new RuntimeException( "Unsupported target format: {$format}" );
        }

        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            throw new RuntimeException( "Source image is not readable: {$path}" );
        }

        if ( ! $this->supports( $format ) ) {
            throw new RuntimeException( "Driver '{$driver}' cannot encode {$format} on this system" );
        }

        $quality     = max( 0, min( 100, $quality ) );
        $destination = $this->targetPath( $path, $format );

        if ( $this->usesImagick() ) {
            $this->convertWithImagick( $path, $destination, $format, $quality );
        } else {
            $this->convertWithGd( $path, $destination, $format, $quality );
        }

        return $destination;
    }

    /**
     * Reports whether the active driver can encode the given format.
     *
     * @since 1.0.0
     *
     * @param  string  $format  Target format (`webp` or `avif`).
     *
     * @return bool True when the driver can encode the requested format.
     */
    public function supports( string $format ): bool
    {
        $format = strtolower( $format );
        $driver = $this->driver();

        if ( ! in_array( $format, self::SUPPORTED_FORMATS, true ) ) {
            return false;
        }

        if ( ! in_array( $driver, self::SUPPORTED_DRIVERS, true ) ) {
            return false;
        }

        if ( 'imagick' === $driver ) {
            if ( ! class_exists( Imagick::class ) ) {
                return false;
            }

            try {
                $formats = (new Imagick)->queryFormats( strtoupper( $format ) );
            } catch ( ImagickException ) {
                return false;
            }

            return ! empty( $formats );
        }

        if ( 'webp' === $format ) {
            return function_exists( 'imagewebp' );
        }

        return function_exists( 'imageavif' );
    }

    /**
     * Returns the active driver name.
     *
     * Reads the override pinned at construction time, falling back to the
     * `artisanpack.performance.images.driver` config value so runtime swaps
     * take effect on every call.
     *
     * @since 1.0.0
     *
     * @return string The driver name (`gd` or `imagick`).
     */
    public function driver(): string
    {
        return $this->driverOverride ?? (string) config( 'artisanpack.performance.images.driver', 'gd' );
    }

    /**
     * Reports whether the active driver should route through Imagick.
     *
     * Single source of truth for the GD-vs-Imagick decision used across
     * `ImageService` and `FormatConverter` so unknown drivers can't silently
     * fall through to GD.
     *
     * @since 1.0.0
     *
     * @return bool True when the active driver is Imagick and the extension is loaded.
     */
    public function usesImagick(): bool
    {
        return 'imagick' === $this->driver() && class_exists( Imagick::class );
    }

    /**
     * Encodes the source image with Imagick.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  string  $destination  Absolute path to write the converted file.
     * @param  string  $format  Target format (`webp` or `avif`).
     * @param  int  $quality  Output quality (0-100).
     *
     * @throws RuntimeException When Imagick fails to encode the image.
     */
    protected function convertWithImagick( string $path, string $destination, string $format, int $quality ): void
    {
        $image = null;

        try {
            $image = new Imagick( $path );
            $image->setImageFormat( $format );
            $image->setImageCompressionQuality( $quality );

            if ( $this->hasAlphaChannel( $path ) ) {
                $image->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
            }

            $image->writeImage( $destination );
        } catch ( ImagickException $exception ) {
            throw new RuntimeException(
                "Imagick failed to convert {$path} to {$format}: " . $exception->getMessage(),
                0,
                $exception,
            );
        } finally {
            $image?->clear();
        }
    }

    /**
     * Encodes the source image with GD.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  string  $destination  Absolute path to write the converted file.
     * @param  string  $format  Target format (`webp` or `avif`).
     * @param  int  $quality  Output quality (0-100).
     *
     * @throws RuntimeException When GD cannot read or encode the image.
     */
    protected function convertWithGd( string $path, string $destination, string $format, int $quality ): void
    {
        $image = $this->createGdImage( $path );

        if ( $this->hasAlphaChannel( $path ) ) {
            imagepalettetotruecolor( $image );
            imagealphablending( $image, false );
            imagesavealpha( $image, true );
        }

        $result = 'webp' === $format
            ? imagewebp( $image, $destination, $quality )
            : imageavif( $image, $destination, $quality );

        imagedestroy( $image );

        if ( false === $result ) {
            throw new RuntimeException( "GD failed to encode {$format} for {$path}" );
        }
    }

    /**
     * Creates a GD resource from the source image.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @throws RuntimeException When the mime type cannot be detected or is unsupported.
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

    /**
     * Determines whether the source image carries an alpha channel.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @return bool True when the source mime type supports transparency.
     */
    protected function hasAlphaChannel( string $path ): bool
    {
        $info = getimagesize( $path );

        if ( false === $info ) {
            return false;
        }

        return in_array( $info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true );
    }

    /**
     * Builds the destination path for the converted file.
     *
     * The converted file is stored alongside the original with the new
     * extension swapped in.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  string  $format  Target format (`webp` or `avif`).
     *
     * @return string Absolute path for the converted file.
     */
    protected function targetPath( string $path, string $format): string
    {
        $directory = dirname( $path);
        $basename  = pathinfo( $path, PATHINFO_FILENAME);

        return $directory . DIRECTORY_SEPARATOR . $basename . '.' . $format;
    }
}
