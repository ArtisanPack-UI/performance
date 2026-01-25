# Create image optimization feature tests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Image optimization features need comprehensive tests to ensure reliability across different configurations.

## Proposed Solution

Create feature tests for all image optimization functionality.

## Acceptance Criteria

- [ ] Tests for WebP conversion (GD and Imagick)
- [ ] Tests for AVIF conversion
- [ ] Tests for dominant color extraction
- [ ] Tests for responsive size generation
- [ ] Tests for lazy image component rendering
- [ ] Tests for responsive image component rendering
- [ ] Tests for HasOptimizedImages trait
- [ ] Tests for queued job processing
- [ ] Tests for image optimization command
- [ ] Tests with various image formats (JPEG, PNG, GIF)
- [ ] All tests pass

## Use Cases

1. CI validates image optimization works correctly
2. Developers run tests after changes
3. Tests catch regressions in processing

## Additional Context

```php
it('converts image to webp', function () {
    $path = createTestImage('jpeg');

    $webpPath = $imageService->convertFormat($path, 'webp', 80);

    expect(file_exists($webpPath))->toBeTrue();
    expect(mime_content_type($webpPath))->toBe('image/webp');
});

it('extracts dominant color', function () {
    $path = createBlueTestImage();

    $color = $imageService->extractDominantColor($path);

    expect($color)->toMatch('/^#[0-9a-f]{6}$/i');
});

it('renders lazy image component', function () {
    $html = Blade::render('<x-perf-lazy-image src="/test.jpg" alt="Test" />');

    expect($html)->toContain('loading="lazy"');
    expect($html)->toContain('decoding="async"');
});
```

---

**Related Issues:**
All Phase 2 issues
