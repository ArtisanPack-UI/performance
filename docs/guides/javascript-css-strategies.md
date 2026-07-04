# JavaScript & CSS strategies

Register scripts with a load strategy and extract critical CSS to eliminate render-blocking work above the fold.

## 1. Enable the features

```dotenv
PERF_SCRIPT_OPTIMIZATION=true
PERF_CRITICAL_CSS=true
```

## 2. Register scripts

Every registration returns a fluent `ScriptRegistration` you can chain strategies onto. The default strategy is `defer`.

```php
use ArtisanPackUI\Performance\Facades\Performance;

Performance::script('/js/analytics.js')->defer();
Performance::script('/js/chat.js')->onInteraction();
Performance::script('/js/lazy-carousel.js')->onVisible();
Performance::script('/js/telemetry.js')->onIdle();
Performance::script('/js/module.js')->module();

echo Performance::renderScripts();
```

| Strategy | Loads |
|---|---|
| `defer()` | With `defer` attribute — after HTML parse, before `DOMContentLoaded` |
| `async()` | With `async` attribute — as soon as fetched |
| `module()` | As an ES module (`type="module"`) |
| `inline()` | Inline `<script>` with the source pasted in |
| `onInteraction()` | On first `mousedown` / `keydown` / `touchstart` |
| `onVisible()` | Via `IntersectionObserver` when the anchor element enters the viewport |
| `onIdle()` | Via `requestIdleCallback` (with a `setTimeout` fallback) |
| `attributes([...])` | Extra `<script>` attributes |
| `priority(int)` | Ordering hint for `renderScripts()` |

## 3. Blade shortcuts

```blade
@deferScript('/js/analytics.js')
@asyncScript('/js/tracker.js')
@moduleScript('/js/module.js')
@conditionalScript('/js/chat.js', 'on-interaction')
```

## 4. Extract critical CSS

The critical path is the CSS the browser needs to render the first viewport. Inline it and defer the rest.

### From a URL

```bash
php artisan perf:critical-css --all
php artisan perf:critical-css --route=home --route=products.show
```

Cached entries are keyed by route; add `--force` to regenerate.

### From HTML at runtime

```php
$critical = Performance::criticalCss()->extract($html);
```

### Inline in Blade

```blade
<head>
    @criticalCss
    <link rel="preload" href="{{ mix('css/app.css') }}" as="style" onload="this.rel='stylesheet'">
</head>
```

## 5. Combine with the middleware

`MinifyHtml` (`perf.minify` alias) collapses whitespace from the response body. Chain it with the resource-hint and early-hints middleware for maximum effect:

```php
Route::middleware(['web', 'perf.minify', 'perf.early-hints'])->group(fn () => …);
```

## Related

- [[api/services]] — `ScriptManager`, `CriticalCssExtractor`
- [[api/blade-directives]] — `@deferScript`, `@asyncScript`, `@moduleScript`, `@conditionalScript`, `@criticalCss`
- [[api/middleware]] — `MinifyHtml`
