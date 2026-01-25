# Create cache warming command

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Caches need to be pre-populated to avoid cold-start performance hits.

## Proposed Solution

Create `perf:warm-cache` Artisan command to warm page and fragment caches.

## Acceptance Criteria

- [ ] Create `src/Commands/WarmCacheCommand.php`
- [ ] Warm by route names
- [ ] Warm by URLs
- [ ] Warm from sitemap.xml
- [ ] Configurable concurrency
- [ ] Configurable delay between requests
- [ ] Progress bar during warming
- [ ] Report success/failure for each URL
- [ ] Unit tests for command

## Use Cases

1. Warm cache after deployment
2. Schedule warming hourly via cron
3. Warm specific critical pages

## Additional Context

```bash
# Warm page cache
php artisan perf:warm-cache --type=page

# Warm specific routes
php artisan perf:warm-cache --routes=home,products.index

# Warm from sitemap
php artisan perf:warm-cache --sitemap=public/sitemap.xml

# Warm with custom concurrency
php artisan perf:warm-cache --concurrent=10 --delay=50
```

**Scheduling:**
```php
// In routes/console.php or scheduler
Schedule::command('perf:warm-cache')->hourly();
```

**Config:**
```php
'cache_warming' => [
    'enabled' => true,
    'routes' => ['home', 'products.index', 'blog.index'],
    'urls' => ['/', '/products', '/about'],
    'concurrent_requests' => 5,
    'delay_ms' => 100,
],
```

---

**Related Issues:**
- #032 (PageCacheManager)
