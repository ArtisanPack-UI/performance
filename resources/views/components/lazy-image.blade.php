{{--
    Lazy image component template.

    Renders an `<img>` element with native `loading="lazy"` and optional
    placeholders. Skeleton placeholders wrap the image in a div the
    application can style via the `.perf-skeleton` class.
--}}
@php
	$imgClass = trim( 'perf-lazy-image' . ( null !== $class ? ' ' . $class : '' ) );
	$imgAttrs = $attributes->except( [ 'class' ] )->merge( [ 'class' => $imgClass ] );
@endphp

@if ( $shouldUseSkeleton() )
	<div class="perf-skeleton" @if ( null !== $width && null !== $height ) style="aspect-ratio: {{ $width }} / {{ $height }};" @endif>
@endif

<img
	src="{{ $initialSrc() }}"
	@if ( $shouldUseBlurPlaceholder() ) data-src="{{ $src }}" @endif
	alt="{{ $alt }}"
	loading="{{ $loadingAttribute }}"
	decoding="async"
	@if ( null !== $width ) width="{{ $width }}" @endif
	@if ( null !== $height ) height="{{ $height }}" @endif
	@if ( null !== $srcset ) srcset="{{ $srcset }}" @endif
	@if ( null !== $sizes ) sizes="{{ $sizes }}" @endif
	@if ( $shouldEmitFetchpriority() ) fetchpriority="{{ $fetchpriority }}" @endif
	@if ( null !== $threshold ) data-threshold="{{ $threshold }}" @endif
	@if ( '' !== $placeholderStyle ) style="{{ $placeholderStyle }}" @endif
	{{ $imgAttrs }}
/>

@if ( $shouldUseSkeleton() )
	</div>
@endif
