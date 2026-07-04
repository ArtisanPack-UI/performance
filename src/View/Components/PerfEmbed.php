<?php

/**
 * Lazy embed Blade component.
 *
 * Renders a click-to-load facade for third-party embeds. When `lazy=true`
 * (the default) the component emits a thumbnail with an accessible play
 * button; the bundled `speculative-rules.js` module swaps in the real
 * provider iframe on the first click. Setting `lazy=false` short-circuits
 * the facade and emits the iframe directly so callers can opt out per
 * embed.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\View\Components;

use ArtisanPackUI\Performance\Services\EmbedOptimizer;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

/**
 * Lazy embed component class.
 *
 *
 * @since      1.0.0
 */
final class PerfEmbed extends Component
{
    /**
     * Resolved facade descriptor or null when resolution failed.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>|null
     */
    public ?array $facade = null;

    /**
     * Reason the embed could not be resolved, if any.
     *
     * @since 1.0.0
     */
    public ?string $error = null;

    /**
     * Creates a new lazy embed component.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Provider name (`youtube`, `vimeo`, `twitter`/`x`).
     * @param  string  $id  Provider-specific identifier.
     * @param  string  $title  Optional title used for the play-button aria-label.
     * @param  bool  $lazy  Whether to defer the iframe until interaction.
     * @param  bool  $showFacade  Whether to render a thumbnail facade above the play button.
     * @param  ?string  $thumbnail  Override thumbnail URL.
     * @param  array<string, mixed>  $params  Extra query parameters appended to the iframe URL.
     * @param  ?string  $class  Additional CSS classes applied to the container.
     * @param  ?int  $width  Frame width in pixels.
     * @param  ?int  $height  Frame height in pixels.
     */
    public function __construct(
        public string $provider,
        public string $id,
        public string $title = '',
        public bool $lazy = true,
        public bool $showFacade = true,
        public ?string $thumbnail = null,
        public array $params = [],
        public ?string $class = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {
        $this->facade = $this->resolveFacade();
    }

    /**
     * Returns the view to render.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view( 'performance::components.perf-embed' );
    }

    /**
     * Reports whether the facade markup should be rendered.
     *
     * @since 1.0.0
     */
    public function shouldRenderFacade(): bool
    {
        return $this->lazy && null !== $this->facade && $this->showFacade;
    }

    /**
     * Reports whether the iframe should be rendered eagerly.
     *
     * @since 1.0.0
     */
    public function shouldRenderEagerIframe(): bool
    {
        return ! $this->lazy && null !== $this->facade;
    }

    /**
     * Resolves the embed facade via the EmbedOptimizer service.
     *
     * Caught exceptions surface through the `error` property so the
     * template can render a fallback message instead of throwing during
     * view compilation.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>|null
     */
    protected function resolveFacade(): ?array
    {
        try {
            $optimizer = app( EmbedOptimizer::class );

            $options = array_filter( [
                'title'     => $this->title,
                'thumbnail' => $this->thumbnail,
                'params'    => $this->params,
            ], static fn ( $value ): bool => null !== $value && '' !== $value && [] !== $value );

            return $optimizer->facade( $this->provider, $this->id, $options );
        } catch ( Throwable $throwable ) {
            $this->error = $throwable->getMessage();

            return null;
        }
    }
}
