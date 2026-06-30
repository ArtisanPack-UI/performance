<?php

/**
 * Metrics chart directive rendering helpers.
 *
 * Backs the `@perfMetricsChartAssets` Blade directive that emits the
 * Chart.js loader plus the package's small bootstrap module which
 * binds Chart.js to the `[data-metrics-chart]` containers rendered
 * by the `MetricsChart` Livewire component.
 *
 * Chart.js itself is loaded from a CDN by default so applications
 * that don't bundle it locally still get working charts; the URL is
 * overridable through `monitoring.chart_library_url` for sites that
 * self-host the library (CSP, air-gapped builds, etc.) or set it to
 * an empty string to skip the loader entirely when Chart.js is
 * already on the page.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Support;

/**
 * Metrics chart directive helper.
 *
 *
 * @since      1.0.0
 */
final class MetricsChartDirectives
{
    /**
     * Default published asset path for the chart bootstrap module.
     *
     * Mirrors the `resources/js` publish target in
     * `PerformanceServiceProvider::publishConfiguration()` so the
     * directive's default script `src` resolves out of the box
     * for any app that ran the `artisanpack-performance-js` publish.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_SCRIPT_PATH = '/vendor/artisanpack-performance/metrics-chart.js';

    /**
     * Default CDN URL for the Chart.js library.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_CHART_LIBRARY_URL = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';

    /**
     * Disallow instantiation; the class is a pure static helper.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }

    /**
     * Renders the `@perfMetricsChartAssets` directive output.
     *
     * Emits the Chart.js loader tag followed by the package's
     * bootstrap module. Callers can suppress either piece via
     * overrides — pass `libraryUrl: ''` when the host page already
     * loads Chart.js, or `src: ''` to skip the bootstrap when
     * embedding it through Vite/webpack instead.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $overrides  Per-call configuration overrides.
     */
    public static function perfMetricsChartAssets( array $overrides = [] ): string
    {
        $output = '';

        $libraryUrl = $overrides['libraryUrl']
            ?? config( 'artisanpack.performance.monitoring.chart_library_url', self::DEFAULT_CHART_LIBRARY_URL );

        if ( is_string( $libraryUrl ) && '' !== $libraryUrl ) {
            $output .= sprintf(
                '<script src="%s"></script>',
                htmlspecialchars( $libraryUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
            );
        }

        $src = $overrides['src'] ?? self::DEFAULT_SCRIPT_PATH;

        if ( is_string( $src ) && '' !== $src ) {
            $output .= sprintf(
                '<script src="%s"></script>',
                htmlspecialchars( $src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
            );
        }

        return $output;
    }
}
