# Implement PageCacheManager

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Caching entire HTML responses dramatically improves TTFB. A central manager is needed.

## Proposed Solution

Create `PageCacheManager` for full-page caching with configurable strategies.

## Acceptance Criteria

- [ ] Create `src/Cache/PageCacheManager.php`
- [ ] `cacheResponse(Request, Response)` method
- [ ] `getCachedResponse(Request)` method
- [ ] `invalidatePageCache(pattern)` method
- [ ] `warmPageCache(urls)` method
- [ ] Cache key generation from request
- [ ] TTL configuration
- [ ] Vary by headers (Accept-Encoding, etc.)
- [ ] Exclude routes configuration
- [ ] Exclude when authenticated option
- [ ] Unit tests for cache manager

## Use Cases

1. Cache homepage for 1 hour
2. Invalidate product pages when product updated
3. Warm cache for critical pages

## Additional Context

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Invalidate specific page
Performance::invalidatePageCache('/products');

// Invalidate by pattern
Performance::invalidatePageCache('/products/*');

// Invalidate all
Performance::flushPageCache();

// Warm cache
Performance::warmPageCache(['/products', '/about']);
```

**Config:**
```php
'page_cache' => [
    'enabled' => true,
    'driver' => 'file', // file, redis, memcached
    'ttl' => 3600,
    'exclude_routes' => ['admin/*', 'user/*'],
    'exclude_when' => ['authenticated', 'has_flash'],
    'vary_by' => ['Accept-Encoding'],
],
```

---

**Related Issues:**
- #002 (Configuration)
