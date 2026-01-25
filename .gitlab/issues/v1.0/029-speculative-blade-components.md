# Create speculative loading Blade components

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::4" ~"Area::Frontend"

## Problem Statement

Developers need convenient components for speculative loading features.

## Proposed Solution

Create Blade components and directives for speculation rules and link attributes.

## Acceptance Criteria

- [ ] Create `@speculativeRules` directive
- [ ] Create `<x-perf-speculative-rules>` component
- [ ] Create `<x-perf-prefetch>` component for link prefetching
- [ ] Support data attributes: `data-prerender`, `data-prefetch`, `data-no-speculate`
- [ ] Configurable eagerness levels
- [ ] Unit tests for components

## Use Cases

1. Add speculation rules to page layout
2. Mark specific links for prerendering
3. Exclude links from speculation

## Additional Context

```blade
{{-- Add speculative rules to page --}}
@speculativeRules

{{-- Or with configuration --}}
<x-perf-speculative-rules
    :prefetch="['moderate']"
    :prerender="['conservative']"
/>

{{-- Prefetch specific URLs --}}
<x-perf-prefetch :urls="['/about', '/contact']" />
```

**Link Attributes:**
```blade
{{-- Mark link for prerendering --}}
<a href="/products/1" data-prerender>Product 1</a>

{{-- Mark link for prefetching --}}
<a href="/blog/post" data-prefetch>Blog Post</a>

{{-- Exclude from speculation --}}
<a href="/logout" data-no-speculate>Logout</a>
```

---

**Related Issues:**
- #026 (SpeculativeRulesGenerator)
