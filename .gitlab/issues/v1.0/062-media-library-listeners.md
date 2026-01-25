# Create media-library event listeners

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::9" ~"Area::Backend"

## Problem Statement

Images uploaded via media-library should be automatically optimized.

## Proposed Solution

Create event listeners that respond to media-library events and trigger optimization.

## Acceptance Criteria

- [ ] Listen to `MediaUploaded` event
- [ ] Dispatch optimization job on upload
- [ ] Generate WebP/AVIF formats
- [ ] Generate responsive sizes
- [ ] Extract dominant color
- [ ] Store optimization metadata
- [ ] Configurable per upload
- [ ] Unit tests for listeners

## Use Cases

1. Media uploaded → auto-optimize in background
2. Formats and sizes generated automatically
3. Dominant color extracted for placeholders

## Additional Context

```php
// Event listener
class OptimizeUploadedMedia
{
    public function handle(MediaUploaded $event): void
    {
        if (!config('artisanpack.performance.media_library_integration.optimize_on_upload')) {
            return;
        }

        OptimizeImageJob::dispatch($event->media, [
            'formats' => ['webp', 'avif'],
            'sizes' => config('artisanpack.performance.images.sizes'),
            'extract_dominant_color' => true,
        ]);
    }
}

// Register in service provider
Event::listen(MediaUploaded::class, OptimizeUploadedMedia::class);
```

**Processing Pipeline:**
1. Media uploaded → `MediaUploaded` event fired
2. Listener dispatches `OptimizeImageJob`
3. Job generates WebP, AVIF versions
4. Job generates responsive sizes
5. Job extracts dominant color
6. Metadata stored with media record

---

**Related Issues:**
- #061 (Detect Media Library)
- #015 (Queue Processing)
