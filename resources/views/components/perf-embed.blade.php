{{--
    Lazy embed component template.

    Emits one of three shapes:
      1. A `<div class="perf-embed-facade">` thumbnail wrapper when the
         component is lazy and the facade resolved cleanly. The bundled
         `speculative-rules.js` module listens for clicks on this element
         and swaps in the provider iframe (iframe-mode) or blockquote +
         widgets script (blockquote-mode for Twitter/X).
      2. A direct `<iframe>` or inline blockquote when `lazy` is false.
      3. A no-op comment when the provider/id failed validation, so the
         page still renders without throwing.

    For blockquote-mode embeds the activation HTML is base64-encoded into
    `data-embed-html` so the markup can contain `"` and `<` without
    breaking the data attribute. The JS module decodes it on click.
--}}
@php
	$containerClass = trim( 'perf-embed ' . ( $class ?? '' ) );
	$style          = ( null !== $width && null !== $height )
		? sprintf( 'aspect-ratio: %d / %d;', $width, $height )
		: '';
	$mode           = $facade['mode'] ?? 'iframe';
	$embedHtmlAttr  = ( 'blockquote' === $mode && '' !== $facade['embed_html'] )
		? base64_encode( $facade['embed_html'] )
		: '';
@endphp

@if ( null === $facade )
	<!-- perf-embed: {{ $error ?? 'unsupported provider or invalid id' }} -->
@elseif ( $shouldRenderFacade() )
	<div
		class="{{ $containerClass }} perf-embed-facade"
		data-provider="{{ $facade['provider'] }}"
		data-id="{{ $facade['id'] }}"
		data-mode="{{ $mode }}"
		data-title="{{ $facade['title'] }}"
		data-iframe-url="{{ $facade['iframe_url'] }}"
		data-embed-html="{{ $embedHtmlAttr }}"
		data-widgets-script="{{ $facade['widgets_script'] }}"
		@if ( '' !== $style ) style="{{ $style }}" @endif
	>
		@if ( '' !== $facade['thumbnail'] )
			<img
				src="{{ $facade['thumbnail'] }}"
				alt="{{ $facade['title'] }}"
				loading="lazy"
				decoding="async"
				class="perf-embed-thumbnail"
			>
		@else
			<div class="perf-embed-thumbnail perf-embed-thumbnail--placeholder" aria-hidden="true"></div>
		@endif

		<button
			type="button"
			class="perf-embed-play"
			aria-label="{{ $facade['title'] }}"
		>
			<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true" focusable="false">
				<path d="M8 5v14l11-7z" fill="currentColor"></path>
			</svg>
		</button>
	</div>
@elseif ( $shouldRenderEagerIframe() )
	@if ( 'blockquote' === $mode )
		<div class="{{ $containerClass }}">
			{!! $facade['embed_html'] !!}
			@if ( '' !== $facade['widgets_script'] )
				<script async src="{{ $facade['widgets_script'] }}" charset="utf-8"></script>
			@endif
		</div>
	@else
		<iframe
			src="{{ $facade['iframe_url'] }}"
			title="{{ $facade['title'] }}"
			loading="eager"
			@if ( null !== $width ) width="{{ $width }}" @endif
			@if ( null !== $height ) height="{{ $height }}" @endif
			allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
			referrerpolicy="strict-origin-when-cross-origin"
			allowfullscreen
			class="{{ $containerClass }}"
		></iframe>
	@endif
@else
	{{-- lazy=true + showFacade=false: emit a bare activator div. The
	     `perf-embed-facade` class is required for `speculative-rules.js`
	     to delegate the click event; without it the embed would never
	     activate, leaving dead UI. Authors that want a fully-custom
	     thumbnail wrapper should layer their own markup over this div. --}}
	<div
		class="{{ $containerClass }} perf-embed-facade"
		data-provider="{{ $facade['provider'] }}"
		data-id="{{ $facade['id'] }}"
		data-mode="{{ $mode }}"
		data-title="{{ $facade['title'] }}"
		data-iframe-url="{{ $facade['iframe_url'] }}"
		data-embed-html="{{ $embedHtmlAttr }}"
		data-widgets-script="{{ $facade['widgets_script'] }}"
		@if ( '' !== $style ) style="{{ $style }}" @endif
	></div>
@endif
