# Implement queue-based image processing

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Image optimization can be slow. Processing should happen in the background via Laravel queues.

## Proposed Solution

Create queued jobs for image optimization that can run in the background.

## Acceptance Criteria

- [ ] Create `OptimizeImageJob` queued job
- [ ] Create `ConvertImageFormatJob` for format conversion
- [ ] Create `GenerateResponsiveSizesJob` for size generation
- [ ] Configurable queue name via config
- [ ] Job chaining for complete optimization pipeline
- [ ] Dispatch events when jobs complete
- [ ] Handle job failures gracefully
- [ ] Retry configuration
- [ ] Unit tests for job dispatching

## Use Cases

1. Image uploaded → optimization job dispatched
2. Job runs in background without blocking request
3. Events fired when optimization complete

## Additional Context

```php
// Dispatch optimization job
OptimizeImageJob::dispatch($imagePath, [
    'sizes' => [320, 640, 1024],
    'formats' => ['webp', 'avif'],
    'extract_dominant_color' => true,
]);

// Job chain for complete processing
Bus::chain([
    new ConvertImageFormatJob($path, 'webp'),
    new ConvertImageFormatJob($path, 'avif'),
    new GenerateResponsiveSizesJob($path, [320, 640, 1024]),
])->dispatch();
```

**Config:**
```php
'images' => [
    'queue' => env('PERF_IMAGE_QUEUE', 'default'),
]
```

---

**Related Issues:**
- #008 (ImageService)
- #005 (Events System)
