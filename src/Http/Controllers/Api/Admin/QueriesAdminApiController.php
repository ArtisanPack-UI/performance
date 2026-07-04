<?php

/**
 * QueriesAdminApiController — slow-query JSON payload + CSV export.
 *
 * Mirrors the aggregation + index-suggestion pipeline that the
 * `QueryAnalyzer` Livewire component builds internally. The rows shape
 * matches the Livewire output so React/Vue front-ends can render an
 * equivalent table without additional client-side massaging.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Controllers\Api\Admin;

use ArtisanPackUI\Performance\Database\IndexSuggester;
use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Queries admin API controller.
 *
 *
 * @since      1.0.0
 */
class QueriesAdminApiController extends AdminApiController
{
    public const RANGE_DAYS = [
        '24h' => 1,
        '7d'  => 7,
        '30d' => 30,
        '90d' => 90,
    ];

    public const RESULT_LIMIT = 50;

    public const EXPORT_LIMIT = 10000;

    /**
     * GET /api/performance/admin/queries — JSON payload.
     *
     * @since 1.0.0
     */
    public function index( Request $request ): JsonResponse
    {
        $this->authorizeAdmin();

        $filters = $this->resolveFilters( $request );

        return response()->json( [
            'rows'             => $this->buildRows( $filters, self::RESULT_LIMIT ),
            'available_routes' => $this->availableRoutes( $filters['start'] ),
            'sort'             => $filters['sort'],
        ] );
    }

    /**
     * GET /api/performance/admin/queries/export — CSV download.
     *
     * @since 1.0.0
     */
    public function export( Request $request ): StreamedResponse
    {
        $this->authorizeAdmin();

        $filters  = $this->resolveFilters( $request );
        // Export uses a much larger cap than the interactive listing so
        // callers requesting the full filtered dataset don't silently
        // receive a 50-row preview.
        $rows     = $this->buildRows( $filters, self::EXPORT_LIMIT );
        $filename = 'slow-queries-' . Carbon::now()->format( 'Y-m-d-His' ) . '.csv';

        return response()->streamDownload( static function () use ( $rows ): void {
            $handle = fopen( 'php://output', 'w' );

            $sanitize = static function ( mixed $value ): string {
                $string = (string) $value;

                if ( '' === $string ) {
                    return '';
                }

                if ( in_array( $string[0], ['=', '+', '-', '@', "\t", "\r"], true ) ) {
                    return "'" . $string;
                }

                return $string;
            };

            fputcsv( $handle, [
                'query',
                'peak_time_ms',
                'avg_time_ms',
                'occurrences',
                'route',
                'file',
                'line',
                'last_seen',
                'suggestion',
            ] );

            foreach ( $rows as $row ) {
                fputcsv( $handle, [
                    $sanitize( $row['query'] ),
                    $row['peak_time_ms'],
                    $row['avg_time_ms'],
                    $row['occurrences'],
                    $sanitize( $row['route'] ?? '' ),
                    $sanitize( $row['file'] ?? '' ),
                    $row['line'] ?? '',
                    $sanitize( $row['last_seen'] ),
                    $sanitize( $row['suggestion'] ?? '' ),
                ] );
            }

            fclose( $handle );
        }, $filename, [
            'Content-Type' => 'text/csv',
        ] );
    }

    /**
     * @return array{start: Carbon, route: string, minTimeMs: int, sort: string}
     */
    protected function resolveFilters( Request $request ): array
    {
        $range     = (string) $request->query( 'range', '7d' );
        $range     = isset( self::RANGE_DAYS[ $range ] ) ? $range : '7d';
        $days      = self::RANGE_DAYS[ $range ];
        $start     = Carbon::now()->subDays( $days - 1 )->startOfDay();
        $route     = trim( (string) $request->query( 'route', '' ) );
        $minTimeMs = max( 0, (int) $request->query( 'min_time_ms', 0 ) );
        $sort      = (string) $request->query( 'sort', 'time' );
        $sort      = in_array( $sort, ['time', 'frequency'], true ) ? $sort : 'time';

        return [
            'start'     => $start,
            'route'     => $route,
            'minTimeMs' => $minTimeMs,
            'sort'      => $sort,
        ];
    }

    /**
     * @param  array{start: Carbon, route: string, minTimeMs: int, sort: string}  $filters
     * @param  int  $limit  Maximum rows to return.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildRows( array $filters, int $limit ): array
    {
        if ( ! class_exists( SlowQuery::class ) ) {
            return [];
        }

        $rows = $this->applyFilters( SlowQuery::query(), $filters )
            ->selectRaw( implode( ', ', [
                'query_normalized',
                'MAX(query) as sample_query',
                'MAX(time_ms) as peak_time_ms',
                'AVG(time_ms) as avg_time_ms',
                'COUNT(*) as occurrences',
                'MAX(route) as route',
                'MAX(file) as file',
                'MAX(line) as line',
                'MAX(created_at) as last_seen',
            ] ) )
            ->groupBy( 'query_normalized' )
            ->orderByDesc( 'time' === $filters['sort'] ? 'peak_time_ms' : 'occurrences' )
            ->limit( $limit )
            ->get();

        $samples       = $rows->pluck( 'sample_query' )->all();
        $suggestionMap = $this->buildSuggestionMap( $samples );

        return $rows->map( function ( $row ) use ( $suggestionMap ): array {
            $normalized = (string) $row->query_normalized;
            $sample     = (string) $row->sample_query;

            return [
                'hash'         => md5( $normalized ),
                'query'        => $sample,
                'normalized'   => $normalized,
                'peak_time_ms' => (float) $row->peak_time_ms,
                'avg_time_ms'  => (float) $row->avg_time_ms,
                'occurrences'  => (int) $row->occurrences,
                'route'        => $row->route,
                'file'         => $row->file,
                'line'         => null !== $row->line ? (int) $row->line : null,
                'last_seen'    => (string) $row->last_seen,
                'suggestion'   => $this->lookupSuggestion( $suggestionMap, $sample ),
            ];
        } )->all();
    }

    /**
     * @param  Builder<SlowQuery>  $query
     * @param  array{start: Carbon, route: string, minTimeMs: int, sort: string}  $filters
     *
     * @return Builder<SlowQuery>
     */
    protected function applyFilters( Builder $query, array $filters ): Builder
    {
        $query->where( 'created_at', '>=', $filters['start'] );

        if ( '' !== $filters['route'] ) {
            $query->where( 'route', $filters['route'] );
        }

        if ( $filters['minTimeMs'] > 0 ) {
            $query->where( 'time_ms', '>=', $filters['minTimeMs'] );
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function availableRoutes( Carbon $start ): array
    {
        if ( ! class_exists( SlowQuery::class ) ) {
            return [];
        }

        return SlowQuery::query()
            ->where( 'created_at', '>=', $start )
            ->whereNotNull( 'route' )
            ->distinct()
            ->orderBy( 'route' )
            ->pluck( 'route' )
            ->filter( static fn ( $value ): bool => is_string( $value ) && '' !== $value )
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $samples
     *
     * @return array<string, array{suggestion: string, weight: int}>
     */
    protected function buildSuggestionMap( array $samples ): array
    {
        $samples = array_values( array_filter(
            array_map( 'strval', $samples ),
            static fn ( string $s ): bool => '' !== $s,
        ) );

        if ( [] === $samples ) {
            return [];
        }

        $suggestions = app( IndexSuggester::class )->suggest( $samples );
        $weights     = ['high' => 3, 'medium' => 2, 'low' => 1];
        $out         = [];

        foreach ( $suggestions as $suggestion ) {
            $table  = (string) ( $suggestion['table'] ?? '' );
            $impact = strtolower( (string) ( $suggestion['impact'] ?? 'medium' ) );

            if ( '' === $table ) {
                continue;
            }

            $weight = $weights[ $impact ] ?? 0;

            if ( isset( $out[ $table ] ) && $out[ $table ]['weight'] >= $weight ) {
                continue;
            }

            $out[ $table ] = [
                'suggestion' => (string) __( 'Add index on :table (:columns)', [
                    'table'   => $table,
                    'columns' => implode( ', ', (array) ( $suggestion['columns'] ?? [] ) ),
                ] ),
                'weight' => $weight,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{suggestion: string, weight: int}>  $suggestionMap
     */
    protected function lookupSuggestion( array $suggestionMap, string $sample ): ?string
    {
        if ( [] === $suggestionMap || '' === $sample ) {
            return null;
        }

        if ( 1 !== preg_match( '/\bfrom\s+`?([a-z_][a-z0-9_]*)`?/i', $sample, $matches ) ) {
            return null;
        }

        $table = strtolower( $matches[1] );

        return $suggestionMap[ $table ]['suggestion'] ?? null;
    }
}
