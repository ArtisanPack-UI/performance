# Create EarlyHints middleware

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::7" ~"Area::Backend"

## Problem Statement

HTTP/2 Early Hints (103) allow sending preload hints before the main response is ready.

## Proposed Solution

Create `EarlyHints` middleware that sends 103 response with resource hints.

## Acceptance Criteria

- [ ] Create `src/Http/Middleware/EarlyHints.php`
- [ ] Send HTTP 103 Early Hints response
- [ ] Include Link headers for critical resources
- [ ] Auto-detect critical resources (optional)
- [ ] Manual hint configuration
- [ ] Skip if server doesn't support 103
- [ ] Unit tests for middleware

## Use Cases

1. Browser receives preload hints immediately
2. Browser starts fetching resources before main response
3. Reduces time to first paint

## Additional Context

```php
// Via middleware
Route::middleware('perf.early-hints')->group(function () {
    // Routes with early hints
});
```

**Response Flow:**
```
HTTP/1.1 103 Early Hints
Link: </css/app.css>; rel=preload; as=style
Link: </js/app.js>; rel=preload; as=script
Link: <https://fonts.googleapis.com>; rel=preconnect

HTTP/1.1 200 OK
Content-Type: text/html
...
```

**Config:**
```php
'early_hints' => [
    'enabled' => true,
    'auto_detect' => true,
    'manual_hints' => [
        ['href' => '/css/app.css', 'rel' => 'preload', 'as' => 'style'],
        ['href' => '/js/app.js', 'rel' => 'preload', 'as' => 'script'],
    ],
],
```

**Note:** Requires web server support (nginx, Apache) and PHP configuration.

---

**Related Issues:**
- #021 (Resource Hints)
