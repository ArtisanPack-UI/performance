# Create script Blade directives

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::3" ~"Area::Frontend"

## Problem Statement

Developers need convenient Blade directives for script loading patterns.

## Proposed Solution

Create Blade directives for common script loading patterns.

## Acceptance Criteria

- [ ] `@deferScript($src)` directive
- [ ] `@asyncScript($src)` directive
- [ ] `@moduleScript($src)` directive
- [ ] `@conditionalScript($src, $event, $target)` directive
- [ ] Register directives in service provider
- [ ] Unit tests for directive output

## Use Cases

1. Developer uses `@deferScript('/js/analytics.js')`
2. Developer uses `@conditionalScript('/js/editor.js', 'interaction', '#editor')`
3. Directives output proper HTML script tags

## Additional Context

```blade
{{-- Defer script --}}
@deferScript('/js/analytics.js')
{{-- Output: <script src="/js/analytics.js" defer></script> --}}

{{-- Async script --}}
@asyncScript('/js/widget.js')
{{-- Output: <script src="/js/widget.js" async></script> --}}

{{-- Module script --}}
@moduleScript('/js/app.mjs')
{{-- Output: <script type="module" src="/js/app.mjs"></script> --}}

{{-- Conditional loading --}}
@conditionalScript('/js/heavy.js', 'visible', '#comments')
{{-- Output: Script that loads when #comments is visible --}}
```

---

**Related Issues:**
- #018 (ScriptManager)
- #019 (Loading Strategies)
