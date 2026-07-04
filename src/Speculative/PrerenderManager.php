<?php

/**
 * Prerender URL manager.
 *
 * Counterpart to `PrefetchManager` for the costlier prerender pathway.
 * Tracks a hard concurrency limit (defaulting to the package config
 * `speculative_loading.prerender.limit`) so analytics-driven prerender
 * suggestions can't accidentally exhaust client resources. URLs that
 * exceed the limit are dropped at `all()` time (after priority sorting),
 * not at registration, so a later `clear()` can reopen slots without the
 * caller needing to re-register.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Speculative;

/**
 * Prerender URL manager.
 *
 *
 * @since      1.0.0
 */
class PrerenderManager
{
    /**
     * Priority weights for the supported priority levels.
     *
     * @since 1.0.0
     *
     * @var array<string, int>
     */
    public const PRIORITY_WEIGHTS = [
        'high'   => 0,
        'medium' => 10,
        'low'    => 20,
    ];

    /**
     * Default priority assigned when none is supplied.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_PRIORITY = 'medium';

    /**
     * Fallback limit when neither the constructor nor config supply one.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const DEFAULT_LIMIT = 2;

    /**
     * Registered URLs keyed by URL.
     *
     * @since 1.0.0
     *
     * @var array<string, array{url: string, priority: string, weight: int, order: int}>
     */
    protected array $entries = [];

    /**
     * Monotonic insertion counter used as a stable sort tiebreaker.
     *
     * @since 1.0.0
     */
    protected int $cursor = 0;

    /**
     * Concurrency limit override, when supplied to the constructor.
     *
     * @since 1.0.0
     */
    protected ?int $limitOverride;

    /**
     * Creates a new prerender manager.
     *
     * @since 1.0.0
     *
     * @param  int|null  $limit  Optional concurrency limit override.
     */
    public function __construct( ?int $limit = null )
    {
        $this->limitOverride = $limit;
    }

    /**
     * Registers a URL or a list of URLs for prerendering.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|string  $urls  URL or list of URLs.
     * @param  string  $priority  Priority level (`high`, `medium`, `low`).
     *
     * @return $this
     */
    public function register( string|array $urls, string $priority = self::DEFAULT_PRIORITY ): self
    {
        $normalizedPriority = $this->normalizePriority( $priority );
        $weight             = self::PRIORITY_WEIGHTS[ $normalizedPriority ];

        foreach ( (array) $urls as $url ) {
            $url = is_string( $url ) ? trim( $url ) : '';

            if ( '' === $url ) {
                continue;
            }

            $this->entries[ $url ] = [
                'url'      => $url,
                'priority' => $normalizedPriority,
                'weight'   => $weight,
                'order'    => $this->cursor++,
            ];
        }

        return $this;
    }

    /**
     * Removes URLs matching the given pattern.
     *
     * @since 1.0.0
     *
     * @param  string  $pattern  URL or glob pattern.
     *
     * @return $this
     */
    public function clear( string $pattern ): self
    {
        foreach ( array_keys( $this->entries ) as $url ) {
            if ( UrlPatternMatcher::matches( $url, $pattern ) ) {
                unset( $this->entries[ $url ] );
            }
        }

        return $this;
    }

    /**
     * Removes every registered URL.
     *
     * @since 1.0.0
     *
     * @return $this
     */
    public function flush(): self
    {
        $this->entries = [];

        return $this;
    }

    /**
     * Returns every URL that fits within the configured limit.
     *
     * URLs are sorted by priority weight then insertion order, then
     * truncated to `limit()`. Limit semantics: a positive limit caps
     * the result; `0` is an explicit "disable prerendering" (returns an
     * empty list — matching operator intent when setting the config
     * value to zero); a negative limit disables the cap entirely for
     * tests and edge cases.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        $limit = $this->limit();

        if ( 0 === $limit ) {
            return [];
        }

        $entries = array_values( $this->entries );

        usort(
            $entries,
            static fn ( array $left, array $right ): int => $left['weight'] <=> $right['weight']
                ?: $left['order'] <=> $right['order'],
        );

        if ( $limit > 0 ) {
            $entries = array_slice( $entries, 0, $limit );
        }

        return array_map( static fn ( array $entry ): string => $entry['url'], $entries );
    }

    /**
     * Returns the active concurrency limit.
     *
     * Resolution order: constructor override → package config
     * (`speculative_loading.prerender.limit`) → `DEFAULT_LIMIT`. A non-int
     * config value falls through to the default rather than crashing.
     *
     * @since 1.0.0
     */
    public function limit(): int
    {
        if ( null !== $this->limitOverride ) {
            return $this->limitOverride;
        }

        $configValue = config( 'artisanpack.performance.speculative_loading.prerender.limit' );

        if ( is_int( $configValue ) ) {
            return $configValue;
        }

        if ( is_numeric( $configValue ) ) {
            return (int) $configValue;
        }

        return self::DEFAULT_LIMIT;
    }

    /**
     * Returns the registered priority for a URL, if any.
     *
     * @since 1.0.0
     *
     * @param  string  $url  The URL to inspect.
     */
    public function priorityFor( string $url ): ?string
    {
        return $this->entries[ $url ]['priority'] ?? null;
    }

    /**
     * Reports whether any URLs are registered (pre-limit).
     *
     * @since 1.0.0
     */
    public function hasUrls(): bool
    {
        return ! empty( $this->entries );
    }

    /**
     * Returns the count of registered URLs (pre-limit).
     *
     * @since 1.0.0
     */
    public function count(): int
    {
        return count( $this->entries );
    }

    /**
     * Normalizes a priority value, defaulting on unknowns.
     *
     * @since 1.0.0
     *
     * @param  string  $priority  Caller-supplied priority.
     */
    protected function normalizePriority( string $priority ): string
    {
        $normalized = strtolower( trim( $priority ) );

        return isset( self::PRIORITY_WEIGHTS[ $normalized ] )
            ? $normalized
            : self::DEFAULT_PRIORITY;
    }
}
