<?php

/**
 * Index suggester service.
 *
 * Reads captured slow queries from the `performance_slow_queries`
 * table (or any caller-supplied iterable) and proposes composite
 * indexes based on the WHERE, ORDER BY, and JOIN clauses that appear
 * most often. The suggester is intentionally heuristic — it does not
 * inspect the database's `pg_stats` / `INFORMATION_SCHEMA.STATISTICS`,
 * which would tightly couple it to a single driver. Instead, it
 * surfaces the columns developers should review and confirms its
 * suggestions by frequency: a column pair that appears in five slow
 * queries is more likely to benefit from an index than one that
 * appears once.
 *
 * Suggestions are returned in priority order (high → low) so callers
 * (the `perf:suggest-indexes` command, the dashboard) can render
 * them as ranked tables without further sorting.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Database;

use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Index suggester class.
 *
 *
 * @since      1.0.0
 */
class IndexSuggester
{
    /**
     * Returns ranked index suggestions for the supplied queries.
     *
     * When `$queries` is null the suggester reads from the slow query
     * table; otherwise the caller can hand-feed a collection of query
     * strings (useful for tests and for ad-hoc analysis of EXPLAIN
     * output stored elsewhere).
     *
     * @since 1.0.0
     *
     * @param  iterable<int, string>|null  $queries  Optional iterable of raw SQL.
     *
     * @return array<int, array{table: string, columns: array<int, string>, sources: array<int, string>, occurrences: int, impact: string}>
     */
    public function suggest( ?iterable $queries = null ): array
    {
        $sources = null === $queries ? $this->loadFromDatabase() : $queries;

        $candidates = [];

        foreach ( $sources as $sql ) {
            $sql = (string) $sql;

            if ( '' === $sql ) {
                continue;
            }

            foreach ( $this->extractCandidates( $sql ) as $candidate ) {
                $key = $candidate['table'] . ':' . implode( ',', $candidate['columns'] );

                if ( ! isset( $candidates[ $key ] ) ) {
                    $candidates[ $key ] = [
                        'table'       => $candidate['table'],
                        'columns'     => $candidate['columns'],
                        'sources'     => [],
                        'occurrences' => 0,
                    ];
                }

                $candidates[ $key ]['occurrences']++;
                $candidates[ $key ]['sources'][] = $candidate['source'];
            }
        }

        return $this->rank( $candidates );
    }

    /**
     * Generates the body of a Laravel migration that adds the suggested indexes.
     *
     * The output is a string of Schema::table calls keyed by table name —
     * callers (the `perf:suggest-indexes` command) drop it into a migration
     * file scaffolded with the appropriate filename. Generating the full
     * migration here would force a coupling to `php artisan make:migration`'s
     * filesystem output that wouldn't survive being driven from a non-CLI
     * context (dashboard, scheduled task).
     *
     * @since 1.0.0
     *
     * @param  array<int, array{table: string, columns: array<int, string>, sources?: array<int, string>, occurrences?: int, impact?: string}>  $suggestions  Suggestions as returned by `suggest()`.
     *
     * @return string PHP source for the migration body.
     */
    public function generateMigrationBody( array $suggestions ): string
    {
        if ( [] === $suggestions ) {
            return '';
        }

        $byTable = [];

        foreach ( $suggestions as $suggestion ) {
            $byTable[ $suggestion['table'] ][] = $suggestion['columns'];
        }

        $blocks = [];

        foreach ( $byTable as $table => $columnSets ) {
            $lines = [];

            foreach ( $columnSets as $columns ) {
                $lines[] = sprintf(
                    '            $table->index([%s]);',
                    implode( ', ', array_map( static fn ( string $column ): string => "'" . $column . "'", $columns ) ),
                );
            }

            $blocks[] = sprintf(
                "        Schema::table('%s', function (Blueprint \$table) {\n%s\n        });",
                $table,
                implode( "\n", $lines ),
            );
        }

        return implode( "\n\n", $blocks );
    }

    /**
     * Loads the slow query log from the database.
     *
     * Wrapped in a try/catch so the suggester degrades gracefully when
     * the `performance_slow_queries` table doesn't exist (apps that
     * haven't run migrations yet, or have disabled `store_in_database`
     * but still call the command). An empty collection is returned in
     * that case so `suggest()` returns no suggestions rather than
     * crashing.
     *
     * @since 1.0.0
     *
     * @return Collection<int, string>
     */
    protected function loadFromDatabase(): Collection
    {
        try {
            return SlowQuery::query()
                ->orderByDesc( 'created_at' )
                ->limit( 1000 )
                ->pluck( 'query' );
        } catch ( Throwable ) {
            return collect();
        }
    }

    /**
     * Extracts candidate (table, columns) tuples from a single SQL string.
     *
     * Three clauses are inspected:
     * - WHERE: simple equality filters (`col = ?`, `col IN (?)`).
     * - ORDER BY: trailing columns benefit from composite indexes that
     *   start with the WHERE columns.
     * - JOIN: the joined table's column on the join condition.
     *
     * The output preserves the order in which columns appear so the
     * suggested index leads with the most selective column.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Raw SQL.
     *
     * @return array<int, array{table: string, columns: array<int, string>, source: string}>
     */
    protected function extractCandidates( string $sql ): array
    {
        $normalized = $this->stripCommentsAndCollapse( $sql );

        $table = $this->extractTable( $normalized );

        if ( null === $table ) {
            return [];
        }

        $whereColumns   = $this->extractWhereColumns( $normalized );
        $orderByColumns = $this->extractOrderByColumns( $normalized );
        $joinCandidates = $this->extractJoinCandidates( $normalized );

        $candidates = [];

        if ( [] !== $whereColumns ) {
            $columns = array_values( array_unique( array_merge( $whereColumns, $orderByColumns ) ) );

            $candidates[] = [
                'table'   => $table,
                'columns' => $columns,
                'source'  => 'where' . ( [] === $orderByColumns ? '' : '+order_by' ),
            ];
        } elseif ( [] !== $orderByColumns ) {
            $candidates[] = [
                'table'   => $table,
                'columns' => $orderByColumns,
                'source'  => 'order_by',
            ];
        }

        foreach ( $joinCandidates as $joinCandidate ) {
            $candidates[] = [
                'table'   => $joinCandidate['table'],
                'columns' => [ $joinCandidate['column'] ],
                'source'  => 'join',
            ];
        }

        return $candidates;
    }

    /**
     * Strips block/inline comments and collapses whitespace.
     *
     * Index suggestion uses regex scanning, so comments and runs of
     * whitespace add noise without information. Strings are not
     * touched — they are unlikely to contain SQL fragments the
     * suggester would mistake for clause boundaries.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Raw SQL.
     */
    protected function stripCommentsAndCollapse( string $sql ): string
    {
        $sql = (string) preg_replace( '#/\*.*?\*/#s', ' ', $sql );
        $sql = (string) preg_replace( '/--[^\n]*/', ' ', $sql );
        $sql = (string) preg_replace( '/\s+/', ' ', $sql );

        return trim( $sql );
    }

    /**
     * Extracts the primary FROM table from a SELECT statement.
     *
     * Handles backtick-quoted, double-quoted, and bare identifiers.
     * Returns null when no FROM clause is present (DML statements
     * like INSERT INTO use a different keyword and aren't candidates
     * for SELECT-style index suggestion).
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Normalized SQL.
     */
    protected function extractTable( string $sql ): ?string
    {
        if ( 1 !== preg_match( '/\bfrom\s+["`]?(?P<table>[a-z_][a-z0-9_]*)["`]?/i', $sql, $matches ) ) {
            return null;
        }

        return strtolower( $matches['table'] );
    }

    /**
     * Extracts equality columns from the WHERE clause.
     *
     * Matches `col = ?`, `col = 'literal'`, `col IN (?)`, and `col IS NULL`
     * patterns. Subqueries are intentionally ignored — their predicates
     * would not benefit from an index on the outer table. Identifiers
     * that include a table prefix (e.g. `t1.user_id`) are unwrapped to
     * the column name only.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Normalized SQL.
     *
     * @return array<int, string>
     */
    protected function extractWhereColumns( string $sql ): array
    {
        if ( 1 !== preg_match( '/\bwhere\b(.*?)(?:\bgroup\s+by\b|\border\s+by\b|\blimit\b|\bhaving\b|$)/is', $sql, $matches ) ) {
            return [];
        }

        $clause = $matches[1];

        preg_match_all(
            '/(?:["`]?[a-z_][a-z0-9_]*["`]?\.)?["`]?(?P<column>[a-z_][a-z0-9_]*)["`]?\s*(?:=|in\s*\(|is\s+(?:not\s+)?null)/i',
            $clause,
            $columnMatches,
        );

        return $this->deduplicateColumns( $columnMatches['column'] ?? [] );
    }

    /**
     * Extracts columns referenced in the ORDER BY clause.
     *
     * The clause is scanned for column references; explicit ASC/DESC
     * directions are dropped because they don't affect index suitability
     * for the standard B-tree case (composite indexes serve both
     * directions of a single column).
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Normalized SQL.
     *
     * @return array<int, string>
     */
    protected function extractOrderByColumns( string $sql ): array
    {
        if ( 1 !== preg_match( '/\border\s+by\b(.*?)(?:\blimit\b|\bhaving\b|$)/is', $sql, $matches ) ) {
            return [];
        }

        $clause = $matches[1];

        preg_match_all(
            '/(?:["`]?[a-z_][a-z0-9_]*["`]?\.)?["`]?(?P<column>[a-z_][a-z0-9_]*)["`]?(?:\s+(?:asc|desc))?/i',
            $clause,
            $columnMatches,
        );

        return $this->deduplicateColumns( $columnMatches['column'] ?? [] );
    }

    /**
     * Extracts table + column pairs from JOIN clauses.
     *
     * Joined tables benefit from indexes on the joined column even when
     * the WHERE clause targets the primary table only. The pattern
     * captures `JOIN <table> ON <table_or_alias>.<column> = ...` and
     * returns the joined table's column.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Normalized SQL.
     *
     * @return array<int, array{table: string, column: string}>
     */
    protected function extractJoinCandidates( string $sql ): array
    {
        preg_match_all(
            '/\bjoin\s+["`]?(?P<table>[a-z_][a-z0-9_]*)["`]?(?:\s+(?:as\s+)?["`]?[a-z_][a-z0-9_]*["`]?)?\s+on\s+(?:["`]?[a-z_][a-z0-9_]*["`]?\.)?["`]?(?P<column>[a-z_][a-z0-9_]*)["`]?\s*=/i',
            $sql,
            $matches,
            PREG_SET_ORDER,
        );

        $candidates = [];

        foreach ( $matches as $match ) {
            $candidates[] = [
                'table'  => strtolower( $match['table'] ),
                'column' => strtolower( $match['column'] ),
            ];
        }

        return $candidates;
    }

    /**
     * Lowercases columns and removes duplicates while preserving order.
     *
     * Order matters for composite-index suggestions — the lead column
     * should be the one the developer wrote first, which is typically
     * the most selective.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $columns  Raw column matches.
     *
     * @return array<int, string>
     */
    protected function deduplicateColumns( array $columns ): array
    {
        $seen   = [];
        $result = [];

        foreach ( $columns as $column ) {
            $column = strtolower( trim( $column ) );

            if ( '' === $column || isset( $seen[ $column ] ) ) {
                continue;
            }

            $seen[ $column ] = true;
            $result[]        = $column;
        }

        return $result;
    }

    /**
     * Ranks candidates by frequency, adds impact, and returns priority order.
     *
     * Impact is a coarse label so the dashboard / CLI table reads at a
     * glance: ≥5 occurrences → High, ≥2 → Medium, otherwise Low. The
     * exact thresholds are heuristic, picked to match how often a real
     * app's slow-query log surfaces the same pattern over a week.
     *
     * @since 1.0.0
     *
     * @param  array<string, array{table: string, columns: array<int, string>, sources: array<int, string>, occurrences: int}>  $candidates  Raw candidates keyed by table+columns.
     *
     * @return array<int, array{table: string, columns: array<int, string>, sources: array<int, string>, occurrences: int, impact: string}>
     */
    protected function rank( array $candidates ): array
    {
        $values = array_values( $candidates );

        usort( $values, static function ( array $a, array $b ): int {
            return $b['occurrences'] <=> $a['occurrences'];
        } );

        return array_map( function ( array $candidate ): array {
            $candidate['sources'] = array_values( array_unique( $candidate['sources'] ) );
            $candidate['impact']  = $this->classifyImpact( $candidate['occurrences'] );

            return $candidate;
        }, $values );
    }

    /**
     * Maps an occurrence count to a coarse impact label.
     *
     * @since 1.0.0
     *
     * @param  int  $occurrences  How many slow queries shared this signature.
     */
    protected function classifyImpact( int $occurrences ): string
    {
        return match ( true ) {
            $occurrences >= 5 => 'High',
            $occurrences >= 2 => 'Medium',
            default           => 'Low',
        };
    }
}
