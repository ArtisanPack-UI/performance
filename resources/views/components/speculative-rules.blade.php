{{--
    Speculative rules component template.

    Emits a single `<script type="speculationrules">` block when the
    component resolved a non-empty rules document, and nothing otherwise.
--}}
@if ( '' !== $html )
{!! $html !!}
@endif
