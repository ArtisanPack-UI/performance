<?php

/**
 * DashboardAdminApiController — JSON payload for the performance dashboard.
 *
 * Serves the same overview/pages/cache rollup the `PerformanceDashboard`
 * Livewire component builds internally, so React and Vue front-ends can
 * render an equivalent dashboard without going through Livewire.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Controllers\Api\Admin;

use ArtisanPackUI\Performance\Cache\CacheStatistics;
use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Monitoring\WebVitals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dashboard admin API controller.
 *
 *
 * @since      1.0.0
 */
class DashboardAdminApiController extends AdminApiController
{
    /**
     * Supported ranges → number of days.
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
     * GET /api/performance/admin/dashboard.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    public function show( Request $request ): JsonResponse
    {
        $this->authorizeAdmin();

        $range = $this->resolveRange( (string) $request->query( 'range', '7d' ) );
        $days  = self::RANGE_DAYS[ $range ];
        $start = Carbon::today()->subDays( $days - 1 );
        $end   = Carbon::today();

        return response()->json( [
            'range'    => $range,
            'overview' => $this->buildOverview( $start, $end ),
            'pages'    => $this->buildPagesBreakdown( $start, $end ),
            'cache'    => $this->buildCacheSummary(),
        ] );
    }

    /**
     * Weighted p75 rollup per metric.
     *
     * @since 1.0.0
     *
     * @return array<int, array{metric: string, p75: float|null, sample_count: int, status: string}>
     */
    protected function buildOverview( Carbon $start, Carbon $end ): array
    {
        $rows = PerformanceMetric::query()
            ->whereBetween( 'date', [$start, $end] )
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
     * Top 25 (route, metric) rows ordered by weighted p75 desc.
     *
     * @since 1.0.0
     *
     * @return array<int, array{route: string|null, metric: string, p75: float, sample_count: int}>
     */
    protected function buildPagesBreakdown( Carbon $start, Carbon $end ): array
    {
        return PerformanceMetric::query()
            ->whereBetween( 'date', [$start, $end] )
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
     * Cache tab summary — counts only, mutating actions live on the cache endpoint.
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
     * Coerce the range input to a supported key.
     *
     * @since 1.0.0
     */
    protected function resolveRange( string $range ): string
    {
        return isset( self::RANGE_DAYS[ $range ]) ? $range : '7d';
    }
}
