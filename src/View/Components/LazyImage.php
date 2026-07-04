<?php

/**
 * Lazy image Blade component.
 *
 * Renders an `<img>` element with native `loading="lazy"` and `decoding="async"`,
 * a configurable placeholder (dominant color, blur LQIP, skeleton, none), and
 * `fetchpriority` support for above-the-fold images. Width/height attributes
 * are required for CLS prevention; callers that supply only `src` will receive
 * a component without dimensions.
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
 * Lazy image component class.
 *
 *
 * @since      1.0.0
 */
final class LazyImage extends Component
{
    /**
     * Supported placeholder strategies.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const PLACEHOLDERS = ['dominant_color', 'blur', 'skeleton', 'none'];

    /**
     * Resolved placeholder strategy for the component.
     *
     * @since 1.0.0
     */
    public string $resolvedPlaceholder;

    /**
     * Resolved `loading` attribute value (`lazy` or `eager`).
     *
     * @since 1.0.0
     */
    public string $loadingAttribute;

    /**
     * Resolved background style applied for the dominant-color placeholder.
     *
     * Empty string when no background is applied.
     *
     * @since 1.0.0
     */
    public string $placeholderStyle;

    /**
     * Creates a new lazy image component instance.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Image source URL.
     * @param  string  $alt  Alt text for accessibility.
     * @param  int|null  $width  Width in pixels (recommended to prevent CLS).
     * @param  int|null  $height  Height in pixels (recommended to prevent CLS).
     * @param  bool  $lazy  Whether to render `loading="lazy"`. Pass false for LCP images.
     * @param  string|null  $placeholder  Placeholder strategy: `dominant_color`, `blur`, `skeleton`, or `none`.
     * @param  string|null  $dominantColor  Hex color used when `placeholder=dominant_color`.
     * @param  string|null  $blurSrc  Data URI of the blurred LQIP when `placeholder=blur`.
     * @param  string|null  $fetchpriority  Fetchpriority hint: `high`, `low`, or `auto`.
     * @param  string|null  $threshold  Optional viewport threshold (CSS root margin) emitted as a `data-threshold` attribute.
     * @param  string|null  $sizes  Optional `sizes` attribute.
     * @param  string|null  $srcset  Optional `srcset` attribute.
     * @param  string|null  $class  Additional CSS classes to append to the `<img>` element.
     */
    public function __construct(
        public string $src,
        public string $alt = '',
        public ?int $width = null,
        public ?int $height = null,
        public bool $lazy = true,
        public ?string $placeholder = null,
        public ?string $dominantColor = null,
        public ?string $blurSrc = null,
        public ?string $fetchpriority = null,
        public ?string $threshold = null,
        public ?string $sizes = null,
        public ?string $srcset = null,
        public ?string $class = null,
    ) {
        $this->resolvedPlaceholder = $this->resolvePlaceholder( $placeholder );
        $this->loadingAttribute    = $lazy ? 'lazy' : 'eager';
        $this->placeholderStyle    = $this->resolvePlaceholderStyle();
    }

    /**
     * Returns the view to render.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view( 'performance::components.lazy-image' );
    }

    /**
     * Reports whether the configured fetchpriority value is supported by browsers.
     *
     * @since 1.0.0
     */
    public function shouldEmitFetchpriority(): bool
    {
        return null !== $this->fetchpriority
            && in_array( strtolower( $this->fetchpriority ), ['high', 'low', 'auto'], true );
    }

    /**
     * Reports whether a skeleton placeholder should wrap the image.
     *
     * @since 1.0.0
     */
    public function shouldUseSkeleton(): bool
    {
        return 'skeleton' === $this->resolvedPlaceholder;
    }

    /**
     * Reports whether the component should emit a blur LQIP src attribute.
     *
     * @since 1.0.0
     */
    public function shouldUseBlurPlaceholder(): bool
    {
        return 'blur' === $this->resolvedPlaceholder
            && ! empty( $this->blurSrc )
            && $this->isSafeBlurDataUri( $this->blurSrc );
    }

    /**
     * Returns the source the browser should render first.
     *
     * Blur placeholders display the LQIP data URI until the lazy load swaps in
     * the high-resolution source; every other strategy uses the original
     * source directly.
     *
     * @since 1.0.0
     */
    public function initialSrc(): string
    {
        return $this->shouldUseBlurPlaceholder() ? (string) $this->blurSrc : $this->src;
    }

    /**
     * Reports whether the given data URI is a safe raster blur placeholder.
     *
     * Whitelists `image/jpeg`, `image/png`, `image/webp`, `image/avif`, and
     * `image/gif` mime types. Rejects everything else — most importantly
     * `image/svg+xml`, which is loadable from `<img src>` but can fire
     * outbound requests via `<image href="https://attacker/leak">` or
     * `<use href="...">` references (an exfiltration/fingerprinting channel
     * through a placeholder attribute, even though the SVG can't execute
     * scripts in the `<img src>` context).
     *
     * @since 1.0.0
     *
     * @param  string  $uri  Caller-supplied data URI.
     */
    protected function isSafeBlurDataUri( string $uri ): bool
    {
        // Match the mime type followed by either `;` (further params, incl.
        // `;base64,`) or `,` (raw data immediately). Anything else — including
        // `image/svg+xml`, `text/html`, an unknown mime, or trailing garbage —
        // fails to match.
        return 1 === preg_match(
            '#^data:image/(?:jpeg|png|webp|avif|gif)[;,]#i',
            $uri,
        );
    }

    /**
     * Resolves the placeholder strategy with fall-back precedence.
     *
     * Per-call → config → `none`. Unknown strategies degrade to `none` so
     * typos don't surface bad markup.
     *
     * @since 1.0.0
     *
     * @param  string|null  $placeholder  Caller-supplied placeholder strategy.
     */
    protected function resolvePlaceholder( ?string $placeholder ): string
    {
        $value = $placeholder
            ?? config( 'artisanpack.performance.images.lazy_loading.placeholder', 'none' );

        $value = strtolower( (string) $value );

        return in_array( $value, self::PLACEHOLDERS, true ) ? $value : 'none';
    }

    /**
     * Resolves the inline style for the dominant-color placeholder.
     *
     * Returns an empty string when no background should be applied so the
     * component template can conditionally skip the `style` attribute. The
     * color value is validated against the `#rrggbb`/`#rgb`/`#rrggbbaa` hex
     * pattern before composition so a hostile or accidentally-malformed
     * value can't smuggle additional CSS declarations through the
     * `style="..."` attribute (Blade attribute escaping does not strip CSS
     * tokens — it just escapes quotes/HTML).
     *
     * @since 1.0.0
     */
    protected function resolvePlaceholderStyle(): string
    {
        if ( 'dominant_color' !== $this->resolvedPlaceholder ) {
            return '';
        }

        $color = $this->dominantColor;

        if ( empty( $color ) || ! $this->isValidHexColor( $color ) ) {
            return '';
        }

        return 'background-color: ' . $color . ';';
    }

    /**
     * Reports whether the given string is a syntactically valid hex color.
     *
     * Accepts the four CSS Color Module 4 hex shorthands — `#rgb`, `#rgba`,
     * `#rrggbb`, `#rrggbbaa` — and rejects every other input so the value
     * is safe to interpolate into a CSS `style` attribute. Fully transparent
     * alpha values (`#rrggbb00`, `#rgb0`) are also rejected: a transparent
     * placeholder is invisible and defeats the entire point of the
     * `dominant_color` strategy.
     *
     * @since 1.0.0
     *
     * @param  string  $color  Caller-supplied color value.
     */
    protected function isValidHexColor( string $color ): bool
    {
        if ( 1 !== preg_match( '/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color ) ) {
            return false;
        }

        // 9-character form is `#rrggbbaa`; alpha = `00` is fully transparent.
        if ( 9 === strlen( $color ) && '00' === strtolower( substr( $color, -2 ) ) ) {
            return false;
        }

        // 5-character form is `#rgba`; alpha = `0` is fully transparent.
        if ( 5 === strlen( $color ) && '0' === substr( $color, -1 ) ) {
            return false;
        }

        return true;
    }
}
