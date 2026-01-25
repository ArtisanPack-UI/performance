# Implement WebP format conversion

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Modern browsers support WebP which provides better compression than JPEG/PNG. The package should convert images to WebP.

## Proposed Solution

Implement WebP conversion in `ImageService` with configurable quality and support for both GD and Imagick.

## Acceptance Criteria

- [ ] Create `FormatConverter` class
- [ ] GD-based WebP conversion
- [ ] Imagick-based WebP conversion
- [ ] Configurable quality (default 80)
- [ ] Preserve transparency for PNG sources
- [ ] Store WebP alongside original
- [ ] Return WebP path after conversion
- [ ] Handle conversion errors gracefully
- [ ] Unit tests for WebP conversion
- [ ] Artisan command: `php artisan perf:generate-webp`

## Use Cases

1. Developer converts uploaded image to WebP
2. Developer batch converts existing images
3. System serves WebP to supporting browsers

## Additional Context

```php
// Via service
$webpPath = $imageService->convertFormat($path, 'webp', 80);

// Via helper
$webpPath = perfConvertToWebP($path, 80);

// Via command
php artisan perf:generate-webp storage/images --quality=80 --recursive
```

**Browser Support**: 95%+ of browsers support WebP.

---

**Related Issues:**
- #008 (ImageService)
