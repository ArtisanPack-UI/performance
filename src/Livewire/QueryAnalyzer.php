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
     * When embedded inside the PerformanceDashboard the parent passes
     * its own `dateRange` in via `@livewire('perf-query-analyzer',
     * ['dateRange' => ...])`; the URL alias here is scoped to `qrange`
     * so a standalone deep-link (or the dev-app test surface) still
     * survives page reloads without clobbering the dashboard's own
     * `?range=` state.
     *
     * @since 1.0.0
     */
    #[Url( as: 'qrange', history: true )]
    public string $dateRange = '7d';

    /**
     * Optional route filter.
     *
     * @since 1.0.0
     */
    #[Url( as: 'qroute', history: true )]
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
    #[Url( as: 'qmin', history: true )]
    public int $minTimeMs = 0;

    /**
     * Current sort key (`time` or `frequency`).
     *
     * @since 1.0.0
     */
    #[Url( as: 'qsort', history: true )]
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
     * Mounts the component with optional label overrides and a parent
     * date-range prop.
     *
     * The URL-bound properties are coerced through their resolvers so a
     * hostile deep-link (`?qrange=nonsense&qsort=..`) can't park the
     * component in an invalid state. When the parent passes an explicit
     * `$dateRange` it overrides any URL-restored value so the two stay
     * in sync — the PerformanceDashboard owns the canonical range.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $labels  Optional label overrides.
     * @param  string|null  $dateRange  Optional range override from a parent component.
     */
    public function mount( array $labels = [], ?string $dateRange = null ): void
    {
        $this->labels    = array_filter( $labels, 'is_string' );

        if ( null !== $dateRange && '' !== $dateRange ) {
            $this->dateRange = $dateRange;
        }

        $this->dateRange = $this->resolveRange( $this->dateRange );
        $this->sort      = $this->resolveSort( $this->sort );
        $this->minTimeMs = max( 0, $this->minTimeMs );
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

            // Sanitize fields against CSV formula injection: Excel /
            // LibreOffice / Google Sheets treat cells starting with
            // `=`, `+`, `-`, `@`, TAB, or CR as formulas. Captured SQL
            // (and route/file names) can start with any of those, so
            // prefix a single quote before writing to break the
            // formula-detection heuristic without breaking round-trip
            // parsers.
            $sanitize = static function ( mixed $value ): string {
                $string = (string) $value;

                if ( '' === $string ) {
                    return '';
                }

                if ( in_array( $string[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
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

        $samples    = $rows->pluck( 'sample_query' )->all();
        $suggestion = $this->buildSuggestionMap( $samples );

        return $rows->map( function ( $row ) use ( $suggestion ): array {
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
                'suggestion'   => $this->lookupSuggestion( $suggestion, $sample ),
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
     * Builds the batch suggestion map — table => formatted suggestion.
     *
     * `IndexSuggester::suggest()` aggregates candidates by table+columns
     * across the whole sample set, so calling it once with every sample
     * preserves the frequency-based impact classification. Per-row
     * `suggest([$sample])` calls would collapse every candidate to
     * `impact='Low'` because a single query is never frequent enough
     * to promote itself to High.
     *
     * The returned map is keyed by table name so a row's lookup only
     * needs to know which table the SQL touches (a cheap regex on the
     * FROM clause). If a sample SQL touches two tables, the
     * highest-impact suggestion wins for each.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $samples  Sample SQL strings from the rendered rows.
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

        // Rank so higher-impact wins on ties when multiple suggestions
        // reference the same table.
        $weights = [ 'high' => 3, 'medium' => 2, 'low' => 1 ];

        $out = [];

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
                'suggestion' => $this->formatSuggestion( $suggestion ),
                'weight'     => $weight,
            ];
        }

        return $out;
    }

    /**
     * Looks up the batched suggestion for a single row's sample SQL.
     *
     * Matches on the first `FROM <table>` token in the sample; if the
     * suggestion map has an entry for that table, we return its
     * formatted hint. This is enough for the vast majority of queries
     * the panel shows (single-table SELECTs with an optional WHERE),
     * and avoids the cost of re-parsing the SQL for a full candidate
     * extraction per row.
     *
     * @since 1.0.0
     *
     * @param  array<string, array{suggestion: string, weight: int}>  $suggestionMap  Batch map from buildSuggestionMap().
     * @param  string  $sample  Raw sample SQL for the row.
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
            'title'      => (string) __( 'Query Analyzer' ),
            'range'      => (string) __( 'Date range' ),
            'route'      => (string) __( 'Route' ),
            'min_time'   => (string) __( 'Min time (ms)' ),
            'sort_time'  => (string) __( 'Sort by time' ),
            'sort_freq'  => (string) __( 'Sort by frequency' ),
            'export'     => (string) __( 'Export CSV' ),
            'query'      => (string) __( 'Query' ),
            'time'       => (string) __( 'Time (ms)' ),
            'count'      => (string) __( 'Count' ),
            'file'       => (string) __( 'File:Line' ),
            'suggestion' => (string) __( 'Suggestion' ),
            'all_routes' => (string) __( 'All routes' ),
            'empty'      => (string) __( 'No slow queries logged for this range.' ),
            'show_full'  => (string) __( 'Show full query' ),
            'hide_full'  => (string) __( 'Hide full query' ),
        ];

        // Filter here (not just in mount) because Livewire re-hydrates
        // `$labels` from the client payload on every update, bypassing
        // the mount-time filter. Without this, a hostile client can
        // send `labels: { title: {"a": 1} }` and trigger a Blade
        // "Array to string conversion" error.
        $safe = array_filter( $this->labels, 'is_string' );

        return array_merge( $defaults, $safe );
    }
}
