# Implement dominant color extraction

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Showing the dominant color of an image while it loads reduces CLS and improves perceived performance.

## Proposed Solution

Create `DominantColorExtractor` class to analyze images and return their dominant color.

## Acceptance Criteria

- [ ] Create `src/Images/DominantColorExtractor.php`
- [ ] Average color algorithm (fast)
- [ ] Quantize algorithm (accurate)
- [ ] Configurable algorithm via config
- [ ] Return hex color string (e.g., '#3b82f6')
- [ ] Cache extracted colors in database
- [ ] Handle transparent images
- [ ] Unit tests for color extraction

## Use Cases

1. Developer extracts color during image upload
2. Color is stored with image metadata
3. Color is used as placeholder background

## Additional Context

```php
// Via service
$color = $imageService->extractDominantColor($path); // '#3b82f6'

// Via helper
$color = perfGetDominantColor($path);

// Store with model
$media->dominant_color = $color;

// Use in view
<img src="{{ $url }}" style="background-color: {{ $color }};" loading="lazy">
```

**Algorithms**:
- `average`: Fast, samples pixels and averages RGB values
- `quantize`: More accurate, uses color quantization

---

**Related Issues:**
- #008 (ImageService)
