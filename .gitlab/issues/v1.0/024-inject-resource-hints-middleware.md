# Create InjectResourceHints middleware

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Resource hints should be automatically injected into HTML responses based on configuration.

## Proposed Solution

Create middleware that injects resource hints into the HTML `<head>`.

## Acceptance Criteria

- [ ] Create `src/Http/Middleware/InjectResourceHints.php`
- [ ] Inject preconnect hints for configured domains
- [ ] Inject dns-prefetch hints
- [ ] Inject preload hints for critical resources
- [ ] Auto-detect third-party domains (optional)
- [ ] Add Link headers for HTTP/2 push
- [ ] Skip for non-HTML responses
- [ ] Skip for excluded routes
- [ ] Unit tests for middleware

## Use Cases

1. Middleware adds configured preconnect links
2. Third-party domains auto-detected and hinted
3. Link headers sent for HTTP/2 push

## Additional Context

```php
// Apply to routes
Route::middleware('perf.resource-hints')->group(function () {
    // Routes with automatic resource hints
});

// Or globally in bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(InjectResourceHints::class);
})
```

**Injected HTML:**
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://www.google-analytics.com">
<link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2">
```

---

**Related Issues:**
- #021 (Resource Hints)
