<?php

/**
 * Resource hints Blade component.
 *
 * Companion to the individual `@preconnect`/`@dnsPrefetch`/`@preload`/`@prefetch`
 * directives. Renders the configured + manually-registered + provider-supplied
 * hints as a block of `<link>` elements that can be dropped into the layout
 * `<head>` so the application doesn't need a middleware to inject them.
 * Optional inline `hints` attribute lets callers pass a per-page list of
 * descriptors without touching the injector singleton.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\View\Components;

use ArtisanPackUI\Performance\Output\ResourceHint;
use ArtisanPackUI\Performance\Output\ResourceHintInjector;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

/**
 * Resource hints component class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
final class ResourceHints extends Component
{
	/**
	 * Resolved `<link>` block ready to emit into the view.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $html = '';

	/**
	 * Creates a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>|string>|null $hints Optional inline hint descriptors. When omitted the injector singleton is consulted.
	 * @param array<int, string>|string|null               $only  Filter — render only hints whose `rel` matches.
	 */
	public function __construct(
		public ?array $hints = null,
		public array|string|null $only = null,
	) {
		$this->html = $this->resolveHtml();
	}

	/**
	 * Returns the view to render.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'performance::components.resource-hints' );
	}

	/**
	 * Resolves the HTML block to emit.
	 *
	 * Inline `$hints` short-circuits the singleton; the filter array is
	 * applied identically in both branches so the per-page form and the
	 * registry-driven form stay behaviorally interchangeable.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function resolveHtml(): string
	{
		$filter = $this->normalizeFilter( $this->only );

		if ( null !== $this->hints ) {
			return $this->renderInline( $this->hints, $filter );
		}

		try {
			$injector = app( ResourceHintInjector::class );
		} catch ( Throwable ) {
			return '';
		}

		return $injector->render( $filter );
	}

	/**
	 * Renders an explicit list of hint descriptors.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>|string> $hints  Descriptor list.
	 * @param  array<int, string>|null                 $filter Optional rel filter.
	 *
	 * @return string
	 */
	protected function renderInline( array $hints, ?array $filter ): string
	{
		$html = [];

		foreach ( $hints as $entry ) {
			$hint = $this->coerceHint( $entry );

			if ( null === $hint ) {
				continue;
			}

			if ( null !== $filter && ! in_array( $hint->rel, $filter, true ) ) {
				continue;
			}

			$html[] = $hint->toLinkElement();
		}

		return implode( "\n", $html );
	}

	/**
	 * Coerces a descriptor into a `ResourceHint`, returning null on failure.
	 *
	 * Accepts a ready-made `ResourceHint`, a verbose associative array, or a
	 * shorthand string keyed under an implicit `rel=preconnect` (the most
	 * common single-string use case).
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed $entry Descriptor.
	 *
	 * @return ResourceHint|null
	 */
	protected function coerceHint( mixed $entry ): ?ResourceHint
	{
		if ( $entry instanceof ResourceHint ) {
			return $entry;
		}

		if ( is_string( $entry ) ) {
			return ResourceHint::fromConfigEntry( 'preconnect', $entry );
		}

		if ( is_array( $entry ) ) {
			$rel = isset( $entry['rel'] ) ? (string) $entry['rel'] : 'preconnect';

			return ResourceHint::fromConfigEntry( $rel, $entry );
		}

		return null;
	}

	/**
	 * Normalizes a filter spec to an array of canonical rel names.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, string>|string|null $only Raw filter.
	 *
	 * @return array<int, string>|null
	 */
	protected function normalizeFilter( array|string|null $only ): ?array
	{
		if ( null === $only ) {
			return null;
		}

		$list = is_array( $only ) ? $only : [ $only ];
		$list = array_values( array_filter( array_map(
			static fn ( mixed $value ): string => strtolower( trim( (string) $value ) ),
			$list,
		) ) );

		return empty( $list ) ? null : $list;
	}
}
