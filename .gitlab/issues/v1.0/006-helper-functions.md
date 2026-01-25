# Create global helper functions

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::1" ~"Area::Backend"

## Problem Statement

The package should provide convenient global helper functions for common operations.

## Proposed Solution

Create `helpers.php` with global functions prefixed with `perf`.

## Acceptance Criteria

- [ ] Create `src/helpers.php`
- [ ] Autoload helpers via Composer
- [ ] Image helpers: `perfOptimizeImage()`, `perfConvertToWebP()`, `perfConvertToAvif()`, `perfGetDominantColor()`, `perfGetResponsiveSrcset()`
- [ ] Cache helpers: `perfRemember()`, `perfRememberForever()`, `perfInvalidateCache()`, `perfFlushCache()`
- [ ] Monitoring helpers: `perfRecordMetric()`, `perfGetRecommendations()`
- [ ] Feature check: `perfFeatureEnabled()`
- [ ] All helpers delegate to facade/service
- [ ] Unit tests for all helpers

## Use Cases

1. Developer uses `perfRemember('key', 3600, fn() => expensive())`
2. Developer uses `perfGetDominantColor($imagePath)`
3. Developer checks `perfFeatureEnabled('image_optimization')`

## Additional Context

```php
// Image helpers
$webp = perfConvertToWebP($path, 80);
$color = perfGetDominantColor($path);
$srcset = perfGetResponsiveSrcset($path, [320, 640, 1024]);

// Cache helpers
$data = perfRemember('key', 3600, fn() => calculate());
perfInvalidateCache('products/*');

// Feature checks
if (perfFeatureEnabled('monitoring')) {
    perfRecordMetric('custom_metric', $value);
}
```

---

**Related Issues:**
- #003 (Performance Facade)
