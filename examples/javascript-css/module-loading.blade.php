{{--
    Module loading with a conditional strategy.

    Loads the checkout ES module only on routes that match the checkout
    pattern. On every other page the tag renders nothing — no bytes on
    the wire, no parse cost.
--}}

@if ( request()->routeIs( 'checkout.*' ) )
    <x-perf-script
        src="{{ asset( 'js/checkout.mjs' ) }}"
        strategy="module"
        integrity="sha384-abc123..."
        crossorigin="anonymous"
    />
@endif

{{-- Or with the shipped conditional strategy so the check lives in one
     place and the fallback strategy is explicit: --}}
<x-perf-script
    src="{{ asset( 'js/product-viewer.mjs' ) }}"
    strategy="conditional"
    :when="request()->routeIs( 'products.show' )"
    strategy-fallback="module"
/>
