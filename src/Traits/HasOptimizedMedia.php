<?php

/**
 * `HasOptimizedMedia` Eloquent trait.
 *
 * Add-on trait for the artisanpack-ui/media-library `Media` model that exposes
 * the optimization metadata written by the Performance package's upload
 * listener (`OptimizeUploadedMedia`) as convenient accessors: URLs for the
 * generated derivatives, a compiled `srcset` string, the extracted dominant
 * color, and the current lifecycle status.
 *
 * All accessors are read-only and safe to call whether or not optimization
 * has run — every method returns a sensible empty/null result when the
 * underlying columns are unpopulated, so views can render before the queued
 * job completes without null-check gymnastics.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Traits;

use ArtisanPackUI\Performance\Support\MediaOptimizationStatus;

/**
 * `HasOptimizedMedia` Eloquent trait.
 *
 *
 * @since      1.0.0
 */
trait HasOptimizedMedia
{
    /**
     * Returns the URL of the optimized derivative for the given format/size.
     *
     * Looks up the URL in the `optimized_formats` map first (format-specific
     * variants like WebP or AVIF), then falls back to `optimized_sizes` when
     * `$format` is null and only a size was requested. Returns `null` when
     * no matching entry has been recorded — callers should typically fall
     * back to the original media URL in that case.
     *
     * @since 1.0.0
     *
     * @param  string|null  $format  Format key (`webp`, `avif`, …) or null for the size-only map.
     * @param  int|string|null  $size  Size key. Accepts either a symbolic name
     *                                 (e.g. `large`) or a pixel width; matched
     *                                 as a string against the JSON keys.
     */
    public function getOptimizedUrl( ?string $format = null, string|int|null $size = null ): ?string
    {
        if ( null !== $format ) {
            $formats = $this->readOptimizedFormatsMap();

            if ( null === $size ) {
                $entry = $formats[ $format ] ?? null;

                return is_string( $entry ) ? $entry : null;
            }

            $entry = $formats[ $format ] ?? null;

            if ( is_array( $entry ) ) {
                $value = $entry[ (string) $size ] ?? null;

                return is_string( $value ) ? $value : null;
            }

            return null;
        }

        if ( null !== $size ) {
            $sizes = $this->readOptimizedSizesMap();
            $value = $sizes[ (string) $size ] ?? null;

            return is_string( $value ) ? $value : null;
        }

        return null;
    }

    /**
     * Returns a `srcset` attribute value built from the recorded sizes.
     *
     * When `$format` is provided the srcset is built from
     * `optimized_formats[$format]` when that entry is a size-map; otherwise
     * the base `optimized_sizes` map is used. Each entry is emitted as
     * `<url> <width>w`. Returns an empty string when nothing is available so
     * the attribute can be interpolated safely into a template.
     *
     * @since 1.0.0
     *
     * @param  string|null  $format  Optional format key (`webp`, `avif`, …).
     */
    public function getSrcset( ?string $format = null ): string
    {
        $entries = [];

        if ( null !== $format ) {
            $formats = $this->readOptimizedFormatsMap();
            $entry   = $formats[ $format ] ?? null;

            if ( is_array( $entry ) ) {
                $entries = $entry;
            }
        }

        if ( empty( $entries ) ) {
            $entries = $this->readOptimizedSizesMap();
        }

        return $this->buildSrcsetFromMap( $entries );
    }

    /**
     * Returns the extracted dominant color as a hex string.
     *
     * @since 1.0.0
     */
    public function getDominantColor(): ?string
    {
        $value = $this->getAttribute( 'dominant_color' );

        return is_string( $value ) && '' !== $value ? $value : null;
    }

    /**
     * Reports whether the optimization pipeline has completed successfully.
     *
     * @since 1.0.0
     */
    public function isOptimized(): bool
    {
        return MediaOptimizationStatus::COMPLETED === $this->getOptimizationStatus();
    }

    /**
     * Returns the raw optimization lifecycle status.
     *
     * Defaults to `pending` when the column is empty or missing so callers can
     * safely `match` on it without a null branch.
     *
     * @since 1.0.0
     */
    public function getOptimizationStatus(): string
    {
        $value = $this->getAttribute( 'optimization_status' );

        return is_string( $value ) && '' !== $value ? $value : MediaOptimizationStatus::PENDING;
    }

    /**
     * Reads and normalizes the `optimized_formats` JSON column into an array.
     *
     * The column is declared as `json` in the migration; Eloquent decodes it
     * for us when the consuming model casts it as `array`/`json`, but not
     * every application will cast it that way — mirror the tolerance here
     * so the trait works with or without the cast.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function readOptimizedFormatsMap(): array
    {
        return $this->normalizeJsonAttribute( $this->getAttribute( 'optimized_formats' ) );
    }

    /**
     * Reads and normalizes the `optimized_sizes` JSON column into an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function readOptimizedSizesMap(): array
    {
        return $this->normalizeJsonAttribute( $this->getAttribute( 'optimized_sizes' ) );
    }

    /**
     * Normalizes a JSON-or-array attribute value into a keyed array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function normalizeJsonAttribute( mixed $value ): array
    {
        if ( is_array( $value ) ) {
            return $value;
        }

        if ( is_string( $value ) && '' !== $value ) {
            $decoded = json_decode( $value, true );

            return is_array( $decoded ) ? $decoded : [];
        }

        return [];
    }

    /**
     * Builds a `srcset` string from a `{ width => url }` map.
     *
     * Entries with non-numeric keys are skipped — `srcset` only accepts width
     * (`Nw`) or density (`Nx`) descriptors, and the map keys are always
     * widths in the schema we control.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $entries  Size-keyed map from the JSON column.
     */
    protected function buildSrcsetFromMap( array $entries ): string
    {
        $parts = [];

        foreach ( $entries as $width => $url ) {
            if ( ! is_string( $url ) || '' === $url ) {
                continue;
            }

            if ( ! is_numeric( $width ) ) {
                continue;
            }

            $parts[] = $url . ' ' . (int) $width . 'w';
        }

        return implode( ', ', $parts );
    }
}
