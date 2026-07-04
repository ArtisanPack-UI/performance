# Image optimization

Snippets covering WebP/AVIF conversion, responsive images, lazy
loading, and registering custom image sizes.

## Enable the feature

`config/artisanpack/performance.php`:

```php
'features' => [
    'image_optimization' => true,
    'lazy_loading'       => true,
],
```

## Examples

- [`webp-avif-conversion.php`](webp-avif-conversion.php) — bulk convert existing images to WebP + AVIF via the `perf:generate-webp` command and the queued job pipeline
- [`responsive-images.blade.php`](responsive-images.blade.php) — `<x-perf-responsive-image>` with automatic `srcset` and `sizes`
- [`lazy-loading.blade.php`](lazy-loading.blade.php) — lazy-loaded image with a dominant-color placeholder
- [`custom-sizes.php`](custom-sizes.php) — register a `product-thumb` image size and use it in a Blade view
