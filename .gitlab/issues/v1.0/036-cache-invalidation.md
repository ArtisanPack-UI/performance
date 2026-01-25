# Implement cache invalidation

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Caches need to be invalidated when underlying data changes.

## Proposed Solution

Create `CacheInvalidator` with pattern-based and tag-based invalidation.

## Acceptance Criteria

- [ ] Create `src/Cache/CacheInvalidator.php`
- [ ] Pattern-based invalidation (wildcards)
- [ ] Tag-based invalidation
- [ ] Model event integration
- [ ] `perf:purge-cache` Artisan command
- [ ] Invalidation logging
- [ ] Unit tests for invalidation

## Use Cases

1. Invalidate product pages when product updated
2. Invalidate all homepage fragments
3. Purge entire cache on deployment

## Additional Context

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Invalidate specific pattern
Performance::invalidatePageCache('/products/*');
Performance::invalidateFragmentsByTag('products');

// Flush all caches
Performance::flushPageCache();

// Model event integration
class Product extends Model
{
    protected static function booted()
    {
        static::saved(fn() => Performance::invalidatePageCache('/products*'));
        static::saved(fn() => Performance::invalidateFragmentsByTag('products'));
    }
}
```

**Artisan Command:**
```bash
# Purge all caches
php artisan perf:purge-cache --type=all

# Purge page cache only
php artisan perf:purge-cache --type=page

# Purge by pattern
php artisan perf:purge-cache --pattern=/products/*
```

---

**Related Issues:**
- #032 (PageCacheManager)
- #034 (Fragment Caching)
