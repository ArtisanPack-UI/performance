<?php

/**
 * Web Vitals threshold constants.
 *
 * Single source of truth for the "good" / "poor" boundary values used
 * by the dashboard's pass/fail badges, the chart's reference lines,
 * and any future recommendation engine. Aligned with web.dev's Core
 * Web Vitals guidance — each metric has metric-specific good and
 * poor thresholds (the ratios are not uniform, e.g. LCP is 1.6x
 * while CLS is 2.5x and FID is 3x).
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Monitoring;

/**
 * Web Vitals threshold helper.
 *
 *
 * @since      1.0.0
 */
final class WebVitals
{
    /**
     * "Good" thresholds per Core Web Vital.
     *
     * Values from web.dev/vitals (LCP ≤2500ms, INP ≤200ms, CLS ≤0.10,
     * FID ≤100ms, TTFB ≤800ms, FCP ≤1800ms). Any sample at or under
     * the threshold qualifies as "good".
     *
     * @since 1.0.0
     *
     * @var array<string, float>
     */
    public const GOOD_THRESHOLDS = [
        'LCP'  => 2500.0,
        'INP'  => 200.0,
        'CLS'  => 0.10,
        'FID'  => 100.0,
        'TTFB' => 800.0,
        'FCP'  => 1800.0,
    ];

    /**
     * "Poor" thresholds per Core Web Vital.
     *
     * Values from web.dev/vitals (LCP >4000ms, INP >500ms, CLS >0.25,
     * FID >300ms, TTFB >1800ms, FCP >3000ms). Anything strictly above
     * the threshold is "poor"; between the good and poor values is
     * "needs improvement".
     *
     * The ratios are NOT uniform — LCP is 1.6x, FCP is 1.67x, TTFB is
     * 2.25x, CLS is 2.5x, INP is 2.5x, FID is 3x — so a single
     * multiplier (e.g. 2x) misclassifies several metrics near the
     * boundary.
     *
     * @since 1.0.0
     *
     * @var array<string, float>
     */
    public const POOR_THRESHOLDS = [
        'LCP'  => 4000.0,
        'INP'  => 500.0,
        'CLS'  => 0.25,
        'FID'  => 300.0,
        'TTFB' => 1800.0,
        'FCP'  => 3000.0,
    ];

    /**
     * Disallow instantiation; the class is a pure constant container.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }

    /**
     * Classifies a metric value against the good/poor thresholds.
     *
     * Returns "good" when the value is at or below the good threshold,
     * "poor" when strictly above the poor threshold, and
     * "needs-improvement" in between. Returns "unknown" when the
     * value is null or the metric is not in the thresholds table.
     *
     * @since 1.0.0
     *
     * @param  string  $metric  Metric name (e.g. `LCP`).
     * @param  float|null  $value  Metric value (typically a p75 rollup).
     */
    public static function classify( string $metric, ?float $value ): string
    {
        if ( null === $value ) {
            return 'unknown';
        }

        if ( ! isset( self::GOOD_THRESHOLDS[ $metric ], self::POOR_THRESHOLDS[ $metric ] ) ) {
            return 'unknown';
        }

        if ( $value <= self::GOOD_THRESHOLDS[ $metric ] ) {
            return 'good';
        }

        if ( $value > self::POOR_THRESHOLDS[ $metric ] ) {
            return 'poor';
        }

        return 'needs-improvement';
    }
}
