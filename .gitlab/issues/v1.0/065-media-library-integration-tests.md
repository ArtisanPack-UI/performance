# Create media library integration tests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::9" ~"Area::Backend"

## Problem Statement

Media library integration needs comprehensive tests.

## Proposed Solution

Create feature tests for all Phase 9 functionality.

## Acceptance Criteria

- [ ] Tests for detection logic
- [ ] Tests for event listeners
- [ ] Tests for extended Media methods
- [ ] Tests for optimization job
- [ ] Tests for migration
- [ ] Tests when media-library not installed
- [ ] All tests pass

## Use Cases

1. CI validates integration works correctly
2. Developers run tests after changes
3. Tests catch regressions

## Additional Context

```php
it('detects media library installation', function () {
    expect(class_exists(\ArtisanPackUI\MediaLibrary\MediaLibraryServiceProvider::class))
        ->toBeTrue();
});

it('optimizes uploaded media', function () {
    $media = Media::factory()->create();

    event(new MediaUploaded($media));

    Queue::assertPushed(OptimizeImageJob::class);
});

it('adds optimization methods to Media model', function () {
    $media = Media::factory()->create([
        'dominant_color' => '#3b82f6',
        'optimized_formats' => ['webp' => '/path/to/webp'],
    ]);

    expect($media->getDominantColor())->toBe('#3b82f6');
    expect($media->getOptimizedUrl('webp'))->toContain('webp');
});

it('handles missing media library gracefully', function () {
    // Mock class_exists to return false
    config(['artisanpack.performance.media_library_integration.enabled' => false]);

    // Should not throw
    expect(fn() => app(PerformanceServiceProvider::class))
        ->not->toThrow(Exception::class);
});
```

---

**Related Issues:**
All Phase 9 issues
