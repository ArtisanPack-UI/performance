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
     * Boot hook that runs once per model instance to merge in the trait's
     * casts for `optimized_formats`, `optimized_sizes`, and `optimized_at`.
     *
     * Registering the casts here means consuming models don't have to
     * remember to add them by hand — the trait is self-contained and
     * database writes go through the correct JSON/datetime encoding
     * regardless of which model class the row is hydrated into.
     *
     * @since 1.0.0
     */
    public function initializeHasOptimizedMedia(): void
    {
        $this->mergeCasts( [
            'optimized_formats' => 'array',
            'optimized_sizes'   => 'array',
            'optimized_at'      => 'datetime',
        ] );
    }

    /**
     * Returns the URL of the optimized derivative for the given format/size.
     *
     * Lookup precedence:
     *  1. When `$size` is provided, return the exact `optimized_formats[$format][$size]`
     *     entry (or the size-only `optimized_sizes[$size]` entry when `$format` is null).
     *  2. When only `$format` is provided and the entry is a `{width => url}` map, return
     *     the URL for the LARGEST width — the natural "give me the best WebP" default.
     *  3. When only `$format` is provided and the entry is a bare string, return it as-is
     *     (older schema shape).
     *
     * Returns `null` when no matching entry has been recorded — callers should
     * typically fall back to the original media URL in that case.
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
            $entry   = $formats[ $format ] ?? null;

            if ( null === $size ) {
                if ( is_string( $entry ) ) {
                    return $entry;
                }

                return is_array( $entry ) ? $this->largestWidthUrl( $entry ) : null;
            }

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
     * Picks the URL for the largest numeric width in a `{width => url}` map.
     *
     * Non-numeric keys and non-string values are skipped so the srcset
     * contract stays well-formed even when the JSON blob was hand-edited.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $entries  Width-keyed URL map.
     */
    protected function largestWidthUrl( array $entries ): ?string
    {
        $best      = null;
        $bestWidth = -1;

        foreach ( $entries as $width => $url ) {
            if ( ! is_string( $url ) || '' === $url || ! is_numeric( $width ) ) {
                continue;
            }

            $intWidth = (int) $width;

            if ( $intWidth > $bestWidth ) {
                $bestWidth = $intWidth;
                $best      = $url;
            }
        }

        return $best;
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
