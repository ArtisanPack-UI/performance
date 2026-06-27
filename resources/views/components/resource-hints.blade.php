{{--
    Resource hints component template.

    Emits a `<link>` block resolved from the `ResourceHintInjector`
    (or an inline `:hints` descriptor list). Returns nothing when
    no hints resolve, so the component can be dropped unconditionally
    into a layout's `<head>`.
--}}
@if ( '' !== $html )
{!! $html !!}
@endif
