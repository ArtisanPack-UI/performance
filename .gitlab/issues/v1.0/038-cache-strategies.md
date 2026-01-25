# Implement cache storage strategies

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Different environments need different cache storage backends (file, Redis, Memcached).

## Proposed Solution

Create cache strategy classes that implement a common interface.

## Acceptance Criteria

- [ ] Create `src/Contracts/CacheStrategy.php` interface
- [ ] Create `src/Cache/Strategies/FileCacheStrategy.php`
- [ ] Create `src/Cache/Strategies/RedisCacheStrategy.php`
- [ ] Create `src/Cache/Strategies/MemcachedCacheStrategy.php`
- [ ] Strategy selection via configuration
- [ ] Environment variable support
- [ ] Unit tests for each strategy

## Use Cases

1. Use file cache in development
2. Use Redis in production
3. Switch strategies via environment variable

## Additional Context

```php
// Interface
interface CacheStrategy
{
    public function get(string $key): ?string;
    public function put(string $key, string $value, int $ttl): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function tags(array $tags): static;
}
```

**Config:**
```php
'page_cache' => [
    'driver' => env('PERF_PAGE_CACHE_DRIVER', 'file'), // file, redis, memcached
],
'fragment_cache' => [
    'driver' => env('PERF_FRAGMENT_CACHE_DRIVER', 'file'),
],
'query_cache' => [
    'driver' => env('PERF_QUERY_CACHE_DRIVER', 'redis'),
],
```

---

**Related Issues:**
- #032 (PageCacheManager)
- #034 (Fragment Caching)
