# Middleware

Middleware live under `ArtisanPackUI\Performance\Http\Middleware`. Two aliases are registered by the service provider; the rest are opt-in via FQCN.

## Registered aliases

| Alias | Class | Purpose |
|---|---|---|
| `perf.minify` | `MinifyHtml` | Minify the response body |
| `perf.early-hints` | `EarlyHints` | Emit a HTTP 103 Early Hints interim response before the main body |

```php
Route::middleware(['web', 'perf.minify', 'perf.early-hints'])->group(function () {
    // …
});
```

## Full class list

### `PageCache`

Wraps the request lifecycle around `PageCacheManager`. On cacheable GET requests, returns the cached response before the controller runs; on the way out, stores fresh responses.

- Applied per-route or per-group — the package does not register it globally so applications control which routes opt in.
- Skips authenticated requests unless `page_cache.include_authenticated` is `true`.
- Skips routes listed in `page_cache.exclude_routes`.

```php
Route::middleware([\ArtisanPackUI\Performance\Http\Middleware\PageCache::class])
    ->get('/', HomeController::class);
```

### `MinifyHtml`

Runs `HtmlMinifier::minify()` on the outgoing response body. Short-circuits on non-HTML payloads, streamed responses, and error responses. Alias: `perf.minify`.

### `EarlyHints`

Emits a HTTP 103 Early Hints response carrying `Link:` preload headers so 103-aware clients (Chrome 103+, Firefox 120+, Safari Tech Preview) can begin fetching critical assets while the main response is still being computed. Alias: `perf.early-hints`.

### `InjectResourceHints`

Drains the request-scoped `ResourceHintInjector` and writes its resolved hints into the outgoing response — a `<link>` block injected into the document `<head>` and an RFC 8288 `Link:` header for browsers that act on headers.

```php
Route::middleware([\ArtisanPackUI\Performance\Http\Middleware\InjectResourceHints::class])
    ->group(fn () => …);
```

### `DetectSlowQueries`

Enables `QueryAnalyzer`, `N1Detector`, and `SlowQueryLogger` at the start of each request so detection runs across the full controller + middleware pipeline. The canonical wiring point for applications that want per-request detection without manually attaching listeners in a service provider.

```php
Route::middleware([\ArtisanPackUI\Performance\Http\Middleware\DetectSlowQueries::class])
    ->group(fn () => …);
```

Enable `database.n1_detection.enabled` and `database.slow_query_logging.enabled` in config for the detectors to do anything.
