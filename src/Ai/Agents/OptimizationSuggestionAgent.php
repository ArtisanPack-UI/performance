<?php

/**
 * Optimization suggestion agent.
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
 * Suggest where to focus optimization work given aggregate performance
 * metrics over a date range.
 *
 * Distinct from {@see PerformanceInsightAgent}: that agent works on a single
 * slow query. This one looks at the *portfolio* — Core Web Vitals, route
 * timings, cache hit rates, slow-query counts — and decides which fires
 * are worth putting out first.
 *
 * ## Input
 *
 * ```
 * [
 *   'range' => [ 'from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD' ], // required
 *   'metrics' => [                                                // required
 *     [
 *       'metric'    => string,   // e.g. 'lcp', 'inp', 'query_time_ms'
 *       'route'     => string,   // e.g. 'GET /articles/{slug}'
 *       'p50'       => float,
 *       'p75'       => float,
 *       'p90'       => float,
 *       'p99'       => float,
 *       'samples'   => int,
 *     ],
 *     …
 *   ],
 *   'context' => [                                                // optional
 *     'traffic_mix'      => string,  // e.g. '65% mobile, 35% desktop'
 *     'recent_changes'   => string,  // e.g. 'switched to Vite last week'
 *     'business_priority'=> string,  // e.g. 'checkout > blog'
 *   ],
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   summary:         string,                              // portfolio-level diagnosis
 *   focus_areas: [
 *     {
 *       title:       string,
 *       routes:      string[],
 *       impact:      'high' | 'medium' | 'low',
 *       effort:      'high' | 'medium' | 'low',
 *       rationale:   string,
 *       actions:     string[],                            // concrete follow-ups
 *     }
 *   ],
 *   quick_wins:      string[],                            // low-effort/high-return items
 *   caveats:         string[]                             // gaps in the data
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.1.0
 */
class OptimizationSuggestionAgent extends ArtisanPackAgent
{

    /**
     * Impact and effort levels the model must choose from.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected const LEVELS = [ 'high', 'medium', 'low' ];

    /**
     * {@inheritDoc}
     *
     * Portfolio-level suggestions are point-in-time snapshots of the
     * current metrics window; once the operator ships one of the focus
     * areas, the previous suggestion is stale.
     */
    public bool $cacheable = false;

    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'performance.optimization_suggestion';

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
You review aggregate application performance metrics and recommend where the team should focus optimization work.

Requirements:
- Base every recommendation on the provided metrics and context. Do NOT invent routes, thresholds, or numbers.
- `summary` is a portfolio-level diagnosis in 2-4 sentences — what stands out across the whole date range.
- `focus_areas` groups related regressions into 1-5 focused work streams, most-impactful first. Each area MUST cite specific `routes` from the input metrics.
- Set `impact` and `effort` to one of `high`, `medium`, or `low`. Impact is user-facing (SLO risk, revenue-adjacent). Effort is engineering cost.
- `actions` lists 2-5 concrete follow-ups for that focus area — not vague advice. Prefer "add a covering index on articles(slug, published_at)" over "improve DB performance".
- `quick_wins` surfaces low-effort/high-return items that stand alone from the focus areas (e.g. bump PHP OPcache, enable Brotli, deploy resource hints for a specific route).
- Bias toward the `business_priority` when provided — if two focus areas would help equally, pick the one closer to the priority.
- `caveats` captures what would sharpen the picture (missing device breakdown, no sample counts on some rows, single-day range, etc.). An empty array is fine when the input is clean.

Return a JSON object with keys: summary (string), focus_areas (array of objects), quick_wins (array of strings), caveats (array of strings).
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
            'required'             => [ 'summary', 'focus_areas', 'quick_wins', 'caveats' ],
            'properties'           => [
                'summary'     => [ 'type' => 'string' ],
                'focus_areas' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => [ 'title', 'routes', 'impact', 'effort', 'rationale', 'actions' ],
                        'properties'           => [
                            'title'     => [ 'type' => 'string' ],
                            'routes'    => [
                                'type'  => 'array',
                                'items' => [ 'type' => 'string' ],
                            ],
                            'impact'    => [ 'type' => 'string', 'enum' => self::LEVELS ],
                            'effort'    => [ 'type' => 'string', 'enum' => self::LEVELS ],
                            'rationale' => [ 'type' => 'string' ],
                            'actions'   => [
                                'type'  => 'array',
                                'items' => [ 'type' => 'string' ],
                            ],
                        ],
                    ],
                ],
                'quick_wins'  => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
                'caveats'     => [
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

        // Empty metrics list => short-circuit. There is nothing for the
        // model to reason about and we would only invite hallucinations.
        if ( [] === $normalized['metrics'] ) {
            return [
                'output'        => [
                    'summary'     => 'No metrics to analyze in the requested range.',
                    'focus_areas' => [],
                    'quick_wins'  => [],
                    'caveats'     => [ 'metrics list was empty' ],
                ],
                'input_tokens'  => 0,
                'output_tokens' => 0,
            ];
        }

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
     * @return array{ range: array{ from: string, to: string }, metrics: array<int, array<string, mixed>>, context: array<string, string> }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with `range` and `metrics` keys.',
            );
        }

        if ( ! isset( $input['range'] ) || ! is_array( $input['range'] ) ) {
            throw FeatureError::forFeature( $this->featureKey, '`range` must be an array with `from` and `to` keys.' );
        }

        $from = isset( $input['range']['from'] ) && is_string( $input['range']['from'] )
            ? trim( $input['range']['from'] )
            : '';
        $to   = isset( $input['range']['to'] ) && is_string( $input['range']['to'] )
            ? trim( $input['range']['to'] )
            : '';

        if ( '' === $from || '' === $to ) {
            throw FeatureError::forFeature( $this->featureKey, '`range.from` and `range.to` must be non-empty strings.' );
        }

        if ( ! isset( $input['metrics'] ) || ! is_array( $input['metrics'] ) ) {
            throw FeatureError::forFeature( $this->featureKey, '`metrics` must be an array.' );
        }

        $context = [];

        if ( isset( $input['context'] ) && is_array( $input['context'] ) ) {
            foreach ( $input['context'] as $key => $value ) {
                if ( is_string( $key ) && is_string( $value ) && '' !== trim( $value ) ) {
                    $context[ $key ] = trim( $value );
                }
            }
        }

        return [
            'range'   => [
                'from' => $from,
                'to'   => $to,
            ],
            'metrics' => array_values( $input['metrics'] ),
            'context' => $context,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 1.1.0
     *
     * @param  array{ range: array{ from: string, to: string }, metrics: array<int, array<string, mixed>>, context: array<string, string> }  $normalized  Normalized input.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $normalized ): array
    {
        $parts = [
            [
                'type' => 'text',
                'text' => sprintf( 'Date range: %s to %s', $normalized['range']['from'], $normalized['range']['to'] ),
            ],
        ];

        if ( [] !== $normalized['context'] ) {
            $parts[] = [
                'type' => 'text',
                'text' => "Context:\n" . $this->encode( $normalized['context'] ),
            ];
        }

        $parts[] = [
            'type' => 'text',
            'text' => "Metrics:\n" . $this->encode( $normalized['metrics'] ),
        ];

        return $parts;
    }

    /**
     * Serialize a payload to a stable JSON string.
     *
     * @since 1.1.0
     *
     * @param  array<mixed>  $value  Payload to serialize.
     *
     * @return string
     */
    protected function encode( array $value ): string
    {
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
     * @return array{ summary: string, focus_areas: array<int, array{ title: string, routes: array<int, string>, impact: string, effort: string, rationale: string, actions: array<int, string> }>, quick_wins: array<int, string>, caveats: array<int, string> }
     */
    protected function validateOutput( array $output ): array
    {
        return [
            'summary'     => isset( $output['summary'] ) ? trim( (string) $output['summary'] ) : '',
            'focus_areas' => $this->focusAreaList( $output['focus_areas'] ?? [] ),
            'quick_wins'  => $this->stringList( $output['quick_wins'] ?? [] ),
            'caveats'     => $this->stringList( $output['caveats'] ?? [] ),
        ];
    }

    /**
     * Shape-check focus-area rows.
     *
     * @since 1.1.0
     *
     * @param  mixed  $raw  Raw focus-area list.
     *
     * @return array<int, array{ title: string, routes: array<int, string>, impact: string, effort: string, rationale: string, actions: array<int, string> }>
     */
    protected function focusAreaList( mixed $raw ): array
    {
        if ( ! is_array( $raw ) ) {
            return [];
        }

        $out = [];

        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $title = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';

            if ( '' === $title ) {
                continue;
            }

            $out[] = [
                'title'     => $title,
                'routes'    => $this->stringList( $row['routes'] ?? [] ),
                'impact'    => $this->clampLevel( $row['impact'] ?? '' ),
                'effort'    => $this->clampLevel( $row['effort'] ?? '' ),
                'rationale' => isset( $row['rationale'] ) ? trim( (string) $row['rationale'] ) : '',
                'actions'   => $this->stringList( $row['actions'] ?? [] ),
            ];
        }

        return $out;
    }

    /**
     * Coerce an impact/effort value into the allowed vocabulary.
     *
     * Falls back to `medium` when the model returns anything unexpected —
     * safer than leaking the raw value into the UI, and matches how a
     * human triager would treat "unknown severity".
     *
     * @since 1.1.0
     *
     * @param  mixed  $value  Raw level from the model.
     *
     * @return string
     */
    protected function clampLevel( mixed $value ): string
    {
        if ( is_string( $value ) ) {
            $lower = strtolower( trim( $value ) );

            if ( in_array( $lower, self::LEVELS, true ) ) {
                return $lower;
            }
        }

        return 'medium';
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
}
