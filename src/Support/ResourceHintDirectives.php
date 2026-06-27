<?php

/**
 * Resource hint directive rendering helpers.
 *
 * Backs the Phase 3 resource-hint Blade directives (`@preconnect`,
 * `@dnsPrefetch`, `@preload`, `@prefetch`). Each helper returns the
 * `<link>` element for a single hint without touching the
 * `ResourceHintInjector` singleton, so per-page directives stay inline
 * at their call site instead of getting collected into the global
 * registry that the `<x-perf-resource-hints>` component drains.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Support;

use ArtisanPackUI\Performance\Output\ResourceHint;
use InvalidArgumentException;

/**
 * Resource hint directive rendering helpers.
 *
 *
 * @since      1.0.0
 */
final class ResourceHintDirectives
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
     * Renders a preconnect `<link>` element.
     *
     * @since 1.0.0
     *
     * @param  string  $href  Origin to preconnect to.
     * @param  string|null  $crossorigin  Optional crossorigin attribute.
     */
    public static function preconnect( string $href, ?string $crossorigin = null ): string
    {
        return self::tryRender( 'preconnect', $href, crossorigin: $crossorigin );
    }

    /**
     * Renders a dns-prefetch `<link>` element.
     *
     * @since 1.0.0
     *
     * @param  string  $href  Origin whose DNS should be resolved early.
     */
    public static function dnsPrefetch( string $href ): string
    {
        return self::tryRender( 'dns-prefetch', $href );
    }

    /**
     * Renders a preload `<link>` element.
     *
     * @since 1.0.0
     *
     * @param  string  $href  Resource URL.
     * @param  string|null  $as  Preload type (`font`, `script`, `style`, …).
     * @param  string|null  $type  MIME type.
     * @param  string|null  $crossorigin  Crossorigin attribute (required for fonts).
     * @param  string|null  $fetchpriority  Optional fetchpriority hint.
     */
    public static function preload(
        string $href,
        ?string $as = null,
        ?string $type = null,
        ?string $crossorigin = null,
        ?string $fetchpriority = null,
    ): string {
        return self::tryRender( 'preload', $href, $as, $type, $crossorigin, fetchpriority: $fetchpriority );
    }

    /**
     * Renders a prefetch `<link>` element.
     *
     * @since 1.0.0
     *
     * @param  string  $href  Resource URL.
     * @param  string|null  $as  Optional preload-style `as` value.
     */
    public static function prefetch( string $href, ?string $as = null ): string
    {
        return self::tryRender( 'prefetch', $href, $as );
    }

    /**
     * Attempts to render a hint, returning an empty string when invalid.
     *
     * Failing soft mirrors the directive contract — a bad `href` should
     * skip the hint rather than blow up the Blade template.
     *
     * @since 1.0.0
     *
     * @param  string  $rel  Hint relation.
     * @param  string  $href  Target URL/origin.
     * @param  string|null  $as  Optional `as` value.
     * @param  string|null  $type  Optional MIME type.
     * @param  string|null  $crossorigin  Optional crossorigin attribute.
     * @param  string|null  $media  Optional media query.
     * @param  string|null  $fetchpriority  Optional fetchpriority hint.
     * @param  string|null  $referrerpolicy  Optional referrerpolicy hint.
     */
    protected static function tryRender(
        string $rel,
        string $href,
        ?string $as = null,
        ?string $type = null,
        ?string $crossorigin = null,
        ?string $media = null,
        ?string $fetchpriority = null,
        ?string $referrerpolicy = null,
    ): string {
        try {
            return (new ResourceHint(
                rel: $rel,
                href: $href,
                as: $as,
                type: $type,
                crossorigin: $crossorigin,
                media: $media,
                fetchpriority: $fetchpriority,
                referrerpolicy: $referrerpolicy,
            ))->toLinkElement();
        } catch ( InvalidArgumentException ) {
            return '';
        }
    }
}
