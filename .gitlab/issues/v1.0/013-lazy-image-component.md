# Create lazy loading image component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Frontend"

## Problem Statement

Lazy loading images improves initial page load. The package should provide an easy-to-use lazy image component.

## Proposed Solution

Create `<x-perf-lazy-image>` Blade component with native lazy loading and JavaScript fallback.

## Acceptance Criteria

- [ ] Create `src/View/Components/LazyImage.php`
- [ ] Create `resources/views/components/lazy-image.blade.php`
- [ ] Native `loading="lazy"` attribute
- [ ] `decoding="async"` attribute
- [ ] Configurable viewport threshold
- [ ] Placeholder support: dominant_color, blur, skeleton, none
- [ ] Fetchpriority attribute support
- [ ] JavaScript fallback for older browsers
- [ ] Auto-sizes support
- [ ] Width/height attributes for CLS prevention
- [ ] Custom class support
- [ ] Unit tests for component

## Use Cases

1. Developer uses `<x-perf-lazy-image src="..." />`
2. Browser lazy loads image when approaching viewport
3. Placeholder shows while image loads

## Additional Context

```blade
{{-- Basic usage --}}
<x-perf-lazy-image
    src="/images/hero.jpg"
    alt="Hero image"
    width="1200"
    height="600"
/>

{{-- With placeholder --}}
<x-perf-lazy-image
    src="/images/hero.jpg"
    alt="Hero"
    placeholder="dominant_color"
    :dominant-color="$image->dominant_color"
/>

{{-- High priority (LCP image) --}}
<x-perf-lazy-image
    src="/images/hero.jpg"
    fetchpriority="high"
    :lazy="false"
/>
```

---

**Related Issues:**
- #011 (Dominant Color)
