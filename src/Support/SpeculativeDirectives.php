<?php

/**
 * Speculative loading directive helpers.
 *
 * Backs the Phase 4 Blade directive `@speculativeRules`. The directive
 * compiles to a single call into `render()` here so the runtime path
 * picks up the singleton `SpeculativeRulesGenerator` (and any container
 * rebinds an application installs in its own service provider).
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Support;

use ArtisanPackUI\Performance\Speculative\PrefetchManager;
use ArtisanPackUI\Performance\Speculative\PrerenderManager;
use ArtisanPackUI\Performance\Speculative\SpeculativeRulesGenerator;
use Closure;
use Throwable;

/**
 * Speculative loading directive helpers.
 *
 *
 * @since      1.0.0
 */
final class SpeculativeDirectives
{
    /**
     * Application-supplied CSP nonce or nonce-producing closure.
     *
     * Set via `SpeculativeDirectives::useCspNonce()`. When non-null, the
     * value is emitted as `nonce="…"` on the inline
     * `<script type="speculationrules">` tag so apps running a strict CSP
     * (no `'unsafe-inline'`) keep the speculation rules active.
     *
     * @since 1.0.0
     *
     * @var Closure|string|null
     */
    protected static string|Closure|null $cspNonce = null;

    /**
     * Disallow instantiation; the class is a pure static helper.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }

    /**
     * Registers a CSP nonce (or nonce-producing closure) for the emitted script tag.
     *
     * Apps that boot a strict CSP should call this from a service provider
     * with the per-request nonce so the `<script type="speculationrules">`
     * tag carries the matching `nonce` attribute. Pass a closure when the
     * nonce is rotated per request and the value isn't known at boot time.
     *
     * @since 1.0.0
     *
     * @param  Closure|string|null  $nonce  Static nonce, closure returning a nonce, or null to clear.
     */
    public static function useCspNonce( string|Closure|null $nonce ): void
    {
        self::$cspNonce = $nonce;
    }

    /**
     * Clears any registered CSP nonce.
     *
     * Tests and long-lived Octane workers should call this between requests
     * so a nonce set during request A doesn't leak into request B's tag.
     *
     * @since 1.0.0
     */
    public static function flushCspNonce(): void
    {
        self::$cspNonce = null;
    }

    /**
     * Renders the `<script type="speculationrules">` block for the current request.
     *
     * Combines package configuration with any URLs the application
     * registered through `PrefetchManager`/`PrerenderManager`. Returns an
     * empty string when the speculative-loading feature is disabled or the
     * rules document collapses to `{}` — emitting an empty script tag would
     * cost a parse with no benefit. When a CSP nonce is registered via
     * `useCspNonce()` the tag carries `nonce="…"` so strict-CSP apps keep
     * the rules active.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>|null  $overrides  Optional per-call override for the rules config.
     */
    public static function render( ?array $overrides = null ): string
    {
        if ( ! self::featureEnabled() ) {
            return '';
        }

        try {
            $generator = app( SpeculativeRulesGenerator::class );
        } catch ( Throwable ) {
            return '';
        }

        $config = self::resolveConfig( $overrides );

        $json = $generator->generate( $config );

        if ( '' === $json || '{}' === $json ) {
            return '';
        }

        $nonce     = self::resolveCspNonce();
        $nonceAttr = null !== $nonce
            ? sprintf( ' nonce="%s"', htmlspecialchars( $nonce, ENT_QUOTES, 'UTF-8' ) )
            : '';

        return sprintf(
            '<script type="speculationrules"%s>%s</script>',
            $nonceAttr,
            $json,
        );
    }

    /**
     * Resolves the registered CSP nonce to a non-empty string, or null.
     *
     * @since 1.0.0
     */
    protected static function resolveCspNonce(): ?string
    {
        if ( null === self::$cspNonce ) {
            return null;
        }

        $value = self::$cspNonce instanceof Closure
            ? ( self::$cspNonce )()
            : self::$cspNonce;

        if ( ! is_string( $value ) || '' === $value ) {
            return null;
        }

        return $value;
    }

    /**
     * Resolves the rules configuration the directive should generate from.
     *
     * Merges (in order) package config → manager-supplied URLs →
     * per-call overrides. Manager URLs are folded into the `urls` key on
     * the relevant action so they appear in the same rules document as
     * the configured patterns.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>|null  $overrides  Caller-supplied overrides.
     *
     * @return array<string, mixed>
     */
    protected static function resolveConfig( ?array $overrides ): array
    {
        $config = (array) config( 'artisanpack.performance.speculative_loading', [] );

        $prefetch  = (array) ( $config['prefetch'] ?? [] );
        $prerender = (array) ( $config['prerender'] ?? [] );

        $prefetchUrls  = self::resolvePrefetchUrls();
        $prerenderUrls = self::resolvePrerenderUrls();

        if ( ! empty( $prefetchUrls ) ) {
            $prefetch['urls'] = array_values( array_unique( array_merge(
                (array) ( $prefetch['urls'] ?? [] ),
                $prefetchUrls,
            ) ) );
        }

        if ( ! empty( $prerenderUrls ) ) {
            $prerender['urls'] = array_values( array_unique( array_merge(
                (array) ( $prerender['urls'] ?? [] ),
                $prerenderUrls,
            ) ) );
        }

        $merged = [
            'prefetch'  => $prefetch,
            'prerender' => $prerender,
        ];

        if ( null !== $overrides ) {
            if ( isset( $overrides['prefetch'] ) && is_array( $overrides['prefetch'] ) ) {
                $merged['prefetch'] = array_replace( $merged['prefetch'], $overrides['prefetch'] );
            }

            if ( isset( $overrides['prerender'] ) && is_array( $overrides['prerender'] ) ) {
                $merged['prerender'] = array_replace( $merged['prerender'], $overrides['prerender'] );
            }
        }

        return $merged;
    }

    /**
     * Returns the prefetch URLs registered via the manager.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected static function resolvePrefetchUrls(): array
    {
        try {
            return app( PrefetchManager::class )->all();
        } catch ( Throwable ) {
            return [];
        }
    }

    /**
     * Returns the prerender URLs registered via the manager (post-limit).
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected static function resolvePrerenderUrls(): array
    {
        try {
            return app( PrerenderManager::class )->all();
        } catch ( Throwable ) {
            return [];
        }
    }

    /**
     * Reports whether the speculative-loading feature is enabled.
     *
     * Defaults to true (matching the package config default) so callers
     * that haven't published the config still get the feature. String
     * config values (`'false'`, `'0'`, `'off'`, …) coming from unparsed
     * env vars are normalized via `FILTER_VALIDATE_BOOLEAN` so the
     * operator's opt-out intent is respected regardless of cast type.
     *
     * @since 1.0.0
     */
    protected static function featureEnabled(): bool
    {
        $enabled = config( 'artisanpack.performance.speculative_loading.enabled', true );

        if ( is_bool( $enabled ) ) {
            return $enabled;
        }

        if ( null === $enabled ) {
            return false;
        }

        return (bool) filter_var( $enabled, FILTER_VALIDATE_BOOLEAN );
    }
}
