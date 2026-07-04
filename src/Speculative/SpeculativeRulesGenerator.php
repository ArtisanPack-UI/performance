<?php

/**
 * Speculative rules generator.
 *
 * Produces a JSON document conforming to the Speculation Rules API spec
 * (https://wicg.github.io/nav-speculation/speculation-rules.html). Callers
 * supply the per-rule configuration (eagerness, include/exclude patterns,
 * an explicit URL list, optional concurrency limit) and receive a JSON
 * string ready to embed inside `<script type="speculationrules">`.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Speculative;

/**
 * Speculative rules generator.
 *
 *
 * @since      1.0.0
 */
class SpeculativeRulesGenerator
{
    /**
     * Eagerness levels permitted by the Speculation Rules API.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SUPPORTED_EAGERNESS = ['immediate', 'eager', 'moderate', 'conservative'];

    /**
     * Default eagerness when none is supplied.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_EAGERNESS = 'moderate';

    /**
     * Generates a speculation rules JSON string from the given configuration.
     *
     * Configuration may include `prefetch` and `prerender` keys. Each block
     * accepts `eagerness`, `include_patterns`, `exclude_patterns`, `urls`,
     * `selector`, and (for prerender) `limit`. Missing blocks are omitted
     * from the output so the returned JSON only contains the rules that were
     * actually requested.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $config  Configuration map.
     *
     * @return string JSON document.
     */
    public function generate( array $config ): string
    {
        $rules = [];

        if ( isset( $config['prefetch'] ) && is_array( $config['prefetch'] ) ) {
            $prefetch = $this->buildRule( 'prefetch', $config['prefetch'] );

            if ( ! empty( $prefetch ) ) {
                $rules['prefetch'] = $prefetch;
            }
        }

        if ( isset( $config['prerender'] ) && is_array( $config['prerender'] ) ) {
            $prerender = $this->buildRule( 'prerender', $config['prerender'] );

            if ( ! empty( $prerender ) ) {
                $rules['prerender'] = $prerender;
            }
        }

        if ( empty( $rules ) ) {
            return '{}';
        }

        // JSON_HEX_TAG / AMP / APOS / QUOT are mandatory: the document is
        // emitted inside `<script type="speculationrules">`, so a registered
        // URL containing `</script>` would close the tag and turn the
        // following bytes into page JS. Hex-encoding `<`, `>`, `&`, `'`, `"`
        // closes that sink without changing how browsers parse the rules.
        $json = json_encode(
            $rules,
            JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT,
        );

        // Invalid UTF-8 in a pattern/URL makes `json_encode()` return
        // `false`. Coercing that to '{}' here means a downstream caller
        // (the directive) emits no `<script>` tag at all instead of an
        // unterminated one.
        return false === $json ? '{}' : $json;
    }

    /**
     * Generates the rules document from package configuration.
     *
     * Reads the `artisanpack.performance.speculative_loading` config key.
     * The same configuration shape as `generate()` is expected.
     *
     * @since 1.0.0
     *
     * @return string JSON document.
     */
    public function generateFromConfig(): string
    {
        $config = (array) config( 'artisanpack.performance.speculative_loading', [] );

        return $this->generate( [
            'prefetch'  => $config['prefetch'] ?? null,
            'prerender' => $config['prerender'] ?? null,
        ] );
    }

    /**
     * Builds the per-rule descriptor list for a single action.
     *
     * Each call emits up to two entries: a `urls`-list rule when the caller
     * supplied explicit URLs and an `href_matches` rule when patterns were
     * supplied. When neither URLs nor include/exclude patterns are present
     * the rule falls back to the action's default selector
     * (`a[data-prefetch]` / `a[data-prerender]`) so the speculation-rules
     * block still wires up data-attribute-marked links. The `limit` option
     * truncates the URLs list.
     *
     * @since 1.0.0
     *
     * @param  string  $action  Either `prefetch` or `prerender`.
     * @param  array<string,mixed>  $options  Caller-supplied options.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildRule( string $action, array $options ): array
    {
        $eagerness = $this->resolveEagerness( $options['eagerness'] ?? null );
        $include   = $this->normalizePatterns( $options['include_patterns'] ?? [] );
        $exclude   = $this->normalizePatterns( $options['exclude_patterns'] ?? [] );
        $urls      = $this->normalizePatterns( $options['urls'] ?? [] );
        $limit     = isset( $options['limit'] ) ? (int) $options['limit'] : null;
        $selector  = isset( $options['selector'] )
            ? (string) $options['selector']
            : $this->defaultSelector( $action );

        $rules = [];

        if ( ! empty( $urls ) ) {
            if ( null !== $limit && $limit > 0 ) {
                $urls = array_slice( $urls, 0, $limit );
            }

            $rules[] = [
                'urls'      => array_values( $urls ),
                'eagerness' => $eagerness,
            ];
        }

        if ( ! empty( $include ) || ! empty( $exclude ) ) {
            $where = $this->buildHrefMatchesWhere( $include, $exclude );

            if ( null !== $where ) {
                $rules[] = [
                    'source'    => 'document',
                    'where'     => $where,
                    'eagerness' => $eagerness,
                ];
            }
        }

        if ( empty( $rules ) ) {
            $where = ['selector_matches' => $selector];

            if ( ! empty( $exclude ) ) {
                $where = [
                    'and' => [
                        ['selector_matches' => $selector],
                        $this->notOperator( $exclude ),
                    ],
                ];
            }

            $rules[] = [
                'source'    => 'document',
                'where'     => $where,
                'eagerness' => $eagerness,
            ];
        }

        return $rules;
    }

    /**
     * Resolves a caller-supplied eagerness value, defaulting on unknowns.
     *
     * @since 1.0.0
     *
     * @param  mixed  $eagerness  Caller-supplied eagerness.
     */
    protected function resolveEagerness( mixed $eagerness ): string
    {
        if ( ! is_string( $eagerness ) || '' === $eagerness ) {
            return self::DEFAULT_EAGERNESS;
        }

        $normalized = strtolower( $eagerness );

        return in_array( $normalized, self::SUPPORTED_EAGERNESS, true )
            ? $normalized
            : self::DEFAULT_EAGERNESS;
    }

    /**
     * Normalizes a pattern list, dropping non-string entries and empties.
     *
     * @since 1.0.0
     *
     * @param  mixed  $patterns  Caller-supplied pattern descriptor.
     *
     * @return array<int, string>
     */
    protected function normalizePatterns( mixed $patterns ): array
    {
        if ( ! is_array( $patterns ) ) {
            return [];
        }

        $result = [];

        foreach ( $patterns as $pattern ) {
            if ( ! is_string( $pattern ) || '' === $pattern ) {
                continue;
            }

            $result[] = $pattern;
        }

        return array_values( array_unique( $result ) );
    }

    /**
     * Builds the `where` clause for an include/exclude pattern pair.
     *
     * When only excludes are given the include defaults to `/*` so the rule
     * fires for every navigation that does not match an exclude pattern.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $include  Include patterns.
     * @param  array<int, string>  $exclude  Exclude patterns.
     *
     * @return array<string, mixed>|null
     */
    protected function buildHrefMatchesWhere( array $include, array $exclude ): ?array
    {
        if ( empty( $include ) && empty( $exclude ) ) {
            return null;
        }

        $effectiveInclude = empty( $include ) ? ['/*'] : $include;

        $includeClause = 1 === count( $effectiveInclude )
            ? ['href_matches' => $effectiveInclude[0]]
            : ['or' => array_map(
                static fn ( string $pattern ): array => ['href_matches' => $pattern],
                $effectiveInclude,
            )];

        if ( empty( $exclude ) ) {
            return $includeClause;
        }

        return [
            'and' => [
                $includeClause,
                $this->notOperator( $exclude ),
            ],
        ];
    }

    /**
     * Builds a `not` clause for a list of exclude patterns.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $exclude  Exclude patterns.
     *
     * @return array<string, mixed>
     */
    protected function notOperator( array $exclude ): array
    {
        $inner = 1 === count( $exclude )
            ? ['href_matches' => $exclude[0]]
            : ['or' => array_map(
                static fn ( string $pattern ): array => ['href_matches' => $pattern],
                $exclude,
            )];

        return ['not' => $inner];
    }

    /**
     * Returns the default link selector used when only a selector rule fires.
     *
     * @since 1.0.0
     *
     * @param  string  $action  Either `prefetch` or `prerender`.
     *
     * @return string CSS selector.
     */
    protected function defaultSelector( string $action ): string
    {
        return 'prerender' === $action
            ? 'a[data-prerender]:not([data-no-speculate])'
            : 'a[data-prefetch]:not([data-no-speculate])';
    }
}
