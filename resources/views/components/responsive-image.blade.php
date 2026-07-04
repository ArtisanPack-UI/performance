{{--
    Responsive image component template.

    Emits a `<picture>` element with AVIF and WebP sources when supported and
    falls back to the original-format `<img>`. Forwards lazy-loading,
    fetchpriority, and placeholder behavior to the lazy image component so all
    image attributes live in one place.
--}}
@php
	$pictureClass = trim( 'perf-responsive-image' . ( null !== $class ? ' ' . $class : '' ) );
@endphp

<picture class="{{ $pictureClass }}" {{ $attributes->except( [ 'class' ] ) }}>
	@if ( '' !== $avifSrcset )
		<source
			type="image/avif"
			srcset="{{ $avifSrcset }}"
			@if ( null !== $sizesAttr ) sizes="{{ $sizesAttr }}" @endif
		/>
	@endif

	@if ( '' !== $webpSrcset )
		<source
			type="image/webp"
			srcset="{{ $webpSrcset }}"
			@if ( null !== $sizesAttr ) sizes="{{ $sizesAttr }}" @endif
		/>
	@endif

	<x-perf-lazy-image
		:src="$fallbackSrc"
		:alt="$alt"
		:width="$width"
		:height="$height"
		:lazy="$lazy"
		:placeholder="$placeholder"
		:dominant-color="$dominantColor"
		:fetchpriority="$fetchpriority"
		:sizes="$sizesAttr"
		:srcset="'' !== $fallbackSrcset ? $fallbackSrcset : null"
		:class="$imgClass"
	/>
</picture>
