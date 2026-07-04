<?php

/**
 * `HasOptimizedImages` Eloquent trait.
 *
 * Adds the model-level image optimization surface that complements the
 * package's image service: callers declare which attributes hold image paths
 * (and how to optimize them) on the model and then read URLs, srcsets, and
 * dominant colors back through a small handful of helpers.
 *
 * Models opt in by `use`-ing the trait and overriding `optimizableImages()`
 * with a map keyed by attribute name. Each entry may carry:
 *  - `sizes`                  array<int> Widths to generate (defaults to package config).
 *  - `formats`                array<string> Format keys (defaults to enabled formats).
 *  - `quality`                int  Override quality for every produced format.
 *  - `extract_dominant_color` bool Whether `getImageDominantColor()` returns a value.
 *  - `auto_optimize`          bool Dispatch `OptimizeImageJob` on save when the attribute changes.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Traits;

use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\Jobs\OptimizeImageJob;
use ArtisanPackUI\Performance\Services\ImageService;
use Throwable;

/**
 * `HasOptimizedImages` Eloquent trait.
 *
 *
 * @since      1.0.0
 */
trait HasOptimizedImages
{
    /**
     * Boots the trait.
     *
     * Wires the `saved` model event to dispatch `OptimizeImageJob` for every
     * `auto_optimize`-enabled attribute that changed since the last save.
     *
     * @since 1.0.0
     */
    public static function bootHasOptimizedImages(): void
    {
        static::saved( function ( $model ): void {
            $model->autoOptimizeChangedImages();
        } );
    }

    /**
     * Returns the optimized variant URL for the given field/format/size.
     *
     * Resolves the attribute value (web path or filesystem path), generates the
     * resized + format-converted variant on demand, and returns the URL the
     * browser can fetch. Returns `null` when the attribute is empty, not a
     * declared field, the file can't be resolved on disk, or the active driver
     * cannot produce the requested format.
     *
     * @since 1.0.0
     *
     * @param  string  $field  Attribute name (must appear in `optimizableImages()`).
     * @param  string  $format  Target format key (`webp`, `avif`, …).
     * @param  int  $size  Target width in pixels.
     */
    public function getOptimizedImageUrl( string $field, string $format, int $size ): ?string
    {
        $config = $this->resolveImageConfig( $field );

        if ( null === $config ) {
            return null;
        }

        $source = $this->resolveSourceFile( $field );

        if ( null === $source ) {
            return null;
        }

        $format    = strtolower( $format );
        $generator = $this->responsiveGenerator();
        $images    = $generator->images();

        if ( ! $images->supportsFormat( $format ) ) {
            return null;
        }

        try {
            $resized   = $images->resize( $source, $size );
            $converted = $images->convertFormat( $resized, $format, $this->qualityFor( $format, $config ) );
        } catch ( Throwable ) {
            return null;
        }

        return $this->mapFilesystemPathToUrl( $field, $converted );
    }

    /**
     * Returns the srcset value for the given field.
     *
     * When `$format` is null the source-format derivatives are used; otherwise
     * each width is converted to the requested format first. Falls back to an
     * empty string when nothing could be produced (unknown field, unresolved
     * source, unsupported format).
     *
     * @since 1.0.0
     *
     * @param  string  $field  Attribute name.
     * @param  string|null  $format  Optional format to convert each variant to.
     */
    public function getImageSrcset( string $field, ?string $format = null ): string
    {
        $config = $this->resolveImageConfig( $field );

        if ( null === $config ) {
            return '';
        }

        $source = $this->resolveSourceFile( $field );

        if ( null === $source ) {
            return '';
        }

        $sizes = $config['sizes'] ?? null;

        try {
            $srcset = $this->responsiveGenerator()->generateSrcset(
                $source,
                is_array( $sizes ) ? array_values( array_map( 'intval', $sizes ) ) : null,
                $format,
            );
        } catch ( Throwable ) {
            return '';
        }

        return $this->rewriteSrcsetToUrls( $field, $srcset );
    }

    /**
     * Returns the dominant color hex for the given field.
     *
     * Returns `null` when the field is unknown, the source can't be resolved,
     * the configuration disables dominant color extraction, or extraction
     * fails. Callers wanting a guaranteed value should fall back themselves
     * (e.g. `?? '#ffffff'`).
     *
     * @since 1.0.0
     *
     * @param  string  $field  Attribute name.
     */
    public function getImageDominantColor( string $field ): ?string
    {
        $config = $this->resolveImageConfig( $field );

        if ( null === $config ) {
            return null;
        }

        // Default to enabled when the per-field flag is absent — the package's
        // global dominant-color toggle in config/performance.php already gates
        // whether extraction is meaningful, so opting in per-field every time
        // would be noisy.
        $extract = array_key_exists( 'extract_dominant_color', $config )
            ? (bool) $config['extract_dominant_color']
            : true;

        if ( ! $extract ) {
            return null;
        }

        $source = $this->resolveSourceFile( $field );

        if ( null === $source ) {
            return null;
        }

        try {
            return $this->imageService()->extractDominantColor( $source );
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * Dispatches `OptimizeImageJob` for every changed `auto_optimize` field.
     *
     * Invoked from the `saved` event registered in `bootHasOptimizedImages()`.
     * Made `public` rather than `protected` so the closure-bound static event
     * listener can call it (closures invoked through Eloquent's event
     * dispatcher run outside the model's protection scope).
     *
     * @since 1.0.0
     */
    public function autoOptimizeChangedImages(): void
    {
        foreach ( $this->optimizableImages() as $field => $config ) {
            if ( empty( $config['auto_optimize'] ) ) {
                continue;
            }

            if ( method_exists( $this, 'wasChanged' ) && ! $this->wasChanged( $field ) ) {
                continue;
            }

            $source = $this->resolveSourceFile( (string) $field );

            if ( null === $source ) {
                continue;
            }

            OptimizeImageJob::dispatch( $source, $this->jobOptionsFor( $config ) );
        }
    }

    /**
     * Returns the optimizable image declaration for this model.
     *
     * Override on the model to enumerate the attributes the trait should treat
     * as image fields and any per-attribute optimization overrides.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    protected function optimizableImages(): array
    {
        return [];
    }

    /**
     * Resolves the per-field config block from `optimizableImages()`.
     *
     * @since 1.0.0
     *
     * @param  string  $field  Attribute name.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveImageConfig( string $field ): ?array
    {
        $images = $this->optimizableImages();

        if ( ! array_key_exists( $field, $images ) || ! is_array( $images[ $field ] ) ) {
            return null;
        }

        return $images[ $field ];
    }

    /**
     * Resolves the attribute value to an absolute filesystem path.
     *
     * Mirrors `ResponsiveImage::resolveSourceFile()` so the trait handles the
     * same mix of inputs callers already pass to the Blade components: bare
     * filesystem paths, web-relative paths under `public_path()`, and remote
     * URLs (which are rejected — there's nothing to optimize on this host).
     *
     * @since 1.0.0
     *
     * @param  string  $field  Attribute name.
     */
    protected function resolveSourceFile( string $field ): ?string
    {
        $value = $this->getAttribute( $field );

        if ( ! is_string( $value ) || '' === $value ) {
            return null;
        }

        if ( str_contains( $value, '://' ) ) {
            return null;
        }

        if ( is_file( $value ) ) {
            return $value;
        }

        if ( ! function_exists( 'public_path' ) ) {
            return null;
        }

        $candidate = public_path( ltrim( $value, '/' ) );

        return is_file( $candidate ) ? $candidate : null;
    }

    /**
     * Maps a generated filesystem path back to a public URL.
     *
     * Uses the directory of the attribute value as the URL prefix when the
     * value is a web-relative path that actually resolves under
     * `public_path()`; otherwise strips `public_path()` from the generated
     * path. Returns `null` when no clean public URL can be derived — the
     * trait must never return a raw filesystem path masquerading as a URL,
     * which would both 404 in the browser and leak the host filesystem
     * layout into the rendered href.
     *
     * The `/`-prefix branch is gated on a real public-path check because
     * raw filesystem paths can also start with `/` (e.g.
     * `/Users/jacob/secret/image.jpg`).
     *
     * @since 1.0.0
     *
     * @param  string  $field  Attribute name.
     * @param  string  $generatedFsPath  Absolute filesystem path of the produced variant.
     */
    protected function mapFilesystemPathToUrl( string $field, string $generatedFsPath ): ?string
    {
        $value = (string) $this->getAttribute( $field );

        if ( '' !== $value && str_starts_with( $value, '/' ) && $this->attributeIsUnderPublicPath( $value ) ) {
            $dir = dirname( $value );

            if ( '/' === $dir ) {
                return '/' . basename( $generatedFsPath );
            }

            return rtrim( $dir, '/' ) . '/' . basename( $generatedFsPath );
        }

        if ( function_exists( 'public_path' ) ) {
            $publicRoot = rtrim( public_path(), DIRECTORY_SEPARATOR );

            if ( str_starts_with( $generatedFsPath, $publicRoot . DIRECTORY_SEPARATOR ) ) {
                $relative = substr( $generatedFsPath, strlen( $publicRoot ) );

                return '/' . ltrim( str_replace( DIRECTORY_SEPARATOR, '/', $relative ), '/' );
            }
        }

        return null;
    }

    /**
     * Reports whether the given web-relative-looking attribute value actually
     * resolves to a real file under `public_path()`.
     *
     * Mirrors the same guard used by `View\Components\ResponsiveImage` so a
     * value like `/Users/jacob/secret/image.jpg` (a raw filesystem path that
     * happens to start with `/`) is not mistaken for a web-relative path
     * served from the application's public root.
     *
     * @since 1.0.0
     *
     * @param  string  $value  Attribute value.
     */
    protected function attributeIsUnderPublicPath( string $value ): bool
    {
        if ( ! function_exists( 'public_path' ) ) {
            return false;
        }

        $candidate = public_path( ltrim( $value, '/' ) );

        return is_file( $candidate );
    }

    /**
     * Rewrites every entry in a srcset string so each path becomes a public URL.
     *
     * @since 1.0.0
     *
     * @param  string  $field  Attribute name (used for URL prefix derivation).
     * @param  string  $srcset  Raw srcset string from the responsive generator.
     */
    protected function rewriteSrcsetToUrls( string $field, string $srcset ): string
    {
        if ( '' === $srcset ) {
            return '';
        }

        $entries = array_map( 'trim', explode( ',', $srcset ) );
        $mapped  = [];

        foreach ( $entries as $entry ) {
            if ( '' === $entry ) {
                continue;
            }

            // Split on the LAST space — descriptors (`<n>w`/`<n>x`) are the
            // trailing token, and filesystem paths can legitimately contain
            // spaces (the project's own packages directory has them).
            $lastSpace = strrpos( $entry, ' ' );

            if ( false === $lastSpace ) {
                $url = $this->mapFilesystemPathToUrl( $field, $entry );

                if ( null !== $url ) {
                    $mapped[] = $url;
                }

                continue;
            }

            $path       = substr( $entry, 0, $lastSpace );
            $descriptor = substr( $entry, $lastSpace + 1 );
            $url        = $this->mapFilesystemPathToUrl( $field, $path );

            if ( null !== $url ) {
                $mapped[] = $url . ' ' . $descriptor;
            }
        }

        return implode( ', ', $mapped );
    }

    /**
     * Resolves the quality value for the given format.
     *
     * Per-field config wins; otherwise the package's configured format quality
     * applies; otherwise reasonable encoder defaults.
     *
     * @since 1.0.0
     *
     * @param  string  $format  Format key.
     * @param  array<string, mixed>  $config  Per-field config block.
     */
    protected function qualityFor( string $format, array $config ): int
    {
        if ( isset( $config['quality'] ) ) {
            return max( 0, min( 100, (int) $config['quality'] ) );
        }

        $default = 'webp' === $format ? 80 : 70;
        $value   = (int) config( "artisanpack.performance.images.formats.{$format}.quality", $default );

        return max( 0, min( 100, $value ) );
    }

    /**
     * Builds the options array forwarded to `OptimizeImageJob`.
     *
     * Only forwards keys the underlying `ImageService::optimize()` understands
     * so unrelated trait-specific keys (`auto_optimize`,
     * `extract_dominant_color`) don't leak into the service contract.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $config  Per-field config block.
     *
     * @return array<string, mixed>
     */
    protected function jobOptionsFor( array $config ): array
    {
        $options = [];

        if ( isset( $config['sizes'] ) ) {
            $options['sizes'] = array_values( array_map( 'intval', (array) $config['sizes'] ) );
        }

        if ( isset( $config['formats'] ) ) {
            $options['formats'] = array_values( array_map( 'strtolower', (array) $config['formats'] ) );
        }

        if ( isset( $config['quality'] ) ) {
            $options['quality'] = max( 0, min( 100, (int) $config['quality'] ) );
        }

        return $options;
    }

    /**
     * Resolves the `ResponsiveImageGenerator` from the container with a fallback.
     *
     * @since 1.0.0
     */
    protected function responsiveGenerator(): ResponsiveImageGenerator
    {
        if ( function_exists( 'app' ) ) {
            try {
                return app( ResponsiveImageGenerator::class );
            } catch ( Throwable ) {
                // Container missing the binding — fall through.
            }
        }

        return new ResponsiveImageGenerator;
    }

    /**
     * Resolves the `ImageService` from the container with a fallback.
     *
     * @since 1.0.0
     */
    protected function imageService(): ImageService
    {
        if ( function_exists( 'app' ) ) {
            try {
                return app( ImageService::class );
            } catch ( Throwable ) {
                // Container missing the binding — fall through.
            }
        }

        return new ImageService;
    }
}
