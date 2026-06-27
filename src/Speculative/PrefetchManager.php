<?php

/**
 * Prefetch URL manager.
 *
 * Holds the application's runtime-registered prefetch URLs. Callers
 * register URLs (typically from analytics-derived next-page predictions or
 * controllers) and the manager exposes them in priority order to the
 * `SpeculativeRulesGenerator`. Pattern matching is delegated to
 * `UrlPatternMatcher` so clears can target globs.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Speculative;

/**
 * Prefetch URL manager.
 *
 *
 * @since      1.0.0
 */
class PrefetchManager
{
    /**
     * Priority weights for the supported priority levels.
     *
     * Lower wins; the manager exposes URLs sorted by ascending weight so
     * higher-priority entries appear first in the rules document.
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
     * Registered URLs keyed by URL.
     *
     * @since 1.0.0
     *
     * @var array<string, array{url: string, priority: string, weight: int, order: int}>
     */
    protected array $entries = [];

    /**
     * Monotonic insertion counter used as a tiebreaker for stable sorts.
     *
     * @since 1.0.0
     */
    protected int $cursor = 0;

    /**
     * Registers a URL or a list of URLs for prefetching.
     *
     * Duplicate registrations replace the prior priority for the URL
     * (last-write-wins). Empty strings are ignored.
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
     * The pattern accepts `*` (single segment) and `**` (any) wildcards.
     * An exact string removes a single entry; a glob removes every entry
     * whose URL matches.
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
     * Returns every registered URL ordered by priority then insertion order.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        $entries = array_values( $this->entries );

        usort(
            $entries,
            static fn ( array $left, array $right ): int => $left['weight'] <=> $right['weight']
                ?: $left['order'] <=> $right['order'],
        );

        return array_map( static fn ( array $entry ): string => $entry['url'], $entries );
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
     * Reports whether any URLs are registered.
     *
     * @since 1.0.0
     */
    public function hasUrls(): bool
    {
        return ! empty( $this->entries );
    }

    /**
     * Returns the count of registered URLs.
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

        return isset( self::PRIORITY_WEIGHTS[ $normalized ])
            ? $normalized
            : self::DEFAULT_PRIORITY;
    }
}
