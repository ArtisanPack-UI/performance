# Create CachesQueries trait

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Caching Eloquent query results is a common need. A trait should make this easy.

## Proposed Solution

Create `CachesQueries` trait for Eloquent models.

## Acceptance Criteria

- [ ] Create `src/Traits/CachesQueries.php`
- [ ] `cacheFor($ttl)` query scope
- [ ] Automatic cache key from query
- [ ] Cache tags based on model
- [ ] Automatic invalidation on model changes
- [ ] Support for all query builder methods
- [ ] Unit tests for trait

## Use Cases

1. Cache expensive aggregation queries
2. Cache frequently accessed lookup queries
3. Auto-invalidate when model updated

## Additional Context

```php
use ArtisanPackUI\Performance\Traits\CachesQueries;

class Post extends Model
{
    use CachesQueries;
}

// Usage
$posts = Post::cacheFor(3600)->popular()->get();
$post = Post::cacheFor(3600)->find($id);
$count = Post::cacheFor(3600)->count();

// Custom cache key
$posts = Post::cacheFor(3600, 'popular-posts')->popular()->get();

// With tags
$posts = Post::cacheFor(3600)->cacheTags(['posts', 'homepage'])->popular()->get();
```

**Auto-invalidation:**
When a Post is created/updated/deleted, related caches are automatically invalidated based on the cache tags.

---

**Related Issues:**
- #002 (Configuration)
