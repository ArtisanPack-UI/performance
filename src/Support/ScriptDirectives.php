<?php

/**
 * Script directive rendering helpers.
 *
 * Backs the Phase 3 Blade directives (`@deferScript`, `@asyncScript`,
 * `@moduleScript`, `@conditionalScript`) and the per-call rendering used
 * by the `<x-perf-script>` / `<x-perf-conditional-script>` components.
 * Builds a transient `ScriptRegistration` so directives and components
 * route through the same strategy registry the `ScriptManager` exposes —
 * applications that swap out a strategy via `registerStrategy()` see
 * their override applied uniformly across the registrar, the directives,
 * and the components.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Support;

use ArtisanPackUI\Performance\JavaScript\ScriptManager;
use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;
use Throwable;

/**
 * Script directive rendering helpers.
 *
 *
 * @since      1.0.0
 */
final class ScriptDirectives
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
     * Renders a deferred script tag.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Script URL.
     * @param  array<string, string>  $attributes  Additional `<script>` attributes.
     */
    public static function defer( string $src, array $attributes = [] ): string
    {
        return self::renderRegistration(
            self::registration( $src, $attributes )->defer(),
        );
    }

    /**
     * Renders an async script tag.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Script URL.
     * @param  array<string, string>  $attributes  Additional `<script>` attributes.
     */
    public static function async( string $src, array $attributes = [] ): string
    {
        return self::renderRegistration(
            self::registration( $src, $attributes )->async(),
        );
    }

    /**
     * Renders an ES module script tag.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Script URL.
     * @param  array<string, string>  $attributes  Additional `<script>` attributes.
     */
    public static function module( string $src, array $attributes = [] ): string
    {
        return self::renderRegistration(
            self::registration( $src, $attributes )->module(),
        );
    }

    /**
     * Renders a conditional (parked) script tag.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Script URL.
     * @param  array<int, string>|string  $loadOn  Trigger(s) emitted as `data-load-on`.
     * @param  string|null  $target  Optional CSS selector emitted as `data-target`.
     * @param  array<string, string>  $attributes  Additional `<script>` attributes.
     */
    public static function conditional(
        string $src,
        string|array $loadOn,
        ?string $target = null,
        array $attributes = [],
    ): string {
        $registration = self::registration( $src, $attributes )
            ->conditional()
            ->loadOn( $loadOn );

        if ( null !== $target && '' !== $target ) {
            $registration->target( $target );
        }

        return self::renderRegistration( $registration );
    }

    /**
     * Renders a registration via the configured strategy.
     *
     * Falls back to the strategy's own renderer when the container isn't
     * available — important for unit tests that exercise directives via
     * `Blade::render()` without a fully booted application.
     *
     * @since 1.0.0
     *
     * @param  ScriptRegistration  $registration  Transient registration.
     */
    public static function renderRegistration( ScriptRegistration $registration ): string
    {
        try {
            return app( ScriptManager::class )->renderOne( $registration );
        } catch ( Throwable ) {
            $manager = new ScriptManager;

            return $manager->renderOne( $registration );
        }
    }

    /**
     * Builds a transient registration with the given attributes applied.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Script URL.
     * @param  array<string, string>  $attributes  Additional `<script>` attributes.
     */
    protected static function registration( string $src, array $attributes ): ScriptRegistration
    {
        $registration = new ScriptRegistration( $src );

        foreach ( $attributes as $name => $value ) {
            $registration->attribute( (string) $name, (string) $value);
        }

        return $registration;
    }
}
