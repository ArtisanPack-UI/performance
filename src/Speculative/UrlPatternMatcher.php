<?php

/**
 * URL pattern matcher.
 *
 * Shared utility for matching path-style patterns used throughout the
 * speculative-loading feature: include/exclude lists for the speculation
 * rules generator and the prefetch/prerender managers. Supports glob-style
 * wildcards (`*` matches a single path segment, `**` matches any number of
 * segments) and exact string matches.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Speculative;

/**
 * URL pattern matcher.
 *
 *
 * @since      1.0.0
 */
final class UrlPatternMatcher
{
    /**
     * Disallow instantiation; the class is a pure static helper.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }

    /**
     * Reports whether the given URL matches any pattern in the list.
     *
     * Empty pattern lists short-circuit to `false` so callers can treat
     * an unset include/exclude list as "no match" without an extra guard.
     *
     * @since 1.0.0
     *
     * @param  string  $url  Caller-supplied URL or path.
     * @param  array<int, string>  $patterns  Pattern list.
     */
    public static function matchesAny( string $url, array $patterns ): bool
    {
        foreach ( $patterns as $pattern ) {
            if ( self::matches( $url, (string) $pattern ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether the given URL matches a single pattern.
     *
     * Patterns may use `*` to match any character except `/` and `**`
     * (or a trailing `/*`) to match across path segments. Exact matches
     * (no wildcards) compare the full string.
     *
     * @since 1.0.0
     *
     * @param  string  $url  Caller-supplied URL or path.
     * @param  string  $pattern  Pattern to test against.
     */
    public static function matches( string $url, string $pattern ): bool
    {
        if ( '' === $pattern ) {
            return false;
        }

        if ( ! str_contains( $pattern, '*' ) ) {
            return $url === $pattern;
        }

        $regex = self::compilePattern( $pattern );

        return 1 === preg_match( $regex, $url );
    }

    /**
     * Compiles a glob-style pattern into an anchored regular expression.
     *
     * Both `*` and `**` are treated as matching any sequence of characters
     * (including `/`). The single-vs-double form is retained for visual
     * clarity at the call site, but the matching semantics are intentionally
     * permissive: package config uses patterns like `*.pdf` to match any
     * URL that ends in `.pdf` regardless of depth, and `/admin/*` is the
     * natural way to say "anything under `/admin/`".
     *
     * @since 1.0.0
     *
     * @param  string  $pattern  Source pattern.
     *
     * @return string PCRE-ready expression.
     */
    private static function compilePattern( string $pattern ): string
    {
        $placeholder = "\x01";

        $tokenized = str_replace( ['**', '*'], $placeholder, $pattern );

        $quoted = preg_quote( $tokenized, '#' );

        $compiled = str_replace( $placeholder, '.*', $quoted );

        return '#^' . $compiled . '$#';
    }
}
