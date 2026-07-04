<?php

/**
 * Dominant color extractor.
 *
 * Samples a source image and returns its dominant color as a 7-character hex
 * string suitable for use as an LQIP background. Two algorithms are supported:
 *  - `average`: downsamples the image to a single pixel and averages RGB.
 *    Fast and good enough for blurred placeholders.
 *  - `quantize`: bins pixels into a coarse RGB lattice and returns the
 *    centroid of the most populated bin. Slower but produces a color that
 *    matches the visually dominant region.
 *
 * Transparent pixels are skipped so colors derived from PNG/GIF/WebP sources
 * reflect the visible content rather than the transparent fill.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Images;

use GdImage;
use Illuminate\Contracts\Cache\Repository;
use Imagick;
use ImagickException;
use ImagickPixel;
use RuntimeException;
use Throwable;

/**
 * Dominant color extractor class.
 *
 *
 * @since      1.0.0
 */
class DominantColorExtractor
{
    /**
     * Supported algorithm keys.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const ALGORITHMS = ['average', 'quantize'];

    /**
     * Default algorithm when neither the caller nor config selects one.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_ALGORITHM = 'average';

    /**
     * Alpha threshold above which a GD pixel is considered transparent.
     *
     * GD alpha values run from 0 (opaque) to 127 (fully transparent). The
     * midpoint conservatively treats nearly-transparent pixels as background
     * fill so the extracted color reflects visible content.
     *
     * @since 1.0.0
     *
     * @var int
     */
    protected const GD_ALPHA_TRANSPARENT_THRESHOLD = 64;

    /**
     * Number of bins per RGB axis used by the quantize algorithm.
     *
     * 8 bins → 8^3 = 512 buckets, fine enough to surface meaningful colors
     * while keeping the bin map small.
     *
     * @since 1.0.0
     *
     * @var int
     */
    protected const QUANTIZE_BINS_PER_AXIS = 8;

    /**
     * Maximum width/height the quantize sampler downscales to.
     *
     * Sampling at full resolution is wasteful — a 100x100 thumbnail captures
     * the dominant region without the per-pixel iteration cost.
     *
     * @since 1.0.0
     *
     * @var int
     */
    protected const QUANTIZE_SAMPLE_SIZE = 100;

    /**
     * Optional cache override pinned at construction time.
     *
     * Null means "read from config on every call" which is the production path.
     * Tests can pin a specific cache store (e.g. an array store) without
     * touching global config.
     *
     * @since 1.0.0
     */
    protected ?Repository $cache;

    /**
     * Optional algorithm override pinned at construction time.
     *
     * Null means "read from config on every call". Tests and callers that want
     * to force an algorithm without flipping config use this constructor seam.
     *
     * @since 1.0.0
     */
    protected ?string $algorithmOverride;

    /**
     * Creates a new extractor.
     *
     * @since 1.0.0
     *
     * @param  string|null  $algorithm  Optional algorithm override (`average` or `quantize`).
     * @param  Repository|null  $cache  Optional cache repository override.
     */
    public function __construct( ?string $algorithm = null, ?Repository $cache = null )
    {
        $this->algorithmOverride = $algorithm;
        $this->cache             = $cache;
    }

    /**
     * Extracts the dominant color from the given image.
     *
     * Caches the result keyed by file path, modification time, and algorithm
     * so repeated calls do not re-decode the image. Pass `$useCache = false`
     * to bypass the cache (useful in tests).
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  string|null  $algorithm  Optional algorithm override for this call.
     * @param  bool  $useCache  Whether to read from / write to the cache.
     *
     * @throws RuntimeException When the source image is unreadable or the algorithm is unknown.
     *
     * @return string Hex color string in the form `#rrggbb`.
     */
    public function extract( string $path, ?string $algorithm = null, bool $useCache = true ): string
    {
        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            throw new RuntimeException( "Source image is not readable: {$path}" );
        }

        $algorithm = $this->resolveAlgorithm( $algorithm );

        if ( ! in_array( $algorithm, self::ALGORITHMS, true ) ) {
            throw new RuntimeException( "Unknown dominant color algorithm: {$algorithm}" );
        }

        if ( ! $useCache ) {
            return $this->compute( $path, $algorithm );
        }

        $cache = $this->cacheStore();

        if ( null === $cache ) {
            return $this->compute( $path, $algorithm );
        }

        $key = $this->cacheKey( $path, $algorithm );
        $ttl = (int) config( 'artisanpack.performance.images.dominant_color.cache_ttl', 0 );

        if ( $ttl > 0 ) {
            return (string) $cache->remember( $key, $ttl, fn (): string => $this->compute( $path, $algorithm ) );
        }

        return (string) $cache->rememberForever( $key, fn (): string => $this->compute( $path, $algorithm ) );
    }

    /**
     * Returns the configured/overridden algorithm name.
     *
     * @since 1.0.0
     */
    public function algorithm(): string
    {
        return $this->resolveAlgorithm( null );
    }

    /**
     * Computes the dominant color without touching the cache.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  string  $algorithm  Resolved algorithm name.
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function compute( string $path, string $algorithm ): string
    {
        return match ( $algorithm ) {
            'quantize' => $this->quantize( $path ),
            default    => $this->average( $path ),
        };
    }

    /**
     * Resolves the algorithm precedence: per-call → override → config → default.
     *
     * @since 1.0.0
     *
     * @param  string|null  $perCall  The per-call algorithm override.
     *
     * @return string Normalized algorithm name.
     */
    protected function resolveAlgorithm( ?string $perCall ): string
    {
        $algorithm = $perCall
            ?? $this->algorithmOverride
            ?? config( 'artisanpack.performance.images.dominant_color.algorithm', self::DEFAULT_ALGORITHM );

        return strtolower( (string) $algorithm );
    }

    /**
     * Returns the cache repository the extractor should use.
     *
     * Resolves the constructor override first, then falls back to the store
     * named by `artisanpack.performance.images.dominant_color.cache_store`.
     * Returns null when caching cannot be wired up so callers fall back to a
     * direct compute.
     *
     * @since 1.0.0
     */
    protected function cacheStore(): ?Repository
    {
        if ( null !== $this->cache ) {
            return $this->cache;
        }

        if ( ! function_exists( 'cache' ) ) {
            return null;
        }

        $store = config( 'artisanpack.performance.images.dominant_color.cache_store' );

        try {
            return cache()->store( $store );
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * Builds the cache key for the given source/algorithm pair.
     *
     * Includes the file modification time so the cache invalidates when the
     * source is replaced without changing its path.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     * @param  string  $algorithm  Algorithm name.
     *
     * @return string Namespaced cache key.
     */
    protected function cacheKey( string $path, string $algorithm ): string
    {
        $mtime = @filemtime( $path );

        return sprintf(
            'performance:dominant_color:%s:%s:%d',
            $algorithm,
            md5( $path ),
            false === $mtime ? 0 : $mtime,
        );
    }

    /**
     * Computes the average-color algorithm.
     *
     * Uses Imagick when the driver supports it; otherwise falls back to GD.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function average( string $path ): string
    {
        if ( $this->usesImagick() ) {
            return $this->averageWithImagick( $path );
        }

        return $this->averageWithGd( $path );
    }

    /**
     * Computes the quantize algorithm.
     *
     * Buckets sampled pixels into an RGB lattice and returns the centroid of
     * the most populated bucket as a hex string.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function quantize( string $path ): string
    {
        if ( $this->usesImagick() ) {
            return $this->quantizeWithImagick( $path );
        }

        return $this->quantizeWithGd( $path );
    }

    /**
     * Averages the source image using Imagick.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @throws RuntimeException When Imagick fails to read the image.
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function averageWithImagick( string $path ): string
    {
        $image = null;

        try {
            $image = new Imagick( $path );

            if ( $image->getImageAlphaChannel() ) {
                $image->setImageBackgroundColor( new ImagickPixel( 'white' ) );
                $image = $image->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
            }

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

        return $this->toHex( (int) $pixel['r'], (int) $pixel['g'], (int) $pixel['b'] );
    }

    /**
     * Averages the source image using GD.
     *
     * Walks a small grid of samples and averages opaque pixels only so the
     * computed color reflects visible content.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function averageWithGd( string $path ): string
    {
        $source = $this->createGdImage( $path );
        $width  = imagesx( $source );
        $height = imagesy( $source );

        $totalR  = 0;
        $totalG  = 0;
        $totalB  = 0;
        $samples = 0;

        $stepX = max( 1, (int) floor( $width / self::QUANTIZE_SAMPLE_SIZE ) );
        $stepY = max( 1, (int) floor( $height / self::QUANTIZE_SAMPLE_SIZE ) );

        for ( $y = 0; $y < $height; $y += $stepY ) {
            for ( $x = 0; $x < $width; $x += $stepX ) {
                $rgba = imagecolorat( $source, $x, $y );

                if ( ( ( $rgba >> 24 ) & 0x7F ) >= self::GD_ALPHA_TRANSPARENT_THRESHOLD ) {
                    continue;
                }

                $totalR += ( $rgba >> 16 ) & 0xFF;
                $totalG += ( $rgba >> 8 ) & 0xFF;
                $totalB += $rgba & 0xFF;
                $samples++;
            }
        }

        imagedestroy( $source );

        if ( 0 === $samples ) {
            return '#ffffff';
        }

        return $this->toHex(
            (int) round( $totalR / $samples ),
            (int) round( $totalG / $samples ),
            (int) round( $totalB / $samples ),
        );
    }

    /**
     * Computes the quantize algorithm using Imagick.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @throws RuntimeException When Imagick fails to read the image.
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function quantizeWithImagick( string $path ): string
    {
        $image = null;

        try {
            $image = new Imagick( $path );

            if ( $image->getImageAlphaChannel() ) {
                $image->setImageBackgroundColor( new ImagickPixel( 'white' ) );
                $image = $image->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
            }

            $image->resizeImage( self::QUANTIZE_SAMPLE_SIZE, self::QUANTIZE_SAMPLE_SIZE, Imagick::FILTER_LANCZOS, 1 );

            $width  = $image->getImageWidth();
            $height = $image->getImageHeight();
            $bins   = [];

            for ( $y = 0; $y < $height; $y++ ) {
                for ( $x = 0; $x < $width; $x++ ) {
                    $color = $image->getImagePixelColor( $x, $y )->getColor();
                    $this->binPixel( $bins, (int) $color['r'], (int) $color['g'], (int) $color['b'] );
                }
            }
        } catch ( ImagickException $exception ) {
            throw new RuntimeException(
                "Imagick failed to sample {$path}: " . $exception->getMessage(),
                0,
                $exception,
            );
        } finally {
            $image?->clear();
        }

        return $this->dominantBinHex( $bins );
    }

    /**
     * Computes the quantize algorithm using GD.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function quantizeWithGd( string $path ): string
    {
        $source = $this->createGdImage( $path );
        $width  = imagesx( $source );
        $height = imagesy( $source );

        $stepX = max( 1, (int) floor( $width / self::QUANTIZE_SAMPLE_SIZE ) );
        $stepY = max( 1, (int) floor( $height / self::QUANTIZE_SAMPLE_SIZE ) );

        $bins = [];

        for ( $y = 0; $y < $height; $y += $stepY ) {
            for ( $x = 0; $x < $width; $x += $stepX ) {
                $rgba = imagecolorat( $source, $x, $y );

                if ( ( ( $rgba >> 24 ) & 0x7F ) >= self::GD_ALPHA_TRANSPARENT_THRESHOLD ) {
                    continue;
                }

                $this->binPixel(
                    $bins,
                    ( $rgba >> 16 ) & 0xFF,
                    ( $rgba >> 8 ) & 0xFF,
                    $rgba & 0xFF,
                );
            }
        }

        imagedestroy( $source );

        return $this->dominantBinHex( $bins );
    }

    /**
     * Accumulates a pixel into the quantize bin map.
     *
     * @since 1.0.0
     *
     * @param  array<string, array{count: int, r: int, g: int, b: int}>  $bins  Reference to the bin accumulator.
     * @param  int  $r  Red channel (0-255).
     * @param  int  $g  Green channel (0-255).
     * @param  int  $b  Blue channel (0-255).
     */
    protected function binPixel( array &$bins, int $r, int $g, int $b ): void
    {
        $binSize = (int) ceil( 256 / self::QUANTIZE_BINS_PER_AXIS );
        $rBin    = (int) floor( $r / $binSize );
        $gBin    = (int) floor( $g / $binSize );
        $bBin    = (int) floor( $b / $binSize );
        $key     = "{$rBin}-{$gBin}-{$bBin}";

        if ( ! isset( $bins[ $key ] ) ) {
            $bins[ $key ] = ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
        }

        $bins[ $key ]['count']++;
        $bins[ $key ]['r'] += $r;
        $bins[ $key ]['g'] += $g;
        $bins[ $key ]['b'] += $b;
    }

    /**
     * Returns the centroid of the most populated quantize bin as a hex string.
     *
     * Returns white when no opaque pixels were sampled (e.g. fully transparent
     * source) so callers always get a sensible placeholder.
     *
     * @since 1.0.0
     *
     * @param  array<string, array{count: int, r: int, g: int, b: int}>  $bins  Accumulated bins.
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function dominantBinHex( array $bins ): string
    {
        if ( empty( $bins ) ) {
            return '#ffffff';
        }

        $winner = null;

        foreach ( $bins as $bin ) {
            if ( null === $winner || $bin['count'] > $winner['count'] ) {
                $winner = $bin;
            }
        }

        $count = $winner['count'];

        return $this->toHex(
            (int) round( $winner['r'] / $count ),
            (int) round( $winner['g'] / $count ),
            (int) round( $winner['b'] / $count ),
        );
    }

    /**
     * Reports whether the configured driver should use Imagick.
     *
     * @since 1.0.0
     */
    protected function usesImagick(): bool
    {
        $driver = (string) config( 'artisanpack.performance.images.driver', 'gd' );

        return 'imagick' === $driver && class_exists( Imagick::class );
    }

    /**
     * Formats an RGB triple as a 7-character lowercase hex string.
     *
     * @since 1.0.0
     *
     * @param  int  $r  Red channel (0-255).
     * @param  int  $g  Green channel (0-255).
     * @param  int  $b  Blue channel (0-255).
     *
     * @return string Hex color string `#rrggbb`.
     */
    protected function toHex( int $r, int $g, int $b ): string
    {
        return sprintf(
            '#%02x%02x%02x',
            max( 0, min( 255, $r ) ),
            max( 0, min( 255, $g ) ),
            max( 0, min( 255, $b ) ),
        );
    }

    /**
     * Creates a GD resource from the source image.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the source image.
     *
     * @throws RuntimeException When the mime type cannot be decoded by GD.
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
            throw new RuntimeException( "Unsupported image type for GD: {$path}");
        }

        return $image;
    }
}
