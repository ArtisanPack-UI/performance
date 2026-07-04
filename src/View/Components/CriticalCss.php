<?php

/**
 * Critical CSS Blade component.
 *
 * Companion to the `@criticalCss` directive — renders the cached critical
 * CSS `<style>` block for the given route as a self-contained component.
 * Used as `<x-perf-critical-css :route="..." />`. Pass `route` explicitly
 * for static layouts or omit it to resolve from `request()->route()->getName()`.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\View\Components;

use ArtisanPackUI\Performance\Css\CriticalCssExtractor;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

/**
 * Critical CSS component class.
 *
 *
 * @since      1.0.0
 */
final class CriticalCss extends Component
{
    /**
     * Resolved critical CSS body.
     *
     * Empty string when no critical CSS is registered for the resolved route
     * (the view template skips emission entirely in that case).
     *
     * @since 1.0.0
     */
    public string $css = '';

    /**
     * Resolved route name used to look up cached CSS.
     *
     * @since 1.0.0
     */
    public string $resolvedRoute;

    /**
     * Creates a new component instance.
     *
     * @since 1.0.0
     *
     * @param  string|null  $route  Optional route name; null resolves from the request.
     */
    public function __construct( public ?string $route = null )
    {
        $this->resolvedRoute = $this->resolveRoute( $route );
        $this->css           = $this->extract( $this->resolvedRoute );
    }

    /**
     * Returns the view to render.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view( 'performance::components.critical-css' );
    }

    /**
     * Resolves the route name, defaulting to the current request's route.
     *
     * Falls back to the literal `default` key so callers can register a
     * fallback CSS bundle once and have unnamed routes pick it up.
     *
     * @since 1.0.0
     *
     * @param  string|null  $route  Explicit route name (may be null).
     */
    protected function resolveRoute( ?string $route ): string
    {
        if ( null !== $route && '' !== $route ) {
            return $route;
        }

        if ( function_exists( 'request' ) ) {
            try {
                $current = request()->route()?->getName();
            } catch ( Throwable ) {
                $current = null;
            }

            if ( null !== $current && '' !== $current ) {
                return $current;
            }
        }

        return CriticalCssExtractor::DEFAULT_ROUTE;
    }

    /**
     * Resolves the extractor and pulls cached CSS for the route.
     *
     * @since 1.0.0
     *
     * @param  string  $route  Resolved route name.
     */
    protected function extract( string $route ): string
    {
        if ( ! function_exists( 'app' ) ) {
            return '';
        }

        try {
            return app( CriticalCssExtractor::class )->forRoute( $route );
        } catch ( Throwable ) {
            return '';
        }
    }
}
