# Implement responsive image generation

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Serving appropriately sized images for different viewports improves performance. The package should generate multiple sizes.

## Proposed Solution

Create `ResponsiveImageGenerator` to generate multiple size variants of images.

## Acceptance Criteria

- [ ] Create `src/Images/ResponsiveImageGenerator.php`
- [ ] Generate images at configurable breakpoint sizes
- [ ] Default sizes: 320, 640, 768, 1024, 1280, 1920
- [ ] Maintain aspect ratio during resize
- [ ] Generate srcset string for use in views
- [ ] Generate sizes at multiple formats (original + WebP + AVIF)
- [ ] Store generated sizes metadata
- [ ] Unit tests for responsive generation

## Use Cases

1. Developer generates responsive versions on upload
2. Package outputs `<picture>` element with srcset
3. Browser selects appropriate size automatically

## Additional Context

```php
// Generate all sizes
$sizes = $imageService->generateResponsiveSizes($path, [320, 640, 1024, 1280]);

// Returns array of paths
[
    320 => '/images/hero-320.jpg',
    640 => '/images/hero-640.jpg',
    // ...
]

// Get srcset string
$srcset = $imageService->generateSrcset($path, [320, 640, 1024]);
// Returns: "hero-320.jpg 320w, hero-640.jpg 640w, hero-1024.jpg 1024w"
```

---

**Related Issues:**
- #008 (ImageService)
