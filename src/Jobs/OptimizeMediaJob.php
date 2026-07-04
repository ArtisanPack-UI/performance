<?php

/**
 * Optimize media queued job.
 *
 * Media-library-aware counterpart to `OptimizeImageJob`: runs the full image
 * optimization pipeline against an uploaded `Media` row and writes the
 * derivative paths, dominant color, and lifecycle status back onto the row
 * so downstream views can consume them through `HasOptimizedMedia`.
 *
 * The job is only useful when the artisanpack-ui/media-library package is
 * installed — dispatch is guarded from the `OptimizeUploadedMedia` listener
 * so the job never lands on the queue in isolation.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Jobs;

use ArtisanPackUI\Performance\Services\ImageService;
use ArtisanPackUI\Performance\Support\MediaOptimizationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Optimize media job class.
 *
 *
 * @since      1.0.0
 */
class OptimizeMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     *
     * @since 1.0.0
     */
    public int $tries;

    /**
     * Seconds between retry attempts.
     *
     * @since 1.0.0
     */
    public int $backoff;

    /**
     * Creates a new job instance.
     *
     * @since 1.0.0
     *
     * @param  Model  $media  The media-library `Media` row to optimize.
     * @param  array<string, mixed>  $options  Optional overrides forwarded to `ImageService::optimize()`:
     *                                          - `sizes` array<int> Widths to generate.
     *                                          - `formats` array<string> Formats to generate.
     *                                          - `quality` int Quality override.
     *                                          - `extract_dominant_color` bool Whether to extract a dominant color hex.
     */
    public function __construct(
        public Model $media,
        public array $options = [],
    ) {
        $this->onQueue( (string) config( 'artisanpack.performance.images.queue', 'default' ) );
        $this->tries   = (int) config( 'artisanpack.performance.images.jobs.tries', 3 );
        $this->backoff = (int) config( 'artisanpack.performance.images.jobs.backoff', 30 );
    }

    /**
     * Executes the job.
     *
     * @since 1.0.0
     *
     * @param  ImageService  $images  Image service resolved from the container.
     */
    public function handle( ImageService $images ): void
    {
        $path = $this->resolveSourcePath();

        if ( null === $path ) {
            $this->writeStatus( MediaOptimizationStatus::FAILED );

            return;
        }

        $this->writeStatus( MediaOptimizationStatus::PROCESSING );

        try {
            $result = $images->optimize( $path, $this->optimizeOptions() );
        } catch ( Throwable $e ) {
            $this->writeStatus( MediaOptimizationStatus::FAILED );

            throw $e;
        }

        $dominant = $this->extractDominantColor( $images, $path );

        $this->applyResults( $result, $dominant );
    }

    /**
     * Marks the job as failed on the media row when Laravel gives up retrying.
     *
     * @since 1.0.0
     */
    public function failed( ?Throwable $exception = null ): void
    {
        $this->writeStatus( MediaOptimizationStatus::FAILED );
    }

    /**
     * Resolves the media row's storage location to an absolute filesystem path.
     *
     * Returns `null` when the file cannot be located — either the media row
     * lacks the columns needed to address the file, or the disk driver
     * doesn't expose a local path (e.g. a remote S3 disk). Remote-disk
     * support would require downloading a temp copy, which is deferred to a
     * future phase.
     *
     * @since 1.0.0
     */
    protected function resolveSourcePath(): ?string
    {
        $filePath = $this->media->getAttribute( 'file_path' );

        if ( ! is_string( $filePath ) || '' === $filePath ) {
            return null;
        }

        $disk = (string) ( $this->media->getAttribute( 'disk' ) ?? 'public' );

        try {
            $storage = Storage::disk( $disk );
        } catch ( Throwable ) {
            return null;
        }

        if ( ! method_exists( $storage, 'path' ) ) {
            return null;
        }

        $absolute = $storage->path( $filePath );

        return is_file( $absolute ) && is_readable( $absolute ) ? $absolute : null;
    }

    /**
     * Builds the option set forwarded to `ImageService::optimize()`.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function optimizeOptions(): array
    {
        $options = $this->options;

        // Trait-only key — never forward it to the ImageService contract.
        unset( $options['extract_dominant_color'] );

        return $options;
    }

    /**
     * Persists the given lifecycle status back onto the media row.
     *
     * `optimized_at` is only touched when the run just completed; a
     * `processing` or `failed` write preserves the previous timestamp so
     * a retry that fails after a successful earlier run doesn't erase
     * the last-known-good freshness signal.
     *
     * Silently no-ops when the target column is not present — applications
     * that pull the Performance package without running the additive media
     * migration should not throw at runtime.
     *
     * @since 1.0.0
     */
    protected function writeStatus( string $status ): void
    {
        $attributes = [ 'optimization_status' => $status ];

        if ( MediaOptimizationStatus::COMPLETED === $status ) {
            $attributes['optimized_at'] = Carbon::now();
        }

        $this->safelyUpdateAttributes( $attributes );
    }

    /**
     * Writes the optimization payload back onto the media row.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  Payload returned by `ImageService::optimize()`.
     * @param  string|null  $dominant  Extracted dominant color (or null when extraction was disabled/failed).
     */
    protected function applyResults( array $result, ?string $dominant ): void
    {
        $formatsMap = $this->buildFormatsMap( $result['variants'] ?? [] );
        $sizesMap   = $this->buildSizesMap( $result['variants'] ?? [] );

        $updates = [
            'optimization_status' => MediaOptimizationStatus::COMPLETED,
            'optimized_at'        => Carbon::now(),
            'optimized_formats'   => $formatsMap,
            'optimized_sizes'     => $sizesMap,
        ];

        if ( null !== $dominant ) {
            $updates['dominant_color'] = $dominant;
        }

        $this->safelyUpdateAttributes( $updates );
    }

    /**
     * Groups the produced variants by format into a `{format => {width => url}}` map.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{path: string, format: string, width: int}>  $variants
     *
     * @return array<string, array<string, string>>
     */
    protected function buildFormatsMap( array $variants ): array
    {
        $map = [];

        foreach ( $variants as $variant ) {
            if ( ! isset( $variant['path'], $variant['format'], $variant['width'] ) ) {
                continue;
            }

            $url = $this->urlForVariant( (string) $variant['path'] );

            if ( null === $url ) {
                continue;
            }

            $map[ (string) $variant['format'] ][ (string) $variant['width'] ] = $url;
        }

        return $map;
    }

    /**
     * Groups the produced variants into a `{width => url}` map keyed by the smallest width.
     *
     * The source-format resize (the first pass in the optimize pipeline) is
     * the natural candidate here — the responsive `srcset` is the same
     * regardless of which modern format the browser picks. We take the
     * first format's map so the shape is deterministic; a future phase may
     * store a dedicated source-format list.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{path: string, format: string, width: int}>  $variants
     *
     * @return array<string, string>
     */
    protected function buildSizesMap( array $variants ): array
    {
        $byFormat = $this->buildFormatsMap( $variants );

        if ( empty( $byFormat ) ) {
            return [];
        }

        return reset( $byFormat );
    }

    /**
     * Converts a filesystem path into a publicly-servable URL when possible.
     *
     * Returns the storage disk URL when the variant lives under the media
     * row's disk root; falls back to a relative public URL when the
     * variant lives under `public_path()`; otherwise returns null.
     *
     * @since 1.0.0
     */
    protected function urlForVariant( string $absolutePath ): ?string
    {
        $disk     = (string) ( $this->media->getAttribute( 'disk' ) ?? 'public' );
        $filePath = (string) $this->media->getAttribute( 'file_path' );

        try {
            $storage = Storage::disk( $disk );
        } catch ( Throwable ) {
            return null;
        }

        if ( method_exists( $storage, 'path' ) && '' !== $filePath ) {
            $sourceAbsolute = $storage->path( $filePath );

            // Trim only the KNOWN trailing suffix — a naive
            // `str_replace( $filePath, '', $sourceAbsolute )` would strip
            // every occurrence of `$filePath` text (breaking on disks whose
            // root contains a repeated segment) and would silently miss
            // Windows-style separators.
            $storageRoot = str_ends_with( $sourceAbsolute, $filePath )
                ? substr( $sourceAbsolute, 0, -strlen( $filePath ) )
                : $sourceAbsolute;
            $storageRoot = rtrim( $storageRoot, DIRECTORY_SEPARATOR );

            if ( '' !== $storageRoot && str_starts_with( $absolutePath, $storageRoot . DIRECTORY_SEPARATOR ) ) {
                $relative = substr( $absolutePath, strlen( $storageRoot ) + 1 );
                $relative = str_replace( DIRECTORY_SEPARATOR, '/', $relative );

                if ( method_exists( $storage, 'url' ) ) {
                    try {
                        return $storage->url( $relative );
                    } catch ( Throwable ) {
                        // Fall through to public-path handling.
                    }
                }
            }
        }

        if ( function_exists( 'public_path' ) ) {
            $publicRoot = rtrim( public_path(), DIRECTORY_SEPARATOR );

            if ( '' !== $publicRoot && str_starts_with( $absolutePath, $publicRoot . DIRECTORY_SEPARATOR ) ) {
                $relative = substr( $absolutePath, strlen( $publicRoot ) );

                return '/' . ltrim( str_replace( DIRECTORY_SEPARATOR, '/', $relative ), '/' );
            }
        }

        return null;
    }

    /**
     * Extracts the dominant color when the option is enabled and encoding permits.
     *
     * Returns `null` on failure so a broken extractor doesn't mark the whole
     * optimization job as failed — the derivatives are still valuable
     * without the LQIP color.
     *
     * @since 1.0.0
     */
    protected function extractDominantColor( ImageService $images, string $path ): ?string
    {
        $shouldExtract = array_key_exists( 'extract_dominant_color', $this->options )
            ? (bool) $this->options['extract_dominant_color']
            : (bool) config( 'artisanpack.performance.images.dominant_color.enabled', true );

        if ( ! $shouldExtract ) {
            return null;
        }

        try {
            return $images->extractDominantColor( $path );
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * Writes attributes back to the media row, ignoring absent columns.
     *
     * The most common cause of a `save()` failure here is the additive
     * `media` migration not having been run — column doesn't exist,
     * SQLSTATE fires, and the job should still report success on its
     * primary work (deriving files). Any failure is logged at warning
     * level so operators aren't blind to migration gaps, connection
     * issues, or unrelated write errors.
     *
     * Also merges casts for the trait-managed columns on the fly so the
     * write works even when the consuming model class doesn't `use
     * HasOptimizedMedia` (e.g., the vanilla vendor `Media` model). Without
     * casts, an array bound to a JSON column relies on undocumented MySQL
     * grammar behavior and fails outright on SQLite/PostgreSQL.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function safelyUpdateAttributes( array $attributes ): void
    {
        if ( ! $this->media->exists ) {
            return;
        }

        $this->media->mergeCasts( [
            'optimized_formats' => 'array',
            'optimized_sizes'   => 'array',
            'optimized_at'      => 'datetime',
        ] );

        foreach ( $attributes as $key => $value ) {
            $this->media->setAttribute( $key, $value );
        }

        try {
            $this->media->save();
        } catch ( Throwable $e ) {
            Log::warning(
                'artisanpack-ui/performance: media optimization write-back failed',
                [
                    'media_id' => $this->media->getKey(),
                    'error'    => $e->getMessage(),
                ],
            );
        }
    }
}
