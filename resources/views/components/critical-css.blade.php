{{--
    Critical CSS component template.

    Emits a `<style>` block containing the cached critical CSS for the
    resolved route. Returns nothing when the extractor produces an empty
    bundle so the component can be dropped unconditionally into a layout.
--}}
@if ( '' !== $css )
<style data-critical="{{ $resolvedRoute }}">{!! $css !!}</style>
@endif
