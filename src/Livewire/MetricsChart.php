<?php

/**
 * Metrics chart Livewire component.
 *
 * Renders a daily time series of one or more Web Vitals as a line chart.
 * The component reads from the aggregated `performance_metrics` table
 * (p75 column) and emits a small `<canvas>` plus a data island the
 * client-side Chart.js bootstrap consumes. The component itself owns
 * the data shaping; the JavaScript layer is a thin renderer.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Livewire;

use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Monitoring\WebVitals;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Metrics chart component class.
 *
 *
 * @since      1.0.0
 */
class MetricsChart extends Component
{
    /**
     * Date range options in days.
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
     * Default per-metric threshold colors keyed by status.
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
     * The metrics to include in the chart.
     *
     * Accepts either a single metric (mounted via the `metric` prop) or
     * a list (mounted via the `metrics` prop). Internally the property
     * is always normalized to a list so the renderer doesn't branch on
     * the shape.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public array $metrics = [ 'LCP' ];

    /**
     * Selected date range key.
     *
     * Bound to the URL so a user-selected range survives a reload and
     * can be bookmarked. Different `as:` than the dashboard so the two
     * components can coexist on a page without their range pickers
     * sharing one query parameter.
     *
     * @since 1.0.0
     */
    #[Url( as: 'chart_range', history: true )]
    public string $dateRange = '7d';

    /**
     * Whether to render the "good" threshold reference line.
     *
     * @since 1.0.0
     */
    public bool $showThreshold = true;

    /**
     * Chart type — `line`, `bar`, or `area`.
     *
     * @since 1.0.0
     */
    public string $chartType = 'line';

    /**
     * Mounts the component, normalizing the metric props.
     *
     * Accepts both `metric="LCP"` and `metrics={['LCP','FID']}` because
     * the issue's example markup shows both forms. Either is coerced
     * to the `$metrics` list.
     *
     * @since 1.0.0
     *
     * @param  string|null  $metric  Single metric name.
     * @param  array<int, string>|null  $metrics  List of metrics.
     * @param  string|null  $dateRange  Range key.
     * @param  bool|null  $showThreshold  Whether to draw threshold reference lines.
     * @param  string|null  $chartType  Chart type override.
     */
    public function mount(
        ?string $metric = null,
        ?array $metrics = null,
        ?string $dateRange = null,
        ?bool $showThreshold = null,
        ?string $chartType = null,
    ): void {
        if ( is_array( $metrics ) ) {
            $this->metrics = array_values( array_filter( $metrics, static fn ( $value ): bool => is_string( $value ) && '' !== $value ) );
        } elseif ( null !== $metric && '' !== $metric ) {
            $this->metrics = [ $metric ];
        } else {
            // Re-filter the property too, since Livewire syncs raw
            // mount-args onto the public property BEFORE mount() runs
            // — a host passing `:metrics="[null, '']"` arrives with
            // junk in `$this->metrics` even when `$metrics` itself is
            // never re-touched here.
            $this->metrics = array_values( array_filter( $this->metrics, static fn ( $value ): bool => is_string( $value ) && '' !== $value ) );
        }

        // An empty or filter-stripped metrics list would later produce
        // `WHERE metric IN ()` — a SQL syntax error on MySQL — so the
        // class-level default (`['LCP']`) is reasserted whenever the
        // resolved list is empty. The host gets a sensible fallback
        // instead of a 500 page.
        if ( empty( $this->metrics ) ) {
            $this->metrics = [ 'LCP' ];
        }

        if ( null !== $dateRange && '' !== $dateRange ) {
            $this->dateRange = $dateRange;
        }

        if ( null !== $showThreshold ) {
            $this->showThreshold = $showThreshold;
        }

        if ( null !== $chartType && '' !== $chartType ) {
            $this->chartType = $chartType;
        }

        $this->dateRange = $this->resolveRange( $this->dateRange );
        $this->chartType = $this->resolveChartType( $this->chartType );
    }

    /**
     * Switches the active date range.
     *
     * Exposed as a Livewire action so the bundled view can render a
     * range-picker (`<button wire:click="setDateRange('30d')">`)
     * without the host having to wire one up. Coerces unknown keys
     * to the default rather than throwing so a stale URL never
     * crashes the page.
     *
     * @since 1.0.0
     *
     * @param  string  $range  Range key.
     */
    public function setDateRange( string $range ): void
    {
        $this->dateRange = $this->resolveRange( $range );
    }

    /**
     * Renders the chart container and data payload.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view( 'performance::livewire.metrics-chart', [
            'chartPayload' => $this->buildChartPayload(),
        ] );
    }

    /**
     * Builds the data payload the client-side renderer consumes.
     *
     * The payload is a single JSON-encodable structure containing the
     * chart type, the labels (daily date strings), every metric's
     * dataset (date → p75), the configured colors, and the threshold
     * lines so the client can render a complete chart without further
     * AJAX round-trips.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function buildChartPayload(): array
    {
        $startDate = $this->startDate();
        $endDate   = $this->endDate();
        $labels    = $this->dateRangeLabels( $startDate, $endDate );

        // The metrics list is guaranteed non-empty by mount() — the
        // class-level default is reasserted whenever the resolved list
        // becomes empty so this query never produces `WHERE metric IN ()`.
        $rows = PerformanceMetric::query()
            ->whereBetween( 'date', [ $startDate, $endDate ] )
            ->whereIn( 'metric', $this->metrics )
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

        foreach ( $this->metrics as $metric ) {
            $values = array_map( static fn ( string $label ) => $byMetric[ $metric ][ $label ] ?? null, $labels );

            $datasets[] = [
                'metric'    => $metric,
                'values'    => $values,
                'threshold' => $this->showThreshold ? ( WebVitals::GOOD_THRESHOLDS[ $metric ] ?? null ) : null,
            ];
        }

        return [
            'type'     => $this->chartType,
            'labels'   => $labels,
            'datasets' => $datasets,
            'colors'   => self::DEFAULT_COLORS,
        ];
    }

    /**
     * Returns the inclusive start date for the current range.
     *
     * @since 1.0.0
     */
    protected function startDate(): Carbon
    {
        $days = self::RANGE_DAYS[ $this->dateRange ] ?? self::RANGE_DAYS['7d'];

        return Carbon::today()->subDays( $days - 1 );
    }

    /**
     * Returns the inclusive end date for the current range.
     *
     * @since 1.0.0
     */
    protected function endDate(): Carbon
    {
        return Carbon::today();
    }

    /**
     * Returns the ordered list of date labels covering the range.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function dateRangeLabels( Carbon $startDate, Carbon $endDate ): array
    {
        $labels = [];
        $cursor = $startDate->copy();

        while ( $cursor->lessThanOrEqualTo( $endDate ) ) {
            $labels[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $labels;
    }

    /**
     * Normalizes the supplied range key.
     *
     * @since 1.0.0
     *
     * @param  string  $range  Range key.
     */
    protected function resolveRange( string $range ): string
    {
        return isset( self::RANGE_DAYS[ $range ] ) ? $range : '7d';
    }

    /**
     * Normalizes the supplied chart type.
     *
     * @since 1.0.0
     *
     * @param  string  $type  Chart type.
     */
    protected function resolveChartType( string $type ): string
    {
        $allowed = [ 'line', 'bar', 'area' ];

        return in_array( $type, $allowed, true ) ? $type : 'line';
    }
}
