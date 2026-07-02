<?php

/**
 * Performance dashboard Livewire component.
 *
 * Top-level surface for the package's admin UI. Composes a date-range
 * picker, a tab strip, and the per-tab summary data sourced from the
 * aggregated `performance_metrics` table and the cache statistics
 * helpers. The component is read-only; mutating cache actions live on
 * the bundled `CacheManager` component the Cache tab mounts.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Livewire;

use ArtisanPackUI\Performance\Cache\CacheStatistics;
use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Monitoring\WebVitals;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Performance dashboard component class.
 *
 *
 * @since      1.0.0
 */
class PerformanceDashboard extends Component
{
    /**
     * Date ranges the picker offers.
     *
     * Expressed as `key => days` so the query side can convert a range
     * into an `>= today - days` clause without re-parsing the labels.
     *
     * @since 1.0.0
     *
     * @var array<string, int>
     */
    public const RANGE_DAYS = [
        '24h' => 1,
        '7d'  => 7,
        '30d' => 30,
        '90d' => 90,
    ];

    /**
     * Tab keys the dashboard exposes.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TABS = [
        'overview',
        'pages',
        'images',
        'cache',
        'queries',
        'recommendations',
    ];

    /**
     * The currently selected date range key.
     *
     * @since 1.0.0
     */
    #[Url( as: 'range', history: true )]
    public string $dateRange = '7d';

    /**
     * The currently active tab key.
     *
     * @since 1.0.0
     */
    #[Url( as: 'tab', history: true )]
    public string $activeTab = 'overview';

    /**
     * Extra CSS classes to append to the outermost dashboard container.
     *
     * @since 1.0.0
     */
    public string $class = '';

    /**
     * Extra CSS classes to append to each card/panel.
     *
     * @since 1.0.0
     */
    public string $cardClasses = '';

    /**
     * Label overrides supplied by the host application.
     *
     * Merged over the default labels resolved at render time so
     * applications can rename any user-facing string without
     * republishing the template.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public array $labels = [];

    /**
     * Mounts the component, applying the default range when none is supplied.
     *
     * Validation is intentionally loose: an unknown range or tab is
     * coerced to the default so deep-linked URLs from older builds never
     * 500. The same coercion happens on every render so an external
     * URL-manipulation bug cannot park the component in a bad state.
     *
     * The `$defaultDateRange` host prop is honored only when the URL
     * did not already carry a `range` query string — otherwise the
     * `#[Url]` restoration would be silently overwritten on every
     * reload, defeating the history binding.
     *
     * @since 1.0.0
     *
     * @param  Request|null  $request  Bound by Livewire to inspect the URL query.
     * @param  string|null  $defaultDateRange  Optional override for the initial range.
     * @param  array<string, string>  $labels  Optional label overrides.
     * @param  string  $class  Extra classes for the outer container.
     * @param  string  $cardClasses  Extra classes for card/panel wrappers.
     */
    public function mount(
        ?Request $request = null,
        ?string $defaultDateRange = null,
        array $labels = [],
        string $class = '',
        string $cardClasses = '',
    ): void {
        $rangeFromUrl = null === $request ? null : $request->query( 'range' );
        $hasUrlRange  = is_string( $rangeFromUrl ) && '' !== $rangeFromUrl;

        if ( ! $hasUrlRange && null !== $defaultDateRange && '' !== $defaultDateRange ) {
            $this->dateRange = $defaultDateRange;
        }

        $this->dateRange   = $this->resolveRange( $this->dateRange );
        $this->activeTab   = $this->resolveTab( $this->activeTab );
        $this->labels      = array_filter( $labels, 'is_string' );
        $this->class       = $class;
        $this->cardClasses = $cardClasses;
    }

    /**
     * Switches the active tab.
     *
     * @since 1.0.0
     *
     * @param  string  $tab  Tab key.
     */
    public function setTab( string $tab ): void
    {
        $this->activeTab = $this->resolveTab( $tab );
    }

    /**
     * Handles `performance:navigate` from child components (e.g. the
     * RecommendationsPanel's "view-query-analyzer" one-click action)
     * by switching the active tab.
     *
     * The event name is package-namespaced so a host application can
     * also listen for it without clashing with unrelated
     * `navigate` events elsewhere in the app.
     *
     * @since 1.0.0
     *
     * @param  string  $tab  Target tab key sent by the dispatching child.
     */
    #[On( 'performance:navigate' )]
    public function navigateToTab( string $tab ): void
    {
        $this->activeTab = $this->resolveTab( $tab );
    }

    /**
     * Switches the active date range.
     *
     * @since 1.0.0
     *
     * @param  string  $range  Range key (`24h`, `7d`, `30d`, `90d`).
     */
    public function setDateRange( string $range ): void
    {
        $this->dateRange = $this->resolveRange( $range );
    }

    /**
     * Forces a re-render so subordinate components reload data.
     *
     * @since 1.0.0
     */
    public function refreshMetrics(): void
    {
        $this->dispatch( 'performance-dashboard:refreshed' );
    }

    /**
     * Renders the dashboard template.
     *
     * Only the active tab's payload is computed — the other tabs
     * resolve to empty defaults until the user switches to them. On
     * a real dataset this is roughly a 5x query reduction per render
     * compared to eager-building every tab. The `overview` payload
     * is always built when the Recommendations tab is active because
     * it derives its list from the overview rollup.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        // Compute visible tabs first so any coercion of the active tab
        // (a hidden tab restored from a URL, say) happens before the
        // per-tab content branches below make their decision.
        $visibleTabs = $this->visibleTabs();

        $overview     = [];
        $pages        = [];
        $cacheSummary = [ 'page' => [ 'entries' => 0 ], 'fragment' => [ 'entries' => 0, 'tags' => 0 ] ];

        // Only the overview/pages/cache tabs are built here — the queries
        // and recommendations tabs delegate entirely to embedded Livewire
        // child components (perf-query-analyzer, perf-recommendations-panel)
        // which own their own data fetching. Duplicating the work here
        // would just double the SQL round-trips per render.
        if ( 'overview' === $this->activeTab ) {
            $overview = $this->buildOverview();
        } elseif ( 'pages' === $this->activeTab ) {
            $pages = $this->buildPagesBreakdown();
        } elseif ( 'cache' === $this->activeTab ) {
            $cacheSummary = $this->buildCacheSummary();
        }

        $data = $this->getViewData( [
            'overview'       => $overview,
            'pages'          => $pages,
            'cacheSummary'   => $cacheSummary,
            'ranges'         => array_keys( self::RANGE_DAYS ),
            'tabs'           => $visibleTabs,
            'resolvedLabels' => $this->resolveLabels(),
        ] );

        return view( 'performance::livewire.performance-dashboard', $data );
    }

    /**
     * Returns the tab keys visible in the current render.
     *
     * Each tab can be toggled via `ui.tabs.<name>` in config. Unknown
     * or falsey entries are treated as hidden; the active tab is
     * coerced back to the first visible tab if the user's selection
     * was hidden after the URL was restored.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function visibleTabs(): array
    {
        $config = (array) config( 'artisanpack.performance.ui.tabs', [] );

        $visible = array_values( array_filter(
            self::TABS,
            static fn ( string $tab ): bool => (bool) ( $config[ $tab ] ?? true ),
        ) );

        // Ensure at least one tab is reachable even when every entry is
        // toggled off — otherwise the render() branches would all miss
        // and the panel body would be empty.
        if ( [] === $visible ) {
            $visible = [ 'overview' ];
        }

        // Coerce the active tab to the first visible one when the URL
        // (or a stale state) restored a hidden tab. Doing this before the
        // early-return above would leave the URL and the rendered content
        // out of sync when every tab is disabled.
        if ( ! in_array( $this->activeTab, $visible, true ) ) {
            $this->activeTab = $visible[0];
        }

        return $visible;
    }

    /**
     * Extension seam for host applications overriding the dashboard.
     *
     * A subclass can override this method to inject additional view
     * variables without duplicating the whole render() pipeline. The
     * default implementation is the identity function.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data  The base view payload.
     *
     * @return array<string, mixed>
     */
    protected function getViewData( array $data ): array
    {
        return $data;
    }

    /**
     * Resolves labels, merging host overrides over defaults.
     *
     * Applications can pass a `labels` prop to override any of these
     * without republishing the dashboard template.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function resolveLabels(): array
    {
        $defaults = [
            'range_label' => (string) __( 'Date range' ),
            'refresh'     => (string) __( 'Refresh' ),
            'core_vitals' => (string) __( 'Core Web Vitals' ),
            'no_metrics'  => (string) __( 'No metrics recorded for this range yet.' ),
        ];

        // Livewire re-hydrates `$labels` from the client payload on every
        // update, so filtering inside mount() alone is not sufficient —
        // a hostile client could send a non-string value in a payload
        // and produce an "Array to string conversion" in Blade. Filter
        // here so every render is safe regardless of hydration path.
        $safe = array_filter( $this->labels, 'is_string' );

        return array_merge( $defaults, $safe );
    }

    /**
     * Builds the Overview tab payload — one row per Core Web Vital.
     *
     * Each metric's headline number is the sample-weighted mean of its
     * bucket p75s (`SUM(p75 * sample_count) / SUM(sample_count)`), not a
     * straight `AVG(p75)`. A straight average over device/connection
     * buckets weights every bucket equally — a 50-sample desktop p75
     * pulls the headline as hard as a 50,000-sample mobile p75 — and
     * the result is a value no actual population experienced. The
     * weighted form is still a mean of percentiles (re-percentiling
     * raw samples would be the only fully-correct rollup), but it at
     * least approximates the population-wide central tendency.
     *
     * @since 1.0.0
     *
     * @return array<int, array{metric: string, p75: float|null, sample_count: int, status: string}>
     */
    protected function buildOverview(): array
    {
        $rows = PerformanceMetric::query()
            ->whereBetween( 'date', [ $this->startDate(), $this->endDate() ] )
            ->selectRaw( 'metric, SUM(p75 * sample_count) as weighted_p75_sum, SUM(sample_count) as sample_count' )
            ->groupBy( 'metric' )
            ->get();

        $out = [];

        foreach ( $rows as $row ) {
            $metric = (string) $row->metric;
            $count  = (int) $row->sample_count;
            $p75    = $count > 0 ? (float) $row->weighted_p75_sum / $count : null;

            $out[] = [
                'metric'       => $metric,
                'p75'          => $p75,
                'sample_count' => $count,
                'status'       => WebVitals::classify( $metric, $p75 ),
            ];
        }

        return $out;
    }

    /**
     * Builds the Pages tab payload — per-route p75 rollup.
     *
     * Mirrors the weighting strategy in `buildOverview()` — each row's
     * p75 is the sample-weighted mean of the per-bucket p75s for that
     * (route, metric) pair, so a route with a tiny mobile/4g cohort
     * doesn't pull a route-level rollup out of alignment with the
     * dominant cohort. Ordering by the weighted mean keeps the
     * "worst pages" list ranked by what the typical user actually
     * sees, not by an unweighted outlier.
     *
     * @since 1.0.0
     *
     * @return array<int, array{route: string|null, metric: string, p75: float, sample_count: int}>
     */
    protected function buildPagesBreakdown(): array
    {
        return PerformanceMetric::query()
            ->whereBetween( 'date', [ $this->startDate(), $this->endDate() ] )
            ->whereNotNull( 'route' )
            ->selectRaw( 'route, metric, SUM(p75 * sample_count) / NULLIF(SUM(sample_count), 0) as p75, SUM(sample_count) as sample_count' )
            ->groupBy( 'route', 'metric' )
            ->orderByDesc( 'p75' )
            ->limit( 25 )
            ->get()
            ->map( static fn ( $row ): array => [
                'route'        => $row->route,
                'metric'       => (string) $row->metric,
                'p75'          => (float) $row->p75,
                'sample_count' => (int) $row->sample_count,
            ] )
            ->all();
    }

    /**
     * Builds the Cache tab summary (counts only — actions live on CacheManager).
     *
     * @since 1.0.0
     *
     * @return array{page: array<string, mixed>, fragment: array<string, mixed>}
     */
    protected function buildCacheSummary(): array
    {
        $statistics = app( CacheStatistics::class );

        return [
            'page'     => $statistics->pageSummary(),
            'fragment' => $statistics->fragmentSummary(),
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
     * Normalizes the supplied range key.
     *
     * @since 1.0.0
     *
     * @param  string  $range  Candidate range key.
     */
    protected function resolveRange( string $range ): string
    {
        return isset( self::RANGE_DAYS[ $range ] ) ? $range : '7d';
    }

    /**
     * Normalizes the supplied tab key.
     *
     * @since 1.0.0
     *
     * @param  string  $tab  Candidate tab key.
     */
    protected function resolveTab( string $tab ): string
    {
        return in_array( $tab, self::TABS, true ) ? $tab : 'overview';
    }
}
