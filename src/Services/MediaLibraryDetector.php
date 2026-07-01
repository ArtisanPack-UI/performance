<?php

/**
 * Media library detector service.
 *
 * Encapsulates the "should we integrate with artisanpack-ui/media-library?"
 * decision so both the service provider and downstream callers (queue
 * job runners, image observers) can consult a single source of truth.
 *
 * The config value at `artisanpack.performance.media_library_integration.enabled`
 * is honored when set to an explicit boolean; when the value is null the
 * detector falls back to a class-existence check on the media-library
 * service provider. That gives operators three states — force on, force
 * off, or "auto" — without needing three separate flags.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Services;

/**
 * Media library detector class.
 *
 *
 * @since      1.0.0
 */
class MediaLibraryDetector
{
    /**
     * Fully-qualified class name of the media library service provider.
     *
     * Kept as a string constant rather than a `::class` reference so
     * we don't force an autoloader miss (and Composer warning) on
     * installations that don't have media-library installed.
     *
     * @since 1.0.0
     */
    public const PROVIDER_CLASS = '\ArtisanPackUI\MediaLibrary\MediaLibraryServiceProvider';

    /**
     * Returns true when the performance package should integrate with media-library.
     *
     * A boolean value at `media_library_integration.enabled` short-
     * circuits the auto-detection so operators can force integration
     * on (for staging where media-library is loaded conditionally) or
     * off (for turnkey deploys that only want the perf helpers).
     *
     * @since 1.0.0
     */
    public function isEnabled(): bool
    {
        $override = config( 'artisanpack.performance.media_library_integration.enabled' );

        if ( is_bool( $override ) ) {
            return $override;
        }

        return $this->isMediaLibraryInstalled();
    }

    /**
     * Returns true when the media-library package's service provider class exists.
     *
     * @since 1.0.0
     */
    public function isMediaLibraryInstalled(): bool
    {
        return class_exists( self::PROVIDER_CLASS );
    }

    /**
     * Returns whether upload optimization should run when the integration is on.
     *
     * When the integration is disabled entirely this returns false
     * regardless of the sub-flag — the sub-flag only matters when the
     * outer switch is on.
     *
     * @since 1.0.0
     */
    public function shouldOptimizeOnUpload(): bool
    {
        if ( ! $this->isEnabled() ) {
            return false;
        }

        return (bool) config( 'artisanpack.performance.media_library_integration.optimize_on_upload', true );
    }

    /**
     * Returns whether modern-format generation should run when the integration is on.
     *
     * @since 1.0.0
     */
    public function shouldGenerateFormatsOnUpload(): bool
    {
        if ( ! $this->isEnabled() ) {
            return false;
        }

        return (bool) config( 'artisanpack.performance.media_library_integration.generate_formats_on_upload', true );
    }

    /**
     * Returns the current detection status as a small array for logging/debug.
     *
     * @since 1.0.0
     *
     * @return array{installed: bool, enabled: bool, source: string}
     */
    public function status(): array
    {
        $override = config( 'artisanpack.performance.media_library_integration.enabled' );

        return [
            'installed' => $this->isMediaLibraryInstalled(),
            'enabled'   => $this->isEnabled(),
            'source'    => is_bool( $override ) ? 'config' : 'auto',
        ];
    }
}
