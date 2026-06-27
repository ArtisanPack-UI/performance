<?php

/**
 * Prefetch link Blade component.
 *
 * Renders one `<link rel="prefetch">` element per URL. The element form
 * is used as a graceful fallback for browsers that don't support the
 * Speculation Rules API — the bundled `speculative-rules.js` module also
 * targets `<link rel="prefetch">` so feature detection is consistent
 * regardless of how the URLs entered the page.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Prefetch link component class.
 *
 *
 * @since      1.0.0
 */
final class PerfPrefetch extends Component
{
    /**
     * URLs to emit as prefetch hints.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public array $resolvedUrls;

    /**
     * Creates a new component instance.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|string  $urls  Single URL or list of URLs.
     * @param  string|null  $as  Optional `as` attribute (`document`, `script`, …).
     */
    public function __construct(
        public string|array $urls,
        public ?string $as = null,
    ) {
        $this->resolvedUrls = $this->normalize( $urls );
    }

    /**
     * Returns the view to render.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view( 'performance::components.perf-prefetch' );
    }

    /**
     * Normalizes the URL input to a list of trimmed, non-empty strings.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|string  $urls  Caller-supplied URL input.
     *
     * @return array<int, string>
     */
    protected function normalize( string|array $urls ): array
    {
        $list = is_array( $urls ) ? $urls : [$urls];

        $normalized = [];

        foreach ( $list as $url ) {
            if ( ! is_string( $url ) ) {
                continue;
            }

            $url = trim( $url );

            if ( '' === $url ) {
                continue;
            }

            $normalized[ $url ] = $url;
        }

        return array_values( $normalized);
    }
}
