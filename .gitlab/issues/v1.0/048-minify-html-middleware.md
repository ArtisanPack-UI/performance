# Create MinifyHtml middleware

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::7" ~"Area::Backend"

## Problem Statement

HTML minification should be applied transparently via middleware.

## Proposed Solution

Create `MinifyHtml` middleware that minifies HTML responses.

## Acceptance Criteria

- [ ] Create `src/Http/Middleware/MinifyHtml.php`
- [ ] Check if response is HTML
- [ ] Apply minification
- [ ] Skip for excluded routes
- [ ] Skip for non-HTML responses
- [ ] Skip for API routes
- [ ] Update Content-Length header
- [ ] Unit tests for middleware

## Use Cases

1. Apply to all web routes
2. Skip for admin/API routes
3. Automatic for eligible responses

## Additional Context

```php
// Via middleware
Route::middleware('perf.minify')->group(function () {
    // Routes with minified HTML
});

// Or globally in bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(MinifyHtml::class);
})
```

**Config:**
```php
'html_minification' => [
    'enabled' => true,
    'exclude_routes' => ['admin/*', 'api/*'],
],
```

**Response Check:**
- Content-Type must be text/html
- Response must be successful (2xx)
- Route must not be excluded

---

**Related Issues:**
- #046 (HTML Minifier)
