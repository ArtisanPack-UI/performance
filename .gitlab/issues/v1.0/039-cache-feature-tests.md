# Create caching system feature tests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Caching features need comprehensive tests across different strategies.

## Proposed Solution

Create feature tests for all Phase 5 functionality.

## Acceptance Criteria

- [ ] Tests for PageCacheManager
- [ ] Tests for page cache middleware
- [ ] Tests for fragment caching directive
- [ ] Tests for cache warming command
- [ ] Tests for cache invalidation
- [ ] Tests for CachesQueries trait
- [ ] Tests for each cache strategy
- [ ] Tests for cache hit/miss scenarios
- [ ] All tests pass

## Use Cases

1. CI validates caching works correctly
2. Developers run tests after changes
3. Tests catch regressions

## Additional Context

```php
it('caches page response', function () {
    $response1 = $this->get('/products');
    $response2 = $this->get('/products');

    expect($response2->headers->get('X-Cache'))->toBe('HIT');
});

it('invalidates cache by pattern', function () {
    $this->get('/products'); // Prime cache

    Performance::invalidatePageCache('/products*');

    $response = $this->get('/products');
    expect($response->headers->get('X-Cache'))->toBe('MISS');
});

it('caches fragment content', function () {
    $html1 = Blade::render('@cache("test", 3600) expensive @endcache');
    $html2 = Blade::render('@cache("test", 3600) expensive @endcache');

    expect(Cache::has('fragment:test'))->toBeTrue();
});

it('warms cache for routes', function () {
    $this->artisan('perf:warm-cache', ['--routes' => 'home,products.index'])
        ->assertSuccessful();
});
```

---

**Related Issues:**
All Phase 5 issues
