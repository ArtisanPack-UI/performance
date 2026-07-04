# Speculative loading & resource hints

Make next-page navigation feel instant by registering prefetch and prerender URLs, and cut TTFB with preconnect / dns-prefetch / preload hints and HTTP 103 Early Hints.

## 1. Enable the features

```dotenv
PERF_SPECULATIVE_LOADING=true
PERF_RESOURCE_HINTS=true
PERF_EARLY_HINTS=true
```

## 2. Register speculative URLs

Register per-request, chain further calls fluently:

```php
use ArtisanPackUI\Performance\Facades\Performance;

Performance::prefetch(['/products', '/about'], 'moderate');
Performance::prerender('/checkout', 'conservative');
```

Eagerness levels map to the Speculation Rules API:

| Level | Meaning |
|---|---|
| `immediate` | Start as soon as the rule is parsed |
| `eager` | Start after a short delay |
| `moderate` | Start on hover / focus (default for prefetch) |
| `conservative` | Start on `pointerdown` (default for prerender) |

## 3. Emit the Speculation Rules block

Add the directive to your layout `<head>`:

```blade
@speculativeRules
```

It renders a `<script type="speculationrules">` with every URL registered on the current request.

## 4. Add resource hints

Individual hints in Blade:

```blade
@preconnect('https://fonts.googleapis.com')
@dnsPrefetch('//cdn.example.com')
@preload('/fonts/inter.woff2', 'font')
@prefetch('/products')
```

Or register batches via the injector service and let the middleware emit them:

```php
Performance::resourceHints()
    ->preconnect('https://fonts.googleapis.com')
    ->dnsPrefetch('//cdn.example.com')
    ->preload('/fonts/inter.woff2', 'font');
```

Attach `InjectResourceHints` to the route or group — the middleware drains the injector and writes both a `<link>` block and an RFC 8288 `Link:` header.

```php
use ArtisanPackUI\Performance\Http\Middleware\InjectResourceHints;

Route::middleware([InjectResourceHints::class])->group(fn () => …);
```

## 5. Enable HTTP 103 Early Hints

Apply the alias to the same routes:

```php
Route::middleware(['perf.early-hints'])->group(fn () => …);
```

103 Early Hints emit an interim response carrying `Link:` preload headers before the main body is ready, so 103-aware clients (Chrome 103+, Firefox 120+, Safari Tech Preview) can begin fetching critical assets while the controller is still working.

## 6. Client-side updates

For SPA transitions that mutate the speculation set after the initial navigation, install the same rules from JavaScript:

```ts
import { installSpeculationRules } from '@artisanpack-ui/performance/speculative-rules'

installSpeculationRules({
    prefetch:  [{ urls: ['/products'], eagerness: 'moderate' }],
    prerender: [{ urls: ['/checkout'], eagerness: 'conservative' }],
})
```

## 7. Clear a rule

```php
Performance::clearPrefetch('/products/*');
Performance::clearPrerender('/checkout');
```

## Related

- [[api/services]] — `PrefetchManager`, `PrerenderManager`, `SpeculativeRulesGenerator`, `ResourceHintInjector`
- [[api/middleware]] — `EarlyHints`, `InjectResourceHints`
- [[api/blade-directives]] — `@speculativeRules`, `@preconnect`, `@dnsPrefetch`, `@preload`, `@prefetch`
- [[api/javascript]] — `@artisanpack-ui/performance/speculative-rules`
