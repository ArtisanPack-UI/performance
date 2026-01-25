# Create responsive image component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Frontend"

## Problem Statement

Serving responsive images with proper srcset and picture element is complex. The package should provide a simple component.

## Proposed Solution

Create `<x-perf-responsive-image>` component that outputs `<picture>` element with format and size variants.

## Acceptance Criteria

- [ ] Create `src/View/Components/ResponsiveImage.php`
- [ ] Create `resources/views/components/responsive-image.blade.php`
- [ ] Output `<picture>` element
- [ ] AVIF source (if available)
- [ ] WebP source (if available)
- [ ] Original format fallback
- [ ] Configurable sizes attribute
- [ ] Configurable breakpoint sizes
- [ ] Integration with lazy loading
- [ ] Dominant color placeholder
- [ ] Custom class support for picture and img
- [ ] Unit tests for component

## Use Cases

1. Developer uses `<x-perf-responsive-image>` for hero images
2. Browser selects best format and size automatically
3. Falls back to original for unsupported browsers

## Additional Context

```blade
<x-perf-responsive-image
    src="/images/hero.jpg"
    alt="Hero image"
    :sizes="['sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280]"
    sizes-attr="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
/>
```

**Output:**
```html
<picture>
    <source type="image/avif" srcset="hero-640.avif 640w, ..." sizes="...">
    <source type="image/webp" srcset="hero-640.webp 640w, ..." sizes="...">
    <img src="hero-1024.jpg" alt="Hero image" loading="lazy" decoding="async">
</picture>
```

---

**Related Issues:**
- #012 (Responsive Generation)
- #013 (Lazy Image Component)
