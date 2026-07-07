<?php

/**
 * Performance insight agent.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Ai\Agents;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use JsonException;

/**
 * Explain why a specific query is slow and suggest optimizations.
 *
 * The agent only *suggests* indexes and rewrites — it never issues DDL or
 * mutates the database. Callers are expected to review the recommendations
 * and generate migrations by hand.
 *
 * ## Input
 *
 * ```
 * [
 *   'query'         => string,             // required, the SQL statement
 *   'explain'       => array|string|null,  // optional, EXPLAIN plan (JSON array or raw text)
 *   'schema'        => array|null,         // optional, list of relevant table schemas
 *   'time_ms'       => float|int|null,     // optional, observed duration in milliseconds
 *   'connection'    => string|null,        // optional, DB connection driver ('mysql', 'pgsql', …)
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   summary:         string,          // one-paragraph diagnosis
 *   bottlenecks:     string[],        // ranked list of the biggest issues
 *   suggested_indexes: [
 *     { table: string, columns: string[], rationale: string }
 *   ],
 *   rewrites:        [
 *     { original: string, suggested: string, rationale: string }
 *   ],
 *   caveats:         string[]         // uncertainties / missing context
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.1.0
 */
class PerformanceInsightAgent extends ArtisanPackAgent
{
    /**
     * {@inheritDoc}
     *
     * Query-insight outputs are point-in-time diagnoses of a specific
     * slow query. Once the operator adds the suggested index or rewrites
     * the query, the previous diagnosis is stale — a cache hit would
     * hand back suggestions naming bottlenecks that no longer exist.
     */
    public bool $cacheable = false;

    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'performance.query_insight';

    /**
     * {@inheritDoc}
     */
    public string $package = 'artisanpack-ui/performance';

    /**
     * {@inheritDoc}
     */
    public string $defaultModel = 'claude-sonnet-4-6';

    /**
     * {@inheritDoc}
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You analyze a single slow SQL query and explain why it is slow, then suggest optimizations.

Requirements:
- Base every claim on the provided query, EXPLAIN plan, and schema. Do NOT invent columns, indexes, tables, or row counts that are not present in the input.
- `summary` is a single paragraph (2-4 sentences) diagnosing the primary bottleneck.
- `bottlenecks` ranks issues by expected impact — biggest first (e.g. full table scan, filesort, index miss, N+1 pattern hint).
- `suggested_indexes` lists composite/single-column indexes that would eliminate the ranked bottlenecks. Each entry MUST name a real table from the schema and columns that exist on it. Include a one-sentence `rationale`.
- `rewrites` proposes query rewrites (JOIN order, subquery flattening, EXISTS-vs-IN, covering SELECT, etc.). Set `original` to the exact clause you replaced and `suggested` to the replacement. Include a one-sentence `rationale`.
- You suggest, you do NOT execute: NEVER emit `CREATE INDEX`, `ALTER TABLE`, or any DDL/DML — the frontend renders your suggestions and a human decides whether to write a migration.
- `caveats` captures anything a downstream reader should know — missing EXPLAIN plan, unknown row counts, ambiguous JOIN cardinality, etc. An empty array is fine when the input is complete.

Return a JSON object with keys: summary (string), bottlenecks (array of strings), suggested_indexes (array of objects), rewrites (array of objects), caveats (array of strings).
PROMPT;
    }

    /**
     * {@inheritDoc}
     */
    public function outputSchema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => [ 'summary', 'bottlenecks', 'suggested_indexes', 'rewrites', 'caveats' ],
            'properties'           => [
                'summary'           => [ 'type' => 'string' ],
                'bottlenecks'       => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
                'suggested_indexes' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => [ 'table', 'columns', 'rationale' ],
                        'properties'           => [
                            'table'     => [ 'type' => 'string' ],
                            'columns'   => [
                                'type'  => 'array',
                                'items' => [ 'type' => 'string' ],
                            ],
                            'rationale' => [ 'type' => 'string' ],
                        ],
                    ],
                ],
                'rewrites'          => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => [ 'original', 'suggested', 'rationale' ],
                        'properties'           => [
                            'original'  => [ 'type' => 'string' ],
                            'suggested' => [ 'type' => 'string' ],
                            'rationale' => [ 'type' => 'string' ],
                        ],
                    ],
                ],
                'caveats'           => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function execute( Credentials $credentials, string $model, string $instructions ): array
    {
        $normalized = $this->normalizeInput( $this->input() );

        $prompter = app( AgentPrompter::class );

        $result = $prompter->prompt(
            credentials: $credentials,
            model: $model,
            instructions: $instructions,
            message: $this->buildMessage( $normalized ),
            outputSchema: $this->outputSchema(),
        );

        return [
            'output'        => $this->validateOutput( $result['output'] ?? [] ),
            'input_tokens'  => (int) ( $result['input_tokens'] ?? 0 ),
            'output_tokens' => (int) ( $result['output_tokens'] ?? 0 ),
        ];
    }

    /**
     * Validate and shape-check the raw agent input.
     *
     * @since 1.1.0
     *
     * @param  mixed  $input  Raw agent input.
     *
     * @return array{ query: string, explain: array<mixed>|string|null, schema: array<mixed>|null, time_ms: float|null, connection: string|null }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with a `query` key.',
            );
        }

        $query = isset( $input['query'] ) && is_string( $input['query'] ) ? trim( $input['query'] ) : '';

        if ( '' === $query ) {
            throw FeatureError::forFeature( $this->featureKey, '`query` must be a non-empty string.' );
        }

        $explain = $input['explain'] ?? null;

        if ( null !== $explain && ! is_array( $explain ) && ! is_string( $explain ) ) {
            throw FeatureError::forFeature( $this->featureKey, '`explain` must be an array, string, or null.' );
        }

        $schema = $input['schema'] ?? null;

        if ( null !== $schema && ! is_array( $schema ) ) {
            throw FeatureError::forFeature( $this->featureKey, '`schema` must be an array or null.' );
        }

        $timeMs = null;

        if ( isset( $input['time_ms'] ) && ( is_int( $input['time_ms'] ) || is_float( $input['time_ms'] ) ) ) {
            $timeMs = (float) $input['time_ms'];
        }

        $connection = isset( $input['connection'] ) && is_string( $input['connection'] )
            ? trim( $input['connection'] )
            : '';

        return [
            'query'      => $query,
            'explain'    => $explain,
            'schema'     => $schema,
            'time_ms'    => $timeMs,
            'connection' => '' === $connection ? null : $connection,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 1.1.0
     *
     * @param  array{ query: string, explain: array<mixed>|string|null, schema: array<mixed>|null, time_ms: float|null, connection: string|null }  $normalized  Normalized input.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $normalized ): array
    {
        $parts = [];

        if ( null !== $normalized['connection'] ) {
            $parts[] = [ 'type' => 'text', 'text' => sprintf( 'Connection: %s', $normalized['connection'] ) ];
        }

        if ( null !== $normalized['time_ms'] ) {
            $parts[] = [ 'type' => 'text', 'text' => sprintf( 'Observed duration: %s ms', $normalized['time_ms'] ) ];
        }

        $parts[] = [ 'type' => 'text', 'text' => "Query:\n" . $normalized['query'] ];

        if ( null !== $normalized['explain'] ) {
            $parts[] = [
                'type' => 'text',
                'text' => "EXPLAIN plan:\n" . $this->encode( $normalized['explain'] ),
            ];
        }

        if ( null !== $normalized['schema'] ) {
            $parts[] = [
                'type' => 'text',
                'text' => "Schema:\n" . $this->encode( $normalized['schema'] ),
            ];
        }

        return $parts;
    }

    /**
     * Serialize a payload to a stable JSON string, or pass through a string.
     *
     * @since 1.1.0
     *
     * @param  array<mixed>|string  $value  Payload to serialize.
     *
     * @return string
     */
    protected function encode( array|string $value ): string
    {
        if ( is_string( $value ) ) {
            return $value;
        }

        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch ( JsonException $exception ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                sprintf( 'payload could not be serialized for the model: %s', $exception->getMessage() ),
                $exception,
            );
        }
    }

    /**
     * Enforce output invariants — coerce shapes, drop malformed rows.
     *
     * @since 1.1.0
     *
     * @param  array<string, mixed>  $output  Decoded model output.
     *
     * @return array{ summary: string, bottlenecks: array<int, string>, suggested_indexes: array<int, array{ table: string, columns: array<int, string>, rationale: string }>, rewrites: array<int, array{ original: string, suggested: string, rationale: string }>, caveats: array<int, string> }
     */
    protected function validateOutput( array $output ): array
    {
        return [
            'summary'           => isset( $output['summary'] ) ? trim( (string) $output['summary'] ) : '',
            'bottlenecks'       => $this->stringList( $output['bottlenecks'] ?? [] ),
            'suggested_indexes' => $this->indexList( $output['suggested_indexes'] ?? [] ),
            'rewrites'          => $this->rewriteList( $output['rewrites'] ?? [] ),
            'caveats'           => $this->stringList( $output['caveats'] ?? [] ),
        ];
    }

    /**
     * Filter a raw list into a clean array of non-empty strings.
     *
     * @since 1.1.0
     *
     * @param  mixed  $raw  Raw list from the model.
     *
     * @return array<int, string>
     */
    protected function stringList( mixed $raw ): array
    {
        if ( ! is_array( $raw ) ) {
            return [];
        }

        $out = [];

        foreach ( $raw as $value ) {
            if ( is_string( $value ) && '' !== trim( $value ) ) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * Shape-check suggested-index rows.
     *
     * @since 1.1.0
     *
     * @param  mixed  $raw  Raw index list.
     *
     * @return array<int, array{ table: string, columns: array<int, string>, rationale: string }>
     */
    protected function indexList( mixed $raw ): array
    {
        if ( ! is_array( $raw ) ) {
            return [];
        }

        $out = [];

        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $table   = isset( $row['table'] ) ? trim( (string) $row['table'] ) : '';
            $columns = $this->stringList( $row['columns'] ?? [] );

            if ( '' === $table || [] === $columns ) {
                continue;
            }

            $out[] = [
                'table'     => $table,
                'columns'   => $columns,
                'rationale' => isset( $row['rationale'] ) ? trim( (string) $row['rationale'] ) : '',
            ];
        }

        return $out;
    }

    /**
     * Shape-check rewrite rows.
     *
     * @since 1.1.0
     *
     * @param  mixed  $raw  Raw rewrite list.
     *
     * @return array<int, array{ original: string, suggested: string, rationale: string }>
     */
    protected function rewriteList( mixed $raw ): array
    {
        if ( ! is_array( $raw ) ) {
            return [];
        }

        $out = [];

        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $original  = isset( $row['original'] ) ? trim( (string) $row['original'] ) : '';
            $suggested = isset( $row['suggested'] ) ? trim( (string) $row['suggested'] ) : '';

            if ( '' === $original || '' === $suggested ) {
                continue;
            }

            $out[] = [
                'original'  => $original,
                'suggested' => $suggested,
                'rationale' => isset( $row['rationale'] ) ? trim( (string) $row['rationale'] ) : '',
            ];
        }

        return $out;
    }
}
