{{--
    Script strategies.

    <x-perf-script> picks an AbstractScriptStrategy from
    ArtisanPackUI\Performance\JavaScript based on the `strategy`
    attribute. Every strategy renders a plain <script> tag with the
    right attributes — no JavaScript wrappers required.

    Requires:
      'features' => [
          'script_optimization' => true,
      ],
--}}

{{-- defer: runs after HTML parsing, in order relative to other defers. --}}
<x-perf-script
    src="{{ asset( 'js/analytics.js' ) }}"
    strategy="defer"
/>

{{-- async: runs as soon as the file loads, out of order. --}}
<x-perf-script
    src="https://plausible.io/js/script.js"
    strategy="async"
    data-domain="example.com"
/>

{{-- module: <script type="module"> — HTTP/2 friendly, tree-shakeable. --}}
<x-perf-script
    src="{{ asset( 'js/app.js' ) }}"
    strategy="module"
/>

{{-- inline: emit a small snippet inline so no extra request is made. --}}
<x-perf-script strategy="inline">
    window.APP_LOCALE = @json( app()->getLocale() );
</x-perf-script>

{{-- conditional: only load on routes matching the pattern. --}}
<x-perf-script
    src="{{ asset( 'js/checkout.js' ) }}"
    strategy="conditional"
    :when="request()->routeIs( 'checkout.*' )"
    strategy-fallback="defer"
/>
