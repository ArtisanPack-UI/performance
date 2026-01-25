# Implement AVIF format conversion

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

AVIF provides even better compression than WebP. The package should support AVIF conversion for maximum optimization.

## Proposed Solution

Implement AVIF conversion in `FormatConverter` with Imagick support (GD has limited AVIF support).

## Acceptance Criteria

- [ ] Imagick-based AVIF conversion
- [ ] GD AVIF conversion (PHP 8.1+ with libavif)
- [ ] Configurable quality (default 70)
- [ ] Graceful fallback when AVIF not supported
- [ ] Check for AVIF support at runtime
- [ ] Store AVIF alongside original and WebP
- [ ] Unit tests for AVIF conversion
- [ ] Skip AVIF if extension not available

## Use Cases

1. Developer converts images to AVIF for modern browsers
2. System falls back to WebP if AVIF not supported
3. Package checks AVIF capability at install

## Additional Context

```php
// Via service
$avifPath = $imageService->convertFormat($path, 'avif', 70);

// Via helper
$avifPath = perfConvertToAvif($path, 70);

// Check support
if ($imageService->supportsFormat('avif')) {
    // Generate AVIF
}
```

**Browser Support**: 85%+ of browsers support AVIF.
**Note**: AVIF requires Imagick with AVIF support or PHP 8.1+ with libavif.

---

**Related Issues:**
- #008 (ImageService)
- #009 (WebP Conversion)
