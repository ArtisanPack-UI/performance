<?php

/**
 * Recommendation engine.
 *
 * Aggregates data from the metrics table, slow-query log, index
 * suggester, and cache statistics into a prioritized list of
 * actionable performance recommendations. Recommendations are
 * expressed as plain arrays (rather than value objects) so the
 * consumer — currently the {@see RecommendationsPanel} Livewire
 * component — can filter, dismiss, or serialize them without an
 * additional adapter layer.
 *
 * The engine deliberately owns no state: it queries fresh on every
 * call and lets the caller (the panel) memoize the response inside
 * a single render. Retention of "which recommendations the user
 * dismissed" also lives in the caller because the desired persistence
 * scope (per-user vs per-session vs. per-installation) is application-
 * specific.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Monitoring;

use ArtisanPackUI\Performance\Cache\CacheStatistics;
use ArtisanPackUI\Performance\Database\IndexSuggester;
use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Support\Carbon;

/**
 * Recommendation engine class.
 *
 *
 * @since      1.0.0
 */
class RecommendationEngine
{
    /**
     * Impact score assigned to each recommendation priority.
     *
     * Used to sort the merged list so the highest-impact
     * recommendations bubble to the top regardless of the order in
     * which the individual builders emitted them.
     *
     * @since 1.0.0
     *
     * @var array<string, int>
     */
    protected const PRIORITY_WEIGHT = [
        'high'   => 3,
        'medium' => 2,
        'low'    => 1,
    ];

    /**
     * Memoized answer to "does the metrics table have any rows?".
     *
     * @since 1.0.0
     */
    protected ?bool $hasMetricsCache = null;

    /**
     * Builds the ranked list of recommendations for the given range.
     *
     * @since 1.0.0
     *
     * @param  string  $dateRange  One of `24h`, `7d`, `30d`, `90d`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build( string $dateRange = '7d' ): array
    {
        $startDate = $this->startDateFor( $dateRange );

        $items = array_merge(
            $this->fromWebVitals( $startDate ),
            $this->fromSlowQueries( $startDate ),
            $this->fromMissingIndexes( $startDate ),
            $this->fromCacheOpportunities(),
        );

        usort( $items, static function ( array $a, array $b ): int {
            return ( self::PRIORITY_WEIGHT[ $b['priority'] ] ?? 0 )
                <=> ( self::PRIORITY_WEIGHT[ $a['priority'] ] ?? 0 );
        } );

        return $items;
    }

    /**
     * Builds recommendations sourced from Core Web Vitals rollups.
     *
     * A metric whose weighted p75 lands in the poor band is emitted as a
     * high-priority item; anything in "needs improvement" is medium.
     * Cohorts below `ui.recommendations.min_samples` are dropped as
     * noise — the threshold is configurable so low-traffic staging
     * environments can lower it to 1 and still get warnings while
     * production sites keep the default anti-noise floor.
     *
     * @since 1.0.0
     *
     * @param  Carbon  $startDate  Inclusive start.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fromWebVitals( Carbon $startDate ): array
    {
        $rows = PerformanceMetric::query()
            ->where( 'date', '>=', $startDate->toDateString() )
            ->selectRaw( 'metric, SUM(p75 * sample_count) as weighted_sum, SUM(sample_count) as sample_count' )
            ->groupBy( 'metric' )
            ->get();

        $minSamples = max( 1, (int) config( 'artisanpack.performance.ui.recommendations.min_samples', 10 ) );

        $items = [];

        foreach ( $rows as $row ) {
            $count = (int) $row->sample_count;

            if ( $count < $minSamples ) {
                continue;
            }

            $metric = (string) $row->metric;
            $p75    = (float) $row->weighted_sum / $count;
            $status = WebVitals::classify( $metric, $p75 );

            if ( 'poor' !== $status && 'needs-improvement' !== $status ) {
                continue;
            }

            $items[] = [
                'id'          => 'metric:' . $metric,
                'type'        => 'web_vital',
                'priority'    => 'poor' === $status ? 'high' : 'medium',
                'impact'      => 'poor' === $status ? 'high' : 'medium',
                'title'       => 'poor' === $status
                    ? (string) __( ':metric is poor', [ 'metric' => $metric ] )
                    : (string) __( ':metric needs improvement', [ 'metric' => $metric ] ),
                'description' => (string) __( 'Weighted p75 of :value across :count samples.', [
                    'value' => number_format( $p75, 2 ),
                    'count' => $count,
                ] ),
                'action'       => null,
                'manual_steps' => [
                    (string) $this->remediationFor( $metric ),
                ],
            ];
        }

        return $items;
    }

    /**
     * Builds recommendations from the slow-query log.
     *
     * A signature is only surfaced when its execution time exceeds
     * `slow_query_logging.threshold_ms` — the same cutoff the logger
     * uses — so the panel never lights up over queries that would
     * never have been captured in the first place.
     *
     * @since 1.0.0
     *
     * @param  Carbon  $startDate  Inclusive start.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fromSlowQueries( Carbon $startDate ): array
    {
        if ( ! class_exists( SlowQuery::class ) ) {
            return [];
        }

        $threshold = (int) config( 'artisanpack.performance.database.slow_query_logging.threshold_ms', 100 );

        $rows = SlowQuery::query()
            ->where( 'created_at', '>=', $startDate )
            ->where( 'time_ms', '>=', $threshold )
            ->selectRaw( 'query_normalized, MAX(time_ms) as peak_time_ms, COUNT(*) as occurrences' )
            ->groupBy( 'query_normalized' )
            ->orderByDesc( 'peak_time_ms' )
            ->limit( 5 )
            ->get();

        $items = [];

        foreach ( $rows as $row ) {
            $items[] = [
                'id'           => 'slow_query:' . md5( (string) $row->query_normalized ),
                'type'         => 'slow_query',
                'priority'     => $row->peak_time_ms >= ( $threshold * 5 ) ? 'high' : 'medium',
                'impact'       => 'medium',
                'title'        => (string) __( 'Slow query repeated :count times', [ 'count' => $row->occurrences ] ),
                'description'  => (string) __( 'Peak execution: :ms ms.', [ 'ms' => number_format( (float) $row->peak_time_ms, 2 ) ] ),
                'action'       => 'view-query-analyzer',
                'manual_steps' => [
                    (string) __( 'Open the Query Analyzer tab to inspect bindings and stack trace.' ),
                    (string) __( 'Consider adding an index or eager-loading the relationship producing the query.' ),
                ],
            ];
        }

        return $items;
    }

    /**
     * Builds recommendations from missing-index suggestions.
     *
     * Runs the {@see IndexSuggester} over the current slow-query
     * corpus and surfaces the top three suggestions with a
     * one-click action that scaffolds a migration via the
     * `perf:suggest-indexes` command.
     *
     * @since 1.0.0
     *
     * @param  Carbon  $startDate  Inclusive start (currently unused; kept for signature symmetry).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fromMissingIndexes( Carbon $startDate ): array
    {
        if ( ! class_exists( SlowQuery::class ) ) {
            return [];
        }

        $suggester = app( IndexSuggester::class );

        $suggestions = $suggester->suggest();

        if ( [] === $suggestions ) {
            return [];
        }

        $items = [];

        foreach ( array_slice( $suggestions, 0, 3 ) as $suggestion ) {
            // IndexSuggester::classifyImpact returns capitalized 'High'/'Medium'/'Low';
            // normalize to lowercase so the priority check and downstream sort
            // (which uses PRIORITY_WEIGHT keyed by lowercase) work consistently.
            $impact = strtolower( (string) ( $suggestion['impact'] ?? 'medium' ) );

            $items[] = [
                'id'          => 'index:' . $suggestion['table'] . ':' . implode( '_', $suggestion['columns'] ),
                'type'        => 'missing_index',
                'priority'    => 'high' === $impact ? 'high' : 'medium',
                'impact'      => $impact,
                'title'       => (string) __( 'Missing index on :table', [ 'table' => $suggestion['table'] ] ),
                'description' => (string) __( 'Add an index covering :columns to speed up matching queries (:count occurrences observed).', [
                    'columns' => implode( ', ', $suggestion['columns'] ),
                    'count'   => (int) ( $suggestion['occurrences'] ?? 0 ),
                ] ),
                'action'         => 'generate-index-migration',
                'action_payload' => [
                    'table'   => $suggestion['table'],
                    'columns' => $suggestion['columns'],
                ],
                'manual_steps' => [
                    (string) __( 'Run `php artisan perf:suggest-indexes --generate` to scaffold the migration.' ),
                    (string) __( 'Review the generated migration and run it via `php artisan migrate`.' ),
                ],
            ];
        }

        return $items;
    }

    /**
     * Builds cache-opportunity recommendations.
     *
     * Both page and fragment caching are opt-in — the recommendation
     * is only useful when the feature is off (the operator has an easy
     * win by flipping the flag) or when the store is empty (the flag is
     * on but nothing has warmed the cache yet).
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fromCacheOpportunities(): array
    {
        $items = [];

        if ( ! (bool) config( 'artisanpack.performance.page_cache.enabled', false ) ) {
            $items[] = [
                'id'           => 'cache:page_disabled',
                'type'         => 'cache_opportunity',
                'priority'     => 'medium',
                'impact'       => 'medium',
                'title'        => (string) __( 'Enable page cache' ),
                'description'  => (string) __( 'Page caching is disabled. Cacheable routes are regenerated on every request.' ),
                'action'       => null,
                'manual_steps' => [
                    (string) __( 'Set `artisanpack.performance.page_cache.enabled` to true.' ),
                    (string) __( 'Add the `perf.page-cache` middleware to a route group.' ),
                ],
            ];
        } elseif ( $this->hasMetrics() && 0 === ( app( CacheStatistics::class )->pageSummary()['entries'] ?? 0 ) ) {
            // Only nudge on an empty page-cache when we have evidence
            // the app is receiving traffic (i.e. there are metrics on
            // the aggregation table). A fresh install has cache-off +
            // no metrics; complaining that "the cache is empty" then
            // is noise, not signal.
            $items[] = [
                'id'           => 'cache:page_empty',
                'type'         => 'cache_opportunity',
                'priority'     => 'low',
                'impact'       => 'low',
                'title'        => (string) __( 'Page cache is empty' ),
                'description'  => (string) __( 'Warm the cache to serve landing pages from storage on the next request.' ),
                'action'       => 'warm-cache',
                'manual_steps' => [
                    (string) __( 'Populate `artisanpack.performance.cache_warming.urls` and run `php artisan perf:warm-cache`.' ),
                ],
            ];
        }

        if ( ! (bool) config( 'artisanpack.performance.fragment_cache.enabled', false ) ) {
            $items[] = [
                'id'           => 'cache:fragment_disabled',
                'type'         => 'cache_opportunity',
                'priority'     => 'low',
                'impact'       => 'low',
                'title'        => (string) __( 'Enable fragment cache' ),
                'description'  => (string) __( 'Fragment caching is disabled, so expensive partials rebuild on every render.' ),
                'action'       => null,
                'manual_steps' => [
                    (string) __( 'Set `artisanpack.performance.fragment_cache.enabled` to true.' ),
                    (string) __( 'Wrap expensive partials with `@cache(key, ttl, tags)`.' ),
                ],
            ];
        }

        return $items;
    }

    /**
     * Returns whether the metrics table has any rows.
     *
     * Cached against `null` so repeat calls within a single build()
     * pass don't re-issue the same COUNT query.
     *
     * @since 1.0.0
     */
    protected function hasMetrics(): bool
    {
        return $this->hasMetricsCache ??= PerformanceMetric::query()->exists();
    }

    /**
     * Returns the inclusive start date for the given range key.
     *
     * @since 1.0.0
     *
     * @param  string  $dateRange  Range key.
     */
    protected function startDateFor( string $dateRange ): Carbon
    {
        $map = [
            '24h' => 1,
            '7d'  => 7,
            '30d' => 30,
            '90d' => 90,
        ];

        $days = $map[ $dateRange ] ?? $map['7d'];

        return Carbon::now()->subDays( $days - 1 )->startOfDay();
    }

    /**
     * Returns a short remediation hint for the given metric.
     *
     * Mirrors the guidance the PerformanceDashboard emits — kept in
     * sync so a metric that appears in both surfaces reads the same
     * across them.
     *
     * @since 1.0.0
     *
     * @param  string  $metric  Metric name.
     */
    protected function remediationFor( string $metric ): string
    {
        return match ( $metric ) {
            'LCP'   => (string) __( 'Optimize the largest element above the fold: preload its image, prioritize its CSS, and serve modern formats.' ),
            'INP'   => (string) __( 'Reduce long tasks on the main thread and split heavy JavaScript into deferred or async chunks.' ),
            'CLS'   => (string) __( 'Reserve space for images, ads, and embeds; avoid inserting content above existing content after load.' ),
            'FID'   => (string) __( 'Defer non-critical JavaScript and split long tasks so the main thread stays responsive to early input.' ),
            'TTFB'  => (string) __( 'Enable page caching, tune database queries, and ensure the origin responds quickly to first byte requests.' ),
            'FCP'   => (string) __( 'Inline critical CSS, preconnect to required origins, and reduce blocking resources in the document head.' ),
            default => (string) __( 'Review the metric definition and audit relevant page resources.' ),
        };
    }
}
