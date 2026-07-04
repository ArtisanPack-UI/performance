<?php

/**
 * ChartAdminApiController — JSON payload for the metrics chart.
 *
 * Mirrors the payload the `MetricsChart` Livewire component builds
 * internally, so external React/Vue chart renderers can hydrate from the
 * same JSON shape.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Controllers\Api\Admin;

use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Monitoring\WebVitals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Chart admin API controller.
 *
 *
 * @since      1.0.0
 */
class ChartAdminApiController extends AdminApiController
{
    /**
     * Supported ranges.
     *
     * @since 1.0.0
     *
     * @var array<string, int>
     */
    public const RANGE_DAYS = [
        '7d'  => 7,
        '30d' => 30,
        '90d' => 90,
    ];

    /**
     * Threshold colors — kept in sync with `MetricsChart::DEFAULT_COLORS`.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const DEFAULT_COLORS = [
        'good'              => '#22c55e',
        'needs_improvement' => '#f59e0b',
        'poor'              => '#ef4444',
    ];

    /**
     * GET /api/performance/admin/chart.
     *
     * @since 1.0.0
     */
    public function show( Request $request ): JsonResponse
    {
        $this->authorizeAdmin();

        $range         = $this->resolveRange( (string) $request->query( 'range', '7d' ) );
        $metrics       = $this->resolveMetrics( $request->query( 'metrics' ) );
        $showThreshold = filter_var(
            $request->query( 'show_threshold', '1' ),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        );
        $showThreshold = null === $showThreshold ? true : $showThreshold;
        $chartType     = $this->resolveChartType( (string) $request->query( 'type', 'line' ) );

        $days   = self::RANGE_DAYS[ $range ];
        $start  = Carbon::today()->subDays( $days - 1 );
        $end    = Carbon::today();
        $labels = $this->dateRangeLabels( $start, $end );

        $rows = PerformanceMetric::query()
            ->whereDate( 'date', '>=', $start->toDateString() )
            ->whereDate( 'date', '<=', $end->toDateString() )
            ->whereIn( 'metric', $metrics )
            ->selectRaw( 'metric, date, SUM(p75 * sample_count) / NULLIF(SUM(sample_count), 0) as p75' )
            ->groupBy( 'metric', 'date' )
            ->orderBy( 'date' )
            ->get();

        $byMetric = [];

        foreach ( $rows as $row ) {
            $metric = (string) $row->metric;
            $date   = Carbon::parse( $row->date )->toDateString();

            $byMetric[ $metric ][ $date ] = null === $row->p75 ? null : (float) $row->p75;
        }

        $datasets = [];

        foreach ( $metrics as $metric ) {
            $values = array_map(
                static fn ( string $label ) => $byMetric[ $metric ][ $label ] ?? null,
                $labels,
            );

            $datasets[] = [
                'metric'    => $metric,
                'values'    => $values,
                'threshold' => $showThreshold ? ( WebVitals::GOOD_THRESHOLDS[ $metric ] ?? null ) : null,
            ];
        }

        return response()->json( [
            'type'     => $chartType,
            'labels'   => $labels,
            'datasets' => $datasets,
            'colors'   => self::DEFAULT_COLORS,
        ] );
    }

    /**
     * Coerce the range key.
     *
     * @since 1.0.0
     */
    protected function resolveRange( string $range ): string
    {
        return isset( self::RANGE_DAYS[ $range ] ) ? $range : '7d';
    }

    /**
     * Coerce the chart type.
     *
     * @since 1.0.0
     */
    protected function resolveChartType( string $type ): string
    {
        $allowed = ['line', 'bar', 'area'];

        return in_array( $type, $allowed, true ) ? $type : 'line';
    }

    /**
     * Normalize the metrics list from either a comma-string or array input.
     *
     * @since 1.0.0
     *
     * @param  mixed  $raw  Raw `metrics` input.
     *
     * @return array<int, string>
     */
    protected function resolveMetrics( mixed $raw ): array
    {
        if ( is_string( $raw ) && '' !== $raw ) {
            $list = array_map( 'trim', explode( ',', $raw ) );
        } elseif ( is_array( $raw ) ) {
            $list = array_map( 'strval', $raw );
        } else {
            $list = [];
        }

        $allowed = array_keys( WebVitals::GOOD_THRESHOLDS );

        $filtered = array_values( array_intersect( array_map( 'strtoupper', $list ), array_map( 'strtoupper', $allowed ) ) );

        return [] === $filtered ? ['LCP'] : $filtered;
    }

    /**
     * Ordered date labels covering the range.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function dateRangeLabels( Carbon $start, Carbon $end ): array
    {
        $labels = [];
        $cursor = $start->copy();

        while ( $cursor->lessThanOrEqualTo( $end ) ) {
            $labels[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $labels;
    }
}
