# Create page cache middleware

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Page caching should be applied transparently via middleware.

## Proposed Solution

Create `PageCache` middleware that checks for cached responses and stores new ones.

## Acceptance Criteria

- [ ] Create `src/Http/Middleware/PageCache.php`
- [ ] Check for cached response first
- [ ] Return cached response if available
- [ ] Capture response and cache if cacheable
- [ ] Skip for POST/PUT/DELETE requests
- [ ] Skip for authenticated users (if configured)
- [ ] Skip for routes with flash messages
- [ ] Add cache headers to response
- [ ] Log cache hits/misses
- [ ] Unit tests for middleware

## Use Cases

1. Apply to public routes for instant responses
2. Skip caching for logged-in users
3. Return cached page in milliseconds

## Additional Context

```php
// Via middleware
Route::middleware('perf.page-cache')->group(function () {
    Route::get('/', HomeController::class);
    Route::get('/products', [ProductController::class, 'index']);
});

// Or via attribute
#[PageCache(ttl: 3600)]
public function index()
{
    return view('products.index');
}
```

**Response Flow:**
1. Check if request is cacheable
2. Check if cached response exists
3. If cached: return immediately (< 10ms)
4. If not: execute controller, cache response, return

---

**Related Issues:**
- #032 (PageCacheManager)
