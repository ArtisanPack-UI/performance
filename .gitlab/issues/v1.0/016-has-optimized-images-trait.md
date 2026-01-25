# Create HasOptimizedImages trait

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Models with image fields should have easy access to optimized image URLs and metadata.

## Proposed Solution

Create `HasOptimizedImages` trait that can be added to Eloquent models.

## Acceptance Criteria

- [ ] Create `src/Traits/HasOptimizedImages.php`
- [ ] Define optimizable images via `optimizableImages()` method
- [ ] `getOptimizedImageUrl($field, $format, $size)` method
- [ ] `getImageSrcset($field)` method
- [ ] `getImageDominantColor($field)` method
- [ ] Automatic optimization on model events (optional)
- [ ] Support for multiple image fields per model
- [ ] Unit tests for trait methods

## Use Cases

1. Developer adds trait to Post model
2. Access optimized images via `$post->getOptimizedImageUrl('featured_image', 'webp', 640)`
3. Get srcset for responsive images

## Additional Context

```php
use ArtisanPackUI\Performance\Traits\HasOptimizedImages;

class Post extends Model
{
    use HasOptimizedImages;

    protected function optimizableImages(): array
    {
        return [
            'featured_image' => [
                'sizes' => [640, 768, 1024, 1280, 1920],
                'formats' => ['webp', 'avif'],
                'quality' => 80,
                'extract_dominant_color' => true,
            ],
            'thumbnail' => [
                'sizes' => [150, 300],
                'formats' => ['webp'],
            ],
        ];
    }
}

// Usage
$url = $post->getOptimizedImageUrl('featured_image', 'webp', 640);
$srcset = $post->getImageSrcset('featured_image');
$color = $post->getImageDominantColor('featured_image');
```

---

**Related Issues:**
- #008 (ImageService)
- #012 (Responsive Generation)
