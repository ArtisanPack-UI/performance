<?php

/**
 * Speculative rules Blade component.
 *
 * Renders a `<script type="speculationrules">` block. Equivalent to the
 * `@speculativeRules` directive but lets callers pass per-instance
 * configuration overrides as attributes — useful when a layout wants to
 * publish different patterns per route without touching the global
 * configuration.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\View\Components;

use ArtisanPackUI\Performance\Support\SpeculativeDirectives;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Speculative rules component class.
 *
 *
 * @since      1.0.0
 */
final class SpeculativeRules extends Component
{
    /**
     * Resolved `<script>` block to render.
     *
     * @since 1.0.0
     */
    public string $html = '';

    /**
     * Creates a new component instance.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>|null  $prefetch  Optional prefetch overrides.
     * @param  array<string, mixed>|null  $prerender  Optional prerender overrides.
     */
    public function __construct(
        public ?array $prefetch = null,
        public ?array $prerender = null,
    ) {
        $this->html = $this->resolveHtml();
    }

    /**
     * Returns the view to render.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view( 'performance::components.speculative-rules' );
    }

    /**
     * Builds the override payload and delegates to the shared helper.
     *
     * @since 1.0.0
     */
    protected function resolveHtml(): string
    {
        $overrides = array_filter(
            [
                'prefetch'  => $this->prefetch,
                'prerender' => $this->prerender,
            ],
            static fn ( $value ): bool => null !== $value,
        );

        return SpeculativeDirectives::render( empty( $overrides ) ? null : $overrides );
    }
}
