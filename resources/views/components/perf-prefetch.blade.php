{{--
    Prefetch component template.

    Emits one `<link rel="prefetch">` element per resolved URL. Empty
    URL lists produce no output so the component can sit unconditionally
    in a layout's `<head>`.
--}}
@foreach ( $resolvedUrls as $url )
<link rel="prefetch" href="{{ $url }}"@if ( null !== $as ) as="{{ $as }}"@endif>
@endforeach
