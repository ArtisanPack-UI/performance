# Media library integration

When [`artisanpack-ui/media-library`](https://github.com/ArtisanPack-UI/media-library) is installed, the Performance package automatically optimizes uploaded images whose file resolves to a local filesystem path. Non-image uploads are skipped.

## 1. How detection works

At boot, `MediaLibraryDetector` inspects the application autoloader. The result flows into config:

```php
'media_library_integration' => [
    'enabled'                    => null,   // null = auto-detect, true/false to force
    'optimize_on_upload'         => true,
    'generate_formats_on_upload' => true,
],
```

Check the resolved state at any time:

```php
app(\ArtisanPackUI\Performance\Services\MediaLibraryDetector::class)->status();
// ['installed' => true, 'enabled' => true, 'source' => 'auto']
```

If `installed` is false, the media-library package isn't autoloadable. If `enabled` is false, the config value is `false` — set it to `null` for auto-detect or `true` to force on.

## 2. Automatic optimization on upload

`OptimizeUploadedMedia` listens for the media-library `Media` model's `created` event (and `MediaUploaded` if the future event class exists) and dispatches `OptimizeMediaJob`. The job writes back to the row:

| Column | Written |
|---|---|
| `dominant_color` | Hex `#rrggbb` |
| `optimization_status` | `pending` → `processing` → `completed` / `failed` |
| `optimized_at` | Completion timestamp |
| `optimized_formats` | JSON `['webp', 'avif']` |
| `optimized_sizes` | JSON `[320, 640, 1024]` |

The migration that adds these columns is a no-op when the `media` table is absent, so applications without media-library never fail their migrate step.

## 3. Expose the metadata

Extend the media-library `Media` model in your app and mix in the trait:

```php
namespace App\Models;

use ArtisanPackUI\MediaLibrary\Models\Media as BaseMedia;
use ArtisanPackUI\Performance\Traits\HasOptimizedMedia;

class OptimizedMedia extends BaseMedia
{
    use HasOptimizedMedia;
}
```

Then read the optimization surface back:

```php
$media = OptimizedMedia::find(1);

$media->isOptimized();                     // bool
$media->getOptimizationStatus();           // 'pending' | 'processing' | 'completed' | 'failed'
$media->getDominantColor();                // '#3b82f6' or null
$media->getOptimizedUrl('webp');           // largest-width URL for the format
$media->getOptimizedUrl('webp', 640);      // specific width
$media->getSrcset('webp');                 // '<url> 320w, <url> 640w, …'
```

The trait auto-registers the casts for `optimized_formats`, `optimized_sizes`, and `optimized_at` via `initializeHasOptimizedMedia()`, so you don't have to declare them on the model yourself.

## 4. Remote disks

The current implementation reads the source file from a disk exposing a local `path()`. Remote disks (S3, GCS) are supported for **storage**, but the optimization job needs a local copy — download to a temp file and dispatch `OptimizeImageJob` against that path in the interim. Native remote-disk support is on the roadmap.

## 5. Re-queue existing rows

Rows uploaded before the migration ran will keep `optimization_status = pending` until they're re-uploaded or manually re-queued:

```php
use ArtisanPackUI\Performance\Jobs\OptimizeMediaJob;

OptimizedMedia::where('optimization_status', 'pending')
    ->each(fn ($media) => OptimizeMediaJob::dispatch($media));
```

## 6. Force-toggle the integration

Set `media_library_integration.enabled` explicitly to override auto-detection:

```php
'media_library_integration' => [
    'enabled' => false, // disable even when media-library is installed
],
```

## Related

- [[api/services]] — `MediaLibraryDetector`
- [[api/traits]] — `HasOptimizedMedia`
- [[api/events]] — `ImageOptimized`
- [[guides/image-optimization]] — Underlying image pipeline
