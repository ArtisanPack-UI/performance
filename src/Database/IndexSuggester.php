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
 * Parsing happens in three preparation passes before any clause-level
 * extraction:
 *
 * 1. String literals are extracted into placeholders so subsequent
 *    regexes can't be tricked by `--` or `;` inside a quoted value.
 * 2. SQL line / block comments are stripped from the placeholder-
 *    safe text.
 * 3. Balanced subqueries are removed so the outer FROM / WHERE / ORDER BY
 *    can be matched without slurping inner clauses (the previous
 *    implementation regularly attributed inner subquery columns to the
 *    outer table).
 *
 * After preparation the suggester walks FROM and JOIN clauses to build
 * an alias → table map, then resolves each column to its owning table
 * via that map. Columns whose qualifier isn't in the map are skipped
 * — a wrong-table suggestion is worse than no suggestion because the
 * generated migration would fail to apply.
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
     * SQL keywords / clause terminators that must never be captured as columns.
     *
     * The ORDER BY parser scans for identifier tokens, but trailing
     * lock hints (`FOR UPDATE`, `SKIP LOCKED`), null-ordering specs
     * (`NULLS FIRST`, `NULLS LAST`), direction markers, and OFFSET /
     * FETCH clauses look identical to bare identifiers. Without an
     * explicit blocklist a clause like `ORDER BY created_at FOR UPDATE`
     * would yield the columns `['created_at', 'for', 'update']` and
     * scaffold a migration that fails on apply.
     *
     * @since 1.0.0
     *
     * @var array<string, true>
     */
    protected const SQL_KEYWORDS = [
        'asc'      => true,
        'desc'     => true,
        'for'      => true,
        'update'   => true,
        'share'    => true,
        'lock'     => true,
        'skip'     => true,
        'locked'   => true,
        'nowait'   => true,
        'nulls'    => true,
        'first'    => true,
        'last'     => true,
        'offset'   => true,
        'fetch'    => true,
        'rows'     => true,
        'row'      => true,
        'only'     => true,
        'with'     => true,
        'ties'     => true,
        'using'    => true,
        'as'       => true,
        'on'       => true,
        'and'      => true,
        'or'       => true,
        'not'      => true,
        'null'     => true,
        'is'       => true,
        'in'       => true,
        'like'     => true,
        'between'  => true,
        'true'     => true,
        'false'    => true,
        'group'    => true,
        'by'       => true,
        'having'   => true,
        'limit'    => true,
        'order'    => true,
        'where'    => true,
        'select'   => true,
        'from'     => true,
        'join'     => true,
        'inner'    => true,
        'outer'    => true,
        'left'     => true,
        'right'    => true,
        'cross'    => true,
        'full'     => true,
        'natural'  => true,
    ];

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
                    implode( ', ', array_map( fn ( string $column ): string => $this->phpQuote( $column ), $columns ) ),
                );
            }

            $blocks[] = sprintf(
                "        Schema::table(%s, function (Blueprint \$table) {\n%s\n        });",
                $this->phpQuote( $table ),
                implode( "\n", $lines ),
            );
        }

        return implode( "\n\n", $blocks );
    }

    /**
     * PHP-quotes an identifier for safe embedding in generated migration code.
     *
     * Backslashes and single quotes are escaped so column / table names
     * that include those characters (rare but legal in PostgreSQL with
     * `"O'Brien"`-style quoted identifiers) don't produce unrunnable
     * migration files.
     *
     * @since 1.0.0
     *
     * @param  string  $value  Identifier to embed.
     */
    protected function phpQuote( string $value ): string
    {
        return "'" . str_replace( [ '\\', "'" ], [ '\\\\', "\\'" ], $value ) . "'";
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
     * The query is first preprocessed (literals → placeholders, comments
     * stripped, subqueries blanked). FROM and JOIN clauses are walked to
     * build an alias → table map. WHERE / ORDER BY / JOIN columns are
     * then resolved through that map; any column whose qualifier can't
     * be resolved is skipped so we never propose `INDEX(status)` on a
     * table that doesn't have a `status` column.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Raw SQL.
     *
     * @return array<int, array{table: string, columns: array<int, string>, source: string}>
     */
    protected function extractCandidates( string $sql ): array
    {
        $prepared = $this->prepareSql( $sql );

        if ( '' === $prepared['outer'] ) {
            return [];
        }

        $aliasMap = $this->buildAliasMap( $prepared['outer'] );

        if ( [] === $aliasMap ) {
            return [];
        }

        $primaryTable = $this->extractPrimaryTable( $prepared['outer'] );

        if ( null === $primaryTable ) {
            return [];
        }

        $whereColumns   = $this->extractWhereColumns( $prepared['outer'], $aliasMap, $primaryTable );
        $orderByColumns = $this->extractOrderByColumns( $prepared['outer'], $aliasMap, $primaryTable );
        $joinCandidates = $this->extractJoinCandidates( $prepared['outer'], $aliasMap );

        $candidates = [];

        $primaryWhere = $whereColumns[ $primaryTable ] ?? [];
        $primaryOrder = $orderByColumns[ $primaryTable ] ?? [];

        if ( [] !== $primaryWhere ) {
            $columns = $this->mergeOrdered( $primaryWhere, $primaryOrder );

            $candidates[] = [
                'table'   => $primaryTable,
                'columns' => $columns,
                'source'  => [] === $primaryOrder ? 'where' : 'where+order_by',
            ];
        } elseif ( [] !== $primaryOrder ) {
            $candidates[] = [
                'table'   => $primaryTable,
                'columns' => $primaryOrder,
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
     * Preprocesses raw SQL into a parser-safe form.
     *
     * Three steps: stash single- and double-quoted string literals into
     * placeholders so subsequent regexes can't be confused by `--` /
     * `;` inside a quoted value, strip line + block comments, then
     * replace balanced parenthesized subexpressions with a single
     * space so the outer query's clause boundaries are unambiguous.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Raw SQL.
     *
     * @return array{outer: string} Map containing the prepared outer-query text.
     */
    protected function prepareSql( string $sql ): array
    {
        $stripped = $this->stripStringLiterals( $sql );
        $stripped = $this->stripComments( $stripped );
        $stripped = $this->stripSubqueries( $stripped );
        $stripped = (string) preg_replace( '/\s+/', ' ', $stripped );

        return [ 'outer' => trim( $stripped ) ];
    }

    /**
     * Replaces every string literal in the SQL with a same-length space run.
     *
     * Same-length replacement preserves byte offsets for any downstream
     * tooling and keeps the regex preprocessor from changing the shape
     * of surrounding tokens (e.g. spaces between predicates).
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Raw SQL.
     */
    protected function stripStringLiterals( string $sql ): string
    {
        $output = '';
        $i      = 0;
        $len    = strlen( $sql );

        while ( $i < $len ) {
            $char = $sql[ $i ];

            if ( "'" === $char || '"' === $char ) {
                $output .= ' ';
                $i++;

                while ( $i < $len ) {
                    $inner = $sql[ $i ];

                    if ( '\\' === $inner && $i + 1 < $len ) {
                        $output .= '  ';
                        $i += 2;

                        continue;
                    }

                    if ( $inner === $char ) {
                        // Standard SQL doubled-quote escape (e.g. 'O''Brien'):
                        // consume both quote chars as part of the literal.
                        if ( $i + 1 < $len && $sql[ $i + 1 ] === $char ) {
                            $output .= '  ';
                            $i += 2;
                            continue;
                        }

                        $output .= ' ';
                        $i++;
                        break;
                    }

                    $output .= ' ';
                    $i++;
                }

                continue;
            }

            $output .= $char;
            $i++;
        }

        return $output;
    }

    /**
     * Strips `--` line comments and `/* ... *​/` block comments.
     *
     * Runs AFTER `stripStringLiterals()` so `--` inside a quoted value
     * is not misread as the start of a comment.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Literal-stripped SQL.
     */
    protected function stripComments( string $sql ): string
    {
        $sql = (string) preg_replace( '#/\*.*?\*/#s', ' ', $sql );
        $sql = (string) preg_replace( '/--[^\n]*/', ' ', $sql );

        return $sql;
    }

    /**
     * Replaces every balanced parenthesized subexpression with a space.
     *
     * Subqueries inside SELECT lists, WHERE predicates, and CTE bodies
     * all show up as parenthesized SELECT statements. Removing them
     * before clause extraction prevents the outer-clause regexes from
     * slurping inner WHERE / ORDER BY content and attributing it to
     * the outer table. The replacement is depth-aware so nested
     * parens unwind correctly.
     *
     * @since 1.0.0
     *
     * @param  string  $sql  Literal-stripped, comment-free SQL.
     */
    protected function stripSubqueries( string $sql ): string
    {
        $output = '';
        $i      = 0;
        $len    = strlen( $sql );

        while ( $i < $len ) {
            $char = $sql[ $i ];

            if ( '(' === $char ) {
                $depth = 1;
                $j     = $i + 1;

                while ( $j < $len && $depth > 0 ) {
                    if ( '(' === $sql[ $j ] ) {
                        $depth++;
                    } elseif ( ')' === $sql[ $j ] ) {
                        $depth--;
                    }

                    $j++;
                }

                $output .= ' ';
                $i       = $j;

                continue;
            }

            $output .= $char;
            $i++;
        }

        return $output;
    }

    /**
     * Builds an alias → table map from FROM + JOIN clauses.
     *
     * Both `FROM users u` and `FROM users AS u` (and `FROM users`
     * without an alias, in which case the table name acts as its own
     * alias) are supported. The first FROM clause supplies the
     * primary table; subsequent `JOIN <table> [AS] <alias>` entries
     * add additional aliases.
     *
     * @since 1.0.0
     *
     * @param  string  $outer  The outer query string.
     *
     * @return array<string, string> Lowercase alias → lowercase table name.
     */
    protected function buildAliasMap( string $outer ): array
    {
        $map = [];

        preg_match_all(
            '/\b(?:from|join)\s+["`]?(?P<table>[a-z_][a-z0-9_]*)["`]?(?:\s+(?:as\s+)?["`]?(?P<alias>[a-z_][a-z0-9_]*)["`]?)?/i',
            $outer,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ( $matches as $match ) {
            $table = strtolower( $match['table'] );
            $alias = isset( $match['alias'] ) && '' !== $match['alias']
                ? strtolower( $match['alias'] )
                : $table;

            if ( isset( self::SQL_KEYWORDS[ $alias ] ) ) {
                $alias = $table;
            }

            $map[ $alias ] = $table;
            $map[ $table ] = $table;
        }

        return $map;
    }

    /**
     * Returns the primary FROM table (used as the default WHERE / ORDER BY owner).
     *
     * Subqueries are already gone by the time this runs, so the first
     * matching `\bfrom\s+<ident>` is the outer query's primary table.
     *
     * @since 1.0.0
     *
     * @param  string  $outer  The outer query string.
     */
    protected function extractPrimaryTable( string $outer ): ?string
    {
        if ( 1 !== preg_match( '/\bfrom\s+["`]?(?P<table>[a-z_][a-z0-9_]*)["`]?/i', $outer, $matches ) ) {
            return null;
        }

        return strtolower( $matches['table'] );
    }

    /**
     * Extracts equality columns from the WHERE clause, grouped by owning table.
     *
     * Matches simple equality predicates (`col = ?`, `col IN (?)`, `col IS NULL`).
     * Each captured column carries its `table.` qualifier through to the
     * alias map so JOIN queries don't end up suggesting columns on the
     * wrong table. Columns without a qualifier are attributed to the
     * primary table.
     *
     * @since 1.0.0
     *
     * @param  string  $outer  The outer query string.
     * @param  array<string, string>  $aliasMap  Alias → table map.
     * @param  string  $primaryTable  Fallback table for unqualified columns.
     *
     * @return array<string, array<int, string>> Table → column list (insertion order preserved).
     */
    protected function extractWhereColumns( string $outer, array $aliasMap, string $primaryTable ): array
    {
        if ( 1 !== preg_match( '/\bwhere\b(.*?)(?:\bgroup\s+by\b|\border\s+by\b|\blimit\b|\bhaving\b|$)/is', $outer, $matches ) ) {
            return [];
        }

        $clause = $matches[1];

        preg_match_all(
            '/(?:["`]?(?P<table>[a-z_][a-z0-9_]*)["`]?\.)?["`]?(?P<column>[a-z_][a-z0-9_]*)["`]?\s*(?:=|in\s*\(|is\s+(?:not\s+)?null)/i',
            $clause,
            $columnMatches,
            PREG_SET_ORDER,
        );

        return $this->groupByTable( $columnMatches, $aliasMap, $primaryTable );
    }

    /**
     * Extracts columns referenced in the ORDER BY clause, grouped by table.
     *
     * Function calls (`LOWER(name)`, `COALESCE(a, b)`) are skipped — the
     * function name is not a column. SQL keywords appearing in trailing
     * clauses (FOR UPDATE, NULLS LAST, SKIP LOCKED) are filtered out by
     * the SQL_KEYWORDS blocklist.
     *
     * @since 1.0.0
     *
     * @param  string  $outer  The outer query string.
     * @param  array<string, string>  $aliasMap  Alias → table map.
     * @param  string  $primaryTable  Fallback table for unqualified columns.
     *
     * @return array<string, array<int, string>>
     */
    protected function extractOrderByColumns( string $outer, array $aliasMap, string $primaryTable ): array
    {
        if ( 1 !== preg_match( '/\border\s+by\b(.*?)(?:\blimit\b|\bhaving\b|\bfor\s+update\b|\bfor\s+share\b|\boffset\b|\bfetch\b|$)/is', $outer, $matches ) ) {
            return [];
        }

        $clause = $matches[1];

        // Match a column reference followed by a non-identifier character
        // (or end of string). The trailing assertion rejects function
        // identifiers because they're followed by `(`.
        preg_match_all(
            '/(?:["`]?(?P<table>[a-z_][a-z0-9_]*)["`]?\.)?["`]?(?P<column>[a-z_][a-z0-9_]*)["`]?(?P<after>\s*[,()]|\s+(?:asc|desc|nulls|,)|\s*$)/i',
            $clause,
            $columnMatches,
            PREG_SET_ORDER,
        );

        $filtered = [];

        foreach ( $columnMatches as $match ) {
            $after = trim( $match['after'] );

            // A trailing `(` means the identifier was a function name.
            if ( str_starts_with( $after, '(' ) ) {
                continue;
            }

            $filtered[] = $match;
        }

        return $this->groupByTable( $filtered, $aliasMap, $primaryTable );
    }

    /**
     * Extracts (joined-table, joined-column) pairs from JOIN clauses.
     *
     * For `JOIN <table> [AS] <alias> ON <a>.<col1> = <b>.<col2>`, the
     * column that should be indexed is the one on the joined table —
     * NOT the column on the outer table (which is typically the PK and
     * already indexed). We use the alias map to resolve each side and
     * pick the column whose table matches the joined table.
     *
     * @since 1.0.0
     *
     * @param  string  $outer  The outer query string.
     * @param  array<string, string>  $aliasMap  Alias → table map.
     *
     * @return array<int, array{table: string, column: string}>
     */
    protected function extractJoinCandidates( string $outer, array $aliasMap ): array
    {
        preg_match_all(
            '/\bjoin\s+["`]?(?P<table>[a-z_][a-z0-9_]*)["`]?(?:\s+(?:as\s+)?["`]?(?P<alias>[a-z_][a-z0-9_]*)["`]?)?\s+on\s+'
            . '["`]?(?P<lhs_table>[a-z_][a-z0-9_]*)["`]?\.["`]?(?P<lhs_column>[a-z_][a-z0-9_]*)["`]?\s*=\s*'
            . '["`]?(?P<rhs_table>[a-z_][a-z0-9_]*)["`]?\.["`]?(?P<rhs_column>[a-z_][a-z0-9_]*)["`]?/i',
            $outer,
            $matches,
            PREG_SET_ORDER,
        );

        $candidates = [];

        foreach ( $matches as $match ) {
            $joinedTable = strtolower( $match['table'] );
            $joinedAlias = isset( $match['alias'] ) && '' !== $match['alias']
                ? strtolower( $match['alias'] )
                : $joinedTable;

            // Filter ON predicates "AS" / "ON" being captured as alias.
            if ( isset( self::SQL_KEYWORDS[ $joinedAlias ] ) ) {
                $joinedAlias = $joinedTable;
            }

            $lhsAlias  = strtolower( $match['lhs_table'] );
            $rhsAlias  = strtolower( $match['rhs_table'] );

            $candidate = null;

            if ( $lhsAlias === $joinedAlias || $lhsAlias === $joinedTable ) {
                $candidate = strtolower( $match['lhs_column'] );
            } elseif ( $rhsAlias === $joinedAlias || $rhsAlias === $joinedTable ) {
                $candidate = strtolower( $match['rhs_column'] );
            }

            if ( null === $candidate || isset( self::SQL_KEYWORDS[ $candidate ] ) ) {
                continue;
            }

            $candidates[] = [
                'table'  => $aliasMap[ $joinedAlias ] ?? $joinedTable,
                'column' => $candidate,
            ];
        }

        return $candidates;
    }

    /**
     * Groups column matches by their owning table via the alias map.
     *
     * Columns referenced with a `<qualifier>.<column>` form are
     * resolved through `$aliasMap`; unqualified columns are attributed
     * to `$primaryTable`. A column whose qualifier doesn't resolve is
     * dropped — the goal is no migration that fails to apply.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, string>>  $matches  Raw regex match groups (must contain `table` + `column`).
     * @param  array<string, string>  $aliasMap  Alias → table map.
     * @param  string  $primaryTable  Fallback for unqualified columns.
     *
     * @return array<string, array<int, string>>
     */
    protected function groupByTable( array $matches, array $aliasMap, string $primaryTable ): array
    {
        $byTable = [];

        foreach ( $matches as $match ) {
            $columnRaw    = isset( $match['column'] ) ? strtolower( trim( $match['column'] ) ) : '';
            $qualifierRaw = isset( $match['table'] ) ? strtolower( trim( $match['table'] ) ) : '';

            if ( '' === $columnRaw || isset( self::SQL_KEYWORDS[ $columnRaw ] ) ) {
                continue;
            }

            if ( '' === $qualifierRaw ) {
                $owningTable = $primaryTable;
            } elseif ( isset( $aliasMap[ $qualifierRaw ] ) ) {
                $owningTable = $aliasMap[ $qualifierRaw ];
            } else {
                // Qualifier didn't resolve — refuse to guess.
                continue;
            }

            if ( isset( $byTable[ $owningTable ] ) && in_array( $columnRaw, $byTable[ $owningTable ], true ) ) {
                continue;
            }

            $byTable[ $owningTable ][] = $columnRaw;
        }

        return $byTable;
    }

    /**
     * Merges two ordered column lists, preserving first-seen order.
     *
     * Used to combine WHERE-clause columns with ORDER BY-clause columns
     * into a composite index suggestion. The lead column should be
     * whatever the developer wrote first (typically the most selective).
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $first  Primary column list.
     * @param  array<int, string>  $second  Secondary column list to append.
     *
     * @return array<int, string>
     */
    protected function mergeOrdered( array $first, array $second ): array
    {
        $seen   = [];
        $result = [];

        foreach ( [ $first, $second ] as $list ) {
            foreach ( $list as $column ) {
                if ( isset( $seen[ $column ] ) ) {
                    continue;
                }

                $seen[ $column ] = true;
                $result[]        = $column;
            }
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
