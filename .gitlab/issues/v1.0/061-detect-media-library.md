# Detect media-library installation

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::9" ~"Area::Backend"

## Problem Statement

The performance package should automatically integrate with artisanpack-ui/media-library when installed.

## Proposed Solution

Create detection logic that checks for media-library installation and enables integration.

## Acceptance Criteria

- [ ] Check if `artisanpack-ui/media-library` is installed
- [ ] Configuration option to override detection
- [ ] Graceful degradation when not installed
- [ ] Log integration status at boot
- [ ] Unit tests for detection logic

## Use Cases

1. Media library installed → auto-enable integration
2. Media library not installed → skip integration
3. Developer can force disable via config

## Additional Context

```php
// In PerformanceServiceProvider
public function boot(): void
{
    if ($this->shouldIntegrateWithMediaLibrary()) {
        $this->registerMediaLibraryListeners();
    }
}

private function shouldIntegrateWithMediaLibrary(): bool
{
    // Check config override
    $configValue = config('artisanpack.performance.media_library_integration.enabled');
    if ($configValue !== null) {
        return $configValue;
    }

    // Auto-detect
    return class_exists(\ArtisanPackUI\MediaLibrary\MediaLibraryServiceProvider::class);
}
```

**Config:**
```php
'media_library_integration' => [
    'enabled' => true, // null for auto-detect, true/false to override
    'optimize_on_upload' => true,
    'generate_formats_on_upload' => true,
],
```

---

**Related Issues:**
- #001 (Package Setup)
