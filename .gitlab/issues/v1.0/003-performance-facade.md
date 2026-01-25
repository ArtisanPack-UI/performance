# Implement Performance facade and service

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::1" ~"Area::Backend"

## Problem Statement

The package needs a central facade and service class to provide a unified API for all performance features.

## Proposed Solution

Create `Performance` facade and `PerformanceService` class with methods for all package features.

## Acceptance Criteria

- [ ] Create `src/Facades/Performance.php` facade
- [ ] Create `src/Services/PerformanceService.php`
- [ ] Register facade in service provider
- [ ] Image optimization methods
- [ ] Script management methods
- [ ] Cache helper methods
- [ ] Monitoring methods
- [ ] Feature check methods
- [ ] Unit tests for facade resolution

## Use Cases

1. Developer uses `Performance::optimizeImage($path)`
2. Developer uses `Performance::script('/js/app.js')->defer()`
3. Developer uses `Performance::remember($key, $ttl, $callback)`

## Additional Context

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Image operations
Performance::optimizeImage($path, $options);
Performance::convertToWebP($path);

// Script management
Performance::script('/js/app.js')->defer();

// Caching
Performance::remember('key', 3600, fn() => expensive());

// Monitoring
Performance::recordMetric('custom', $value);
```

---

**Related Issues:**
- #001 (Package Setup)
