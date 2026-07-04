{{--
    Script component template.

    Emits the pre-rendered `<script>` HTML composed by the component's
    `withAttributes()` hook. The template is a static cached Blade file
    so `$html` is just a runtime echo — `{{ ... }}` smuggled through an
    attacker-controlled src/attribute is escaped at render time and
    cannot be re-evaluated as Blade. Closure-return alternatives would
    route through `extractBladeViewFromString()` and recompile the
    output as a template, opening an injection path.
--}}
{{--
    `$renderScript` is the public `renderScript( ?ComponentAttributeBag )`
    method exposed to the view by `Component::data()` as a Closure bound
    to `$this`. Calling it here (rather than pre-computing `$html` in
    `data()`) is necessary because Blade binds caller-supplied
    pass-through attributes via `withAttributes()` AFTER `data()` runs —
    so attribute-aware HTML can only be assembled at view-render time.

    `{!! ... !!}` echoes the returned string verbatim into this
    already-compiled template; Blade does NOT re-parse it, so no
    `{{ ... }}` smuggled through an attacker-controlled `src` or attribute
    can be re-evaluated. (Returning a Closure from `render()` would route
    the string through `extractBladeViewFromString()`, which DOES
    recompile.)
--}}
{!! $renderScript( $attributes ) !!}
