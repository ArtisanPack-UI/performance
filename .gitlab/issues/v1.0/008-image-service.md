# Implement ImageService

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

The package needs a central service for all image optimization operations.

## Proposed Solution

Create `ImageService` class with methods for format conversion, resizing, and metadata extraction.

## Acceptance Criteria

- [ ] Create `src/Services/ImageService.php`
- [ ] `optimize($path, $options)` method
- [ ] `convertFormat($path, $format, $quality)` method
- [ ] `resize($path, $width, $height)` method
- [ ] `generateSrcset($path, $sizes)` method
- [ ] `extractDominantColor($path)` method
- [ ] `generatePlaceholder($path, $type)` method
- [ ] Support for GD and Imagick drivers
- [ ] Driver configuration via config
- [ ] Unit tests for all methods

## Use Cases

1. Developer optimizes uploaded image with all variants
2. Developer generates responsive srcset for existing image
3. Developer extracts dominant color for placeholder

## Additional Context

```php
use ArtisanPackUI\Performance\Services\ImageService;

$imageService = app(ImageService::class);

$result = $imageService->optimize($path, [
    'sizes' => [320, 640, 1024],
    'formats' => ['webp', 'avif'],
    'quality' => 80,
]);

$srcset = $imageService->generateSrcset($path, [320, 640, 1024]);
$color = $imageService->extractDominantColor($path);
```

---

**Related Issues:**
- #003 (Performance Facade)
