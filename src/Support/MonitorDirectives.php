<?php

/**
 * Performance monitor directive rendering helpers.
 *
 * Backs the `@perfMonitor` Blade directive that boots the Core Web
 * Vitals RUM collector. Renders a small JSON configuration block
 * (endpoint, sample rate, page context, CSRF token) the published
 * `web-vitals.js` module reads at startup, plus the `<script>` tag
 * that pulls the module itself.
 *
 * The configuration block is emitted as an inline `<script>` rather
 * than as data attributes so the page can deliver per-request values
 * (route name, CSRF token) without the collector having to re-query
 * the DOM. The module script tag uses `type="module"` so modern
 * browsers can run it without a bundler, and falls back silently on
 * older browsers (the metrics are best-effort).
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Support;
use Throwable;

/**
 * Performance monitor directive helper.
 *
 *
 * @since      1.0.0
 */
final class MonitorDirectives
{
    /**
     * Default API endpoint that receives metric beacons.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_ENDPOINT = '/api/performance/metrics';

    /**
     * Default published asset path for the web-vitals module.
     *
     * Mirrors the publish target in
     * `PerformanceServiceProvider::publishConfiguration()` so the
     * directive's default script `src` resolves out of the box for
     * any app that ran `vendor:publish --tag=artisanpack-performance-js`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_SCRIPT_PATH = '/vendor/artisanpack-performance/web-vitals.js';

    /**
     * Disallow instantiation; the class is a pure static helper.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }

    /**
     * Renders the `@perfMonitor` directive output.
     *
     * Emits nothing when monitoring is disabled or
     * `collect_web_vitals` is off so a layout containing the
     * directive doesn't ship dead JavaScript in environments that
     * opted out. Optional `$overrides` allow per-page customization
     * (e.g. attaching tenant/user IDs as `extra` metadata) without
     * mutating package config.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $overrides  Per-call configuration overrides.
     */
    public static function perfMonitor( array $overrides = [] ): string
    {
        if ( ! self::isEnabled() ) {
            return '';
        }

        $config = self::buildConfig( $overrides );

        $configBlock = self::renderConfigBlock( $config );
        $scriptTag   = self::renderScriptTag( $overrides );

        return $configBlock . "\n" . $scriptTag;
    }

    /**
     * Reports whether the directive should produce any output.
     *
     * @since 1.0.0
     */
    public static function isEnabled(): bool
    {
        $monitoring = (bool) config( 'artisanpack.performance.monitoring.enabled', false );

        if ( ! $monitoring ) {
            return false;
        }

        return (bool) config( 'artisanpack.performance.monitoring.collect_web_vitals', true );
    }

    /**
     * Returns the resolved configuration handed to the JS module.
     *
     * The return value is intentionally a flat associative array (no
     * nested objects beyond `extra`) so `json_encode()` produces a
     * compact, predictable payload the JS side can `JSON.parse`
     * without surprises.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $overrides  Per-call overrides.
     *
     * @return array<string, mixed>
     */
    public static function buildConfig( array $overrides = [] ): array
    {
        $endpoint = $overrides['endpoint']
            ?? config( 'artisanpack.performance.monitoring.endpoint', self::DEFAULT_ENDPOINT );

        $sampleRate = $overrides['sampleRate']
            ?? config( 'artisanpack.performance.monitoring.sample_rate', 100 );

        $defaults = [
            'endpoint'   => (string) $endpoint,
            'sampleRate' => (int) $sampleRate,
            'csrfToken'  => self::csrfToken(),
            'page'       => self::currentPath(),
            'route'      => self::currentRouteName(),
            'extra'      => (array) ( $overrides['extra'] ?? [] ),
        ];

        // Apply remaining overrides (page/route/csrfToken) after the
        // defaults so callers can blank a field by passing an empty
        // string — useful when the page intentionally wants to drop
        // server-supplied context (e.g. privacy-sensitive routes).
        foreach ( [ 'csrfToken', 'page', 'route' ] as $key ) {
            if ( array_key_exists( $key, $overrides ) ) {
                $defaults[ $key ] = $overrides[ $key ];
            }
        }

        return $defaults;
    }

    /**
     * Renders the inline configuration `<script>` block.
     *
     * The payload is `JSON_HEX_TAG`-encoded so a closing `</script>`
     * sequence inside any string value can't break out of the
     * surrounding block — the dominant XSS escape route for inline
     * JSON. `JSON_UNESCAPED_SLASHES` keeps URLs readable in the
     * rendered page source.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $config  Resolved configuration.
     */
    public static function renderConfigBlock( array $config ): string
    {
        $encoded = json_encode(
            $config,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
        );

        if ( false === $encoded ) {
            $encoded = '{}';
        }

        return '<script>window.ArtisanPackPerformance=window.ArtisanPackPerformance||{};'
            . 'window.ArtisanPackPerformance.monitor=' . $encoded . ';</script>';
    }

    /**
     * Renders the `<script type="module">` tag that loads the collector.
     *
     * Callers can supply a `src` override so applications that bundle
     * the collector into their own Vite/webpack pipeline can point
     * the directive at their bundled output instead of the package's
     * published asset.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $overrides  Per-call overrides; honors `src`.
     */
    public static function renderScriptTag( array $overrides = [] ): string
    {
        $src = isset( $overrides['src'] ) && is_string( $overrides['src'] ) && '' !== $overrides['src']
            ? $overrides['src']
            : self::DEFAULT_SCRIPT_PATH;

        // No `defer` attribute — module scripts are deferred by spec
        // default (HTML Living Standard §4.12.1), so adding it would
        // be redundant noise in view-source.
        return sprintf(
            '<script type="module" src="%s"></script>',
            htmlspecialchars( $src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
        );
    }

    /**
     * Returns the active CSRF token, or null when unavailable.
     *
     * Falls back to null silently — the session manager isn't always
     * bound in Blade-render contexts (component preview, console
     * `view()->render()` calls) and we'd rather emit `csrfToken:null`
     * than crash the whole page.
     *
     * @since 1.0.0
     */
    protected static function csrfToken(): ?string
    {
        try {
            $token = csrf_token();

            return is_string( $token ) && '' !== $token ? $token : null;
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * Returns the current request path with a leading slash.
     *
     * Falls back to null when no request is active (CLI rendering,
     * test harnesses without `request()->path()`) so the JS side can
     * still derive the path from `window.location` at runtime.
     *
     * @since 1.0.0
     */
    protected static function currentPath(): ?string
    {
        try {
            $request = request();

            if ( null === $request ) {
                return null;
            }

            $path = $request->path();

            return '/' . ltrim( $path, '/' );
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * Returns the current route's name, or null when no named route matched.
     *
     * @since 1.0.0
     */
    protected static function currentRouteName(): ?string
    {
        try {
            $request = request();

            if ( null === $request ) {
                return null;
            }

            $route = $request->route();

            if ( null === $route || ! method_exists( $route, 'getName' ) ) {
                return null;
            }

            $name = $route->getName();

            return is_string( $name ) && '' !== $name ? $name : null;
        } catch ( Throwable ) {
            return null;
        }
    }
}
