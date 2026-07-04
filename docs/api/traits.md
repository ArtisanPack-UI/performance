# Eloquent traits

Traits live under `ArtisanPackUI\Performance\Traits`.

## `HasOptimizedImages`

Mixes the model-level image optimization surface into any Eloquent model. Callers declare which attributes hold image paths (and how to optimize them) and read URLs, srcsets, and dominant colors back through convenience accessors.

```php
use ArtisanPackUI\Performance\Traits\HasOptimizedImages;

class Product extends Model
{
    use HasOptimizedImages;

    protected function optimizableImages(): array
    {
        return [
            'hero_image' => [
                'sizes'                  => [320, 640, 1024],
                'formats'                => ['webp', 'avif'],
                'quality'                => 80,
                'extract_dominant_color' => true,
                'auto_optimize'          => true,
            ],
        ];
    }
}
```

Accessors:

| Method | Purpose |
|---|---|
| `getOptimizedImageUrl(string $attribute, string $format, int $size): string` | URL of one variant |
| `getImageSrcset(string $attribute, string $format = 'webp'): string` | `srcset` string across the configured sizes |
| `getImageDominantColor(string $attribute): ?string` | Cached dominant color hex |

`auto_optimize: true` dispatches `OptimizeImageJob` whenever the attribute changes on save.

## `HasOptimizedMedia`

Add-on trait for the `artisanpack-ui/media-library` `Media` model that exposes the optimization metadata written by `OptimizeUploadedMedia` as accessors.

```php
use ArtisanPackUI\MediaLibrary\Models\Media as BaseMedia;
use ArtisanPackUI\Performance\Traits\HasOptimizedMedia;

class OptimizedMedia extends BaseMedia
{
    use HasOptimizedMedia;
}
```

Accessors:

| Method | Purpose |
|---|---|
| `isOptimized(): bool` | `true` when `optimization_status === 'completed'` |
| `getOptimizationStatus(): string` | `pending` \| `processing` \| `completed` \| `failed` |
| `getDominantColor(): ?string` | Hex `#rrggbb` or null |
| `getOptimizedUrl(string $format, ?int $width = null): ?string` | Format-only lookup returns the largest width; format + width picks a specific variant |
| `getSrcset(string $format): string` | `srcset` across every generated width for a format |

The trait auto-registers casts for `optimized_formats`, `optimized_sizes`, and `optimized_at` via `initializeHasOptimizedMedia()`, so the columns don't need to be declared on the model.

## `CachesQueries`

Mix into an Eloquent model to opt the model into the Performance package's query cache. Wires the model's `newEloquentBuilder()` to return `CachingEloquentBuilder`, which adds `cacheFor()` / `cacheTags()` to the fluent builder API.

```php
use ArtisanPackUI\Performance\Traits\CachesQueries;

class Report extends Model
{
    use CachesQueries;
}

Report::query()
    ->cacheFor(3600)
    ->cacheTags(['reports'])
    ->where('year', 2026)
    ->get();
```

Invalidate by tag when data changes:

```php
Performance::invalidateFragmentsByTag('reports');
```
