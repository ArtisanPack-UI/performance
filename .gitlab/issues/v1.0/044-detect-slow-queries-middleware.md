# Create DetectSlowQueries middleware

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::6" ~"Area::Backend"

## Problem Statement

Query analysis should run automatically during request lifecycle.

## Proposed Solution

Create middleware that enables query logging and analysis for each request.

## Acceptance Criteria

- [ ] Create `src/Http/Middleware/DetectSlowQueries.php`
- [ ] Enable query logging at request start
- [ ] Analyze queries at request end
- [ ] Detect N+1 patterns
- [ ] Log slow queries
- [ ] Fire events for detected issues
- [ ] Skip for excluded routes
- [ ] Unit tests for middleware

## Use Cases

1. Automatic N+1 detection for all requests
2. Slow query logging per request
3. Development-only detection mode

## Additional Context

```php
// Via middleware group
Route::middleware('perf.detect-queries')->group(function () {
    // Routes with query detection
});

// Or globally (development only)
// in bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    if (app()->environment('local')) {
        $middleware->append(DetectSlowQueries::class);
    }
})
```

**Middleware Flow:**
1. Start of request: Enable query logging
2. End of request: Analyze all queries
3. Log slow queries (> threshold)
4. Detect N+1 patterns
5. Fire events if issues found

---

**Related Issues:**
- #041 (N+1 Detection)
- #042 (Slow Query Logging)
