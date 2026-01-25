# Create script Blade components

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::3" ~"Area::Frontend"

## Problem Statement

Components provide a more flexible interface for script loading than directives.

## Proposed Solution

Create Blade components for script loading with full customization.

## Acceptance Criteria

- [ ] Create `<x-perf-script>` component
- [ ] Create `<x-perf-conditional-script>` component
- [ ] Strategy attribute: defer, async, module
- [ ] Priority attribute
- [ ] Load-on attribute for conditional loading
- [ ] Target attribute for visibility/interaction loading
- [ ] Custom attributes pass-through
- [ ] Unit tests for components

## Use Cases

1. Developer uses `<x-perf-script src="..." strategy="defer" />`
2. Developer uses `<x-perf-conditional-script load-on="visible" />`
3. Components support all script options

## Additional Context

```blade
{{-- Basic script with strategy --}}
<x-perf-script src="/js/app.js" strategy="defer" />

{{-- Module script --}}
<x-perf-script src="/js/app.mjs" strategy="module" />

{{-- Conditional loading on interaction --}}
<x-perf-script
    src="/js/heavy-widget.js"
    :load-on="['click', 'mouseover']"
    target="#widget-container"
/>

{{-- Conditional loading on visibility --}}
<x-perf-script
    src="/js/comments.js"
    load-on="visible"
    target="#comments-section"
/>
```

---

**Related Issues:**
- #018 (ScriptManager)
- #022 (Script Directives)
