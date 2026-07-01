<?php

/**
 * Query analyzer Livewire component.
 *
 * Admin surface for reviewing slow queries captured by the
 * `SlowQueryLogger` into the `performance_slow_queries` table. Groups
 * rows by their normalized signature so repeat offenders collapse to a
 * single row, ranks by either total time or occurrence count, and
 * augments each signature with an index-column suggestion from
 * `IndexSuggester`. The component is read-only — actions (copy, view
 * trace, export) are client-side or driven by browser downloads to
 * keep the dashboard state simple.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Livewire;

use ArtisanPackUI\Performance\Database\IndexSuggester;
use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Query analyzer component class.
 *
 *
 * @since      1.0.0
 */
class QueryAnalyzer extends Component
{
    /**
     * Date ranges the filter offers.
     *
     * Expressed as `key => days` so the query filter can convert a range
     * to a `>= today - days` clause without re-parsing labels.
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
     * Sort options the header exposes.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SORTS = [ 'time', 'frequency' ];

    /**
     * Maximum number of grouped signatures rendered per page.
     *
     * @since 1.0.0
     */
    public const RESULT_LIMIT = 50;

    /**
     * Currently selected date range.
     *
     * @since 1.0.0
     */
    #[Url( as: 'range', history: true )]
    public string $dateRange = '7d';

    /**
     * Optional route filter.
     *
     * @since 1.0.0
     */
    #[Url( as: 'route', history: true )]
    public string $routeFilter = '';

    /**
     * Minimum single-execution time in ms.
     *
     * Filters out queries whose peak time falls below the threshold so
     * the list surfaces the queries the operator actually needs to look
     * at rather than a wall of near-threshold rows.
     *
     * @since 1.0.0
     */
    #[Url( as: 'min', history: true )]
    public int $minTimeMs = 0;

    /**
     * Current sort key (`time` or `frequency`).
     *
     * @since 1.0.0
     */
    #[Url( as: 'sort', history: true )]
    public string $sort = 'time';

    /**
     * Label overrides supplied by the host application.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public array $labels = [];

    /**
     * Signature of the row being previewed in full, or null when none is open.
     *
     * @since 1.0.0
     */
    public ?string $expandedSignature = null;

    /**
     * Mounts the component with optional label overrides.
     *
     * The URL-bound properties are coerced through their resolvers so a
     * hostile deep-link (`?range=nonsense&sort=..`) can't park the
     * component in an invalid state — every render sees the same
     * validated shape whether or not the URL was fresh.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $labels  Optional label overrides.
     */
    public function mount( array $labels = [] ): void
    {
        $this->labels      = array_filter( $labels, 'is_string' );
        $this->dateRange   = $this->resolveRange( $this->dateRange );
        $this->sort        = $this->resolveSort( $this->sort );
        $this->minTimeMs   = max( 0, $this->minTimeMs );
    }

    /**
     * Applies the supplied filter values and resets pagination.
     *
     * @since 1.0.0
     */
    public function updatedDateRange(): void
    {
        $this->dateRange         = $this->resolveRange( $this->dateRange );
        $this->expandedSignature = null;
    }

    /**
     * Applies the supplied sort key.
     *
     * @since 1.0.0
     *
     * @param  string  $sort  Requested sort key.
     */
    public function setSort( string $sort ): void
    {
        $this->sort              = $this->resolveSort( $sort );
        $this->expandedSignature = null;
    }

    /**
     * Toggles the expanded view for a given signature.
     *
     * Uses a signature hash rather than the raw SQL so wire:click
     * expressions in the view stay short and free of user-controlled
     * characters.
     *
     * @since 1.0.0
     *
     * @param  string  $hash  MD5 of the normalized signature.
     */
    public function toggleExpanded( string $hash ): void
    {
        $this->expandedSignature = $this->expandedSignature === $hash ? null : $hash;
    }

    /**
     * Downloads the current result set as CSV.
     *
     * StreamedResponse keeps memory bounded for large exports — the
     * grouped rows are already capped at RESULT_LIMIT so this is
     * defensive, not load-bearing, but staying streamed means an
     * operator can lift the limit without a rewrite.
     *
     * @since 1.0.0
     */
    public function exportCsv(): StreamedResponse
    {
        $rows     = $this->buildRows();
        $filename = 'slow-queries-' . Carbon::now()->format( 'Y-m-d-His' ) . '.csv';

        return response()->streamDownload( static function () use ( $rows ): void {
            $handle = fopen( 'php://output', 'w' );

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
                    $row['query'],
                    $row['peak_time_ms'],
                    $row['avg_time_ms'],
                    $row['occurrences'],
                    $row['route'] ?? '',
                    $row['file'] ?? '',
                    $row['line'] ?? '',
                    $row['last_seen'],
                    $row['suggestion'] ?? '',
                ] );
            }

            fclose( $handle );
        }, $filename, [
            'Content-Type' => 'text/csv',
        ] );
    }

    /**
     * Renders the component template.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view( 'performance::livewire.query-analyzer', [
            'rows'            => $this->buildRows(),
            'availableRoutes' => $this->availableRoutes(),
            'ranges'          => array_keys( self::RANGE_DAYS ),
            'sorts'           => self::SORTS,
            'resolvedLabels'  => $this->resolveLabels(),
        ] );
    }

    /**
     * Builds the grouped-and-ranked rows for the current filter selection.
     *
     * Grouping happens in SQL — `query_normalized` collapses logically
     * identical queries and the aggregate columns (`MAX(time_ms)`,
     * `COUNT(*)`) drive the sort. Doing it in PHP would need `SELECT *`
     * over potentially thousands of rows to fold down into a few dozen.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildRows(): array
    {
        if ( ! class_exists( SlowQuery::class ) ) {
            return [];
        }

        $rows = $this->applyFilters( SlowQuery::query() )
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
            ->orderByDesc( 'time' === $this->sort ? 'peak_time_ms' : 'occurrences' )
            ->limit( self::RESULT_LIMIT )
            ->get();

        $suggester   = app( IndexSuggester::class );
        $suggestions = $this->buildSuggestions( $suggester, $rows->pluck( 'sample_query' )->all() );

        return $rows->map( static function ( $row ) use ( $suggestions ): array {
            $normalized = (string) $row->query_normalized;

            return [
                'hash'         => md5( $normalized ),
                'query'        => (string) $row->sample_query,
                'normalized'   => $normalized,
                'peak_time_ms' => (float) $row->peak_time_ms,
                'avg_time_ms'  => (float) $row->avg_time_ms,
                'occurrences'  => (int) $row->occurrences,
                'route'        => $row->route,
                'file'         => $row->file,
                'line'         => null !== $row->line ? (int) $row->line : null,
                'last_seen'    => (string) $row->last_seen,
                'suggestion'   => $suggestions[ $normalized ] ?? null,
            ];
        } )->all();
    }

    /**
     * Applies the current filter state to the given base query.
     *
     * @since 1.0.0
     *
     * @param  Builder  $query  Slow-query base builder.
     */
    protected function applyFilters( Builder $query ): Builder
    {
        $query->where( 'created_at', '>=', $this->startDate() );

        if ( '' !== $this->routeFilter ) {
            $query->where( 'route', $this->routeFilter );
        }

        if ( $this->minTimeMs > 0 ) {
            $query->where( 'time_ms', '>=', $this->minTimeMs );
        }

        return $query;
    }

    /**
     * Returns the distinct route values available for the filter dropdown.
     *
     * Restricted to routes that appear inside the current date range so
     * the dropdown never lists routes that would produce an empty table.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function availableRoutes(): array
    {
        if ( ! class_exists( SlowQuery::class ) ) {
            return [];
        }

        return SlowQuery::query()
            ->where( 'created_at', '>=', $this->startDate() )
            ->whereNotNull( 'route' )
            ->distinct()
            ->orderBy( 'route' )
            ->pluck( 'route' )
            ->filter( static fn ( $value ): bool => is_string( $value ) && '' !== $value )
            ->values()
            ->all();
    }

    /**
     * Builds a `normalized => suggestion` map for the queries being shown.
     *
     * `IndexSuggester::suggest()` groups candidates by table+columns
     * across the whole set, so we cannot map back to the original
     * signatures once it returns. Instead we run each signature through
     * `extractCandidates` individually — this is what the CLI command
     * does when it needs per-signature attribution.
     *
     * @since 1.0.0
     *
     * @param  IndexSuggester  $suggester  Injected suggester service.
     * @param  array<int, string>  $queries  Sample queries to analyze.
     *
     * @return array<string, string>
     */
    protected function buildSuggestions( IndexSuggester $suggester, array $queries ): array
    {
        $out = [];

        foreach ( $queries as $sample ) {
            $sample = (string) $sample;

            if ( '' === $sample ) {
                continue;
            }

            $suggestions = $suggester->suggest( [ $sample ] );

            if ( [] === $suggestions ) {
                continue;
            }

            $normalized         = $this->normalizeForKey( $sample );
            $out[ $normalized ] = $this->formatSuggestion( $suggestions[0] );
        }

        return $out;
    }

    /**
     * Returns a normalized signature that keys back into the grouped rows.
     *
     * The stored `query_normalized` column comes from
     * `Database\QueryAnalyzer::normalize()` — reusing the analyzer's
     * canonicalization keeps both sides in lockstep so a signature
     * emitted by the suggester matches a row shown in the table.
     *
     * @since 1.0.0
     *
     * @param  string  $sample  Raw sample SQL.
     */
    protected function normalizeForKey( string $sample ): string
    {
        return app( \ArtisanPackUI\Performance\Database\QueryAnalyzer::class )->normalize( $sample );
    }

    /**
     * Formats an IndexSuggester row into a short, human-readable hint.
     *
     * @since 1.0.0
     *
     * @param  array{table: string, columns: array<int, string>, impact?: string}  $suggestion  A single suggestion row.
     */
    protected function formatSuggestion( array $suggestion ): string
    {
        return (string) __( 'Add index on :table (:columns)', [
            'table'   => $suggestion['table'],
            'columns' => implode( ', ', $suggestion['columns'] ),
        ] );
    }

    /**
     * Returns the inclusive start date for the current range.
     *
     * @since 1.0.0
     */
    protected function startDate(): Carbon
    {
        $days = self::RANGE_DAYS[ $this->dateRange ] ?? self::RANGE_DAYS['7d'];

        return Carbon::now()->subDays( $days - 1 )->startOfDay();
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
     * Normalizes the supplied sort key.
     *
     * @since 1.0.0
     *
     * @param  string  $sort  Candidate sort key.
     */
    protected function resolveSort( string $sort ): string
    {
        return in_array( $sort, self::SORTS, true ) ? $sort : 'time';
    }

    /**
     * Resolves the action labels, merging host overrides over defaults.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function resolveLabels(): array
    {
        $defaults = [
            'title'         => (string) __( 'Query Analyzer' ),
            'range'         => (string) __( 'Date range' ),
            'route'         => (string) __( 'Route' ),
            'min_time'      => (string) __( 'Min time (ms)' ),
            'sort_time'     => (string) __( 'Sort by time' ),
            'sort_freq'     => (string) __( 'Sort by frequency' ),
            'export'        => (string) __( 'Export CSV' ),
            'query'         => (string) __( 'Query' ),
            'time'          => (string) __( 'Time (ms)' ),
            'count'         => (string) __( 'Count' ),
            'file'          => (string) __( 'File:Line' ),
            'suggestion'    => (string) __( 'Suggestion' ),
            'all_routes'    => (string) __( 'All routes' ),
            'empty'         => (string) __( 'No slow queries logged for this range.' ),
            'show_full'     => (string) __( 'Show full query' ),
            'hide_full'     => (string) __( 'Hide full query' ),
        ];

        return array_merge( $defaults, $this->labels );
    }
}
