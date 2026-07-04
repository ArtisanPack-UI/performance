# Image optimization

Convert JPEG/PNG uploads to WebP or AVIF, generate responsive sizes, and extract a dominant color for use as an LQIP placeholder.

## 1. Enable the feature

```dotenv
PERF_IMAGE_OPTIMIZATION=true
```

Check the driver capability at runtime:

```php
Performance::images()->supportsFormat('avif'); // bool
```

`ext-gd` covers WebP on most hosts; AVIF typically needs `ext-imagick` linked against libheif.

## 2. Optimize an image on demand

```php
use ArtisanPackUI\Performance\Facades\Performance;

$result = Performance::optimizeImage($path, [
    'sizes'   => [320, 640, 1024],
    'formats' => ['webp', 'avif'],
    'quality' => 80,
]);
```

`$result` contains every generated variant path keyed by width and format. The `ImageOptimized` event fires on success.

Convert directly for one-off use:

```php
$webp = Performance::convertToWebP($path, 80);
$avif = Performance::convertToAvif($path, 70);
```

Or use the Blade-safe helpers, which return the source path when the driver can't encode the target format:

```php
$src = perfConvertToWebP($path);
```

## 3. Extract a dominant color

Use the color as an inline `background-color` while the full image loads:

```php
$color = Performance::getDominantColor($path); // '#3b82f6'
```

## 4. Generate a responsive `srcset`

```php
$srcset = Performance::getResponsiveSrcset($path, [320, 640, 1024]);
```

## 5. Optimize in the background

Queue the pipeline so uploads return immediately:

```php
use ArtisanPackUI\Performance\Jobs\OptimizeImageJob;

OptimizeImageJob::dispatch($path, [
    'sizes'   => [320, 640, 1024],
    'formats' => ['webp', 'avif'],
]);
```

## 6. Model integration

Attach the `HasOptimizedImages` trait to any Eloquent model that owns image attributes:

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

$product->getOptimizedImageUrl('hero_image', 'webp', 640);
$product->getImageSrcset('hero_image', 'webp');
$product->getImageDominantColor('hero_image');
```

`auto_optimize: true` dispatches `OptimizeImageJob` whenever the attribute changes on save.

## 7. Render in Blade

```blade
<x-perf-responsive-image
    src="/images/hero.jpg"
    :sizes="[320, 640, 1024]"
    formats="webp,avif"
    alt="Hero"
/>

<x-perf-lazy-image
    src="/images/product.jpg"
    dominant-color="#3b82f6"
    alt="Product"
/>
```

## 8. Bulk-convert an existing library

```bash
php artisan perf:generate-webp public/images --recursive
```

Add `--quality=` to override the configured quality, or `--force` to overwrite existing WebP files.

## Related

- [[api/services]] — `PerformanceService`, `ImageService`, `ResponsiveImageGenerator`
- [[api/traits]] — `HasOptimizedImages`
- [[api/events]] — `ImageOptimized`
- [[guides/media-library-integration]] — Automatic optimization for media-library uploads
