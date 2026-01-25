# Extend Media model with optimization methods

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::9" ~"Area::Backend"

## Problem Statement

Media model needs convenient methods to access optimized versions.

## Proposed Solution

Add methods to Media model via trait or macro for accessing optimized images.

## Acceptance Criteria

- [ ] `getOptimizedUrl($format, $size)` method
- [ ] `getSrcset()` method
- [ ] `getDominantColor()` method
- [ ] `isOptimized()` method
- [ ] `getOptimizationStatus()` method
- [ ] Methods added via trait or macro
- [ ] Blade integration for easy use
- [ ] Unit tests for methods

## Use Cases

1. Get WebP URL for specific size
2. Get complete srcset string
3. Check if optimization is complete

## Additional Context

```php
// Using the Media model
$media = Media::find($id);

// Get optimized URL
$webpUrl = $media->getOptimizedUrl('webp', 'large');

// Get srcset
$srcset = $media->getSrcset();
// "image-320.webp 320w, image-640.webp 640w, ..."

// Get dominant color
$color = $media->getDominantColor(); // '#3b82f6'

// Check status
if ($media->isOptimized()) {
    // Use optimized version
}
```

**Blade Usage:**
```blade
<x-perf-responsive-image
    :media="$media"
    alt="{{ $media->alt_text }}"
/>

{{-- Component reads from media model methods --}}
```

---

**Related Issues:**
- #062 (Media Library Listeners)
