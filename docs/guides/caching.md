# Caching

Full-page and fragment caching with tag-based invalidation, warming, and safe flush semantics.

## 1. Enable the features

```dotenv
PERF_PAGE_CACHE=true
PERF_FRAGMENT_CACHE=true
PERF_CACHE_WARMING=true
```

Point the fragment cache at a dedicated store so `Performance::flushCache()` never nukes sessions, locks, or unrelated app cache entries:

```php
'fragment_cache' => [
    'driver' => 'redis-perf', // separate store from cache.default
],
```

## 2. Serve pages from cache

Apply the `PageCache` middleware to routes you want cached:

```php
use ArtisanPackUI\Performance\Http\Middleware\PageCache;

Route::middleware([PageCache::class])->group(function () {
    Route::get('/', HomeController::class);
    Route::get('/pricing', PricingController::class);
});
```

The middleware short-circuits on authenticated requests (unless `page_cache.include_authenticated` is true) and on routes listed in `page_cache.exclude_routes`.

## 3. Cache fragments

Wrap a block in `Performance::fragmentRemember()` (or the `@cache` Blade directive) with a TTL and optional tags:

```php
$html = Performance::fragmentRemember(
    'sidebar-featured',
    900,
    fn () => view('partials.sidebar-featured')->render(),
    tags: ['sidebar', 'featured'],
);
```

```blade
@cache('sidebar-featured', 900)
    @include('partials.sidebar-featured')
@endcache
```

## 4. Namespaced key/value

For plain remember/forget outside the fragment surface:

```php
Performance::remember('products.top-selling', 3600, fn () => Product::topSelling()->get());
Performance::rememberForever('site.settings', fn () => Settings::all());
Performance::invalidateCache('products.top-selling');
```

Every key is namespaced under `performance:` in the underlying store.

## 5. Invalidation

```php
Performance::invalidatePageCache('/products/*'); // wildcard
Performance::invalidateFragmentsByTag('featured');
Performance::flushPageCache();
Performance::flushCache(); // fragment store — refuses to flush the default store
```

`flushCache()` throws a `RuntimeException` when the fragment driver is unset or matches `cache.default`, because Laravel's cache contract only exposes store-wide `flush()` — flushing the default store would wipe sessions, rate limits, and unrelated cache entries.

## 6. Warm the cache

```bash
php artisan perf:warm-cache --sitemap=public/sitemap.xml
php artisan perf:warm-cache --routes=home,products.index
php artisan perf:warm-cache --urls=https://example.com/,https://example.com/pricing
```

From code:

```php
Performance::warmPageCache(['/', '/pricing', '/docs']);
```

Fires `CacheWarmed` on success.

## 7. Purge on demand

```bash
php artisan perf:purge-cache --type=page --pattern="/products/*"
php artisan perf:purge-cache --type=fragment --tag=sidebar
php artisan perf:purge-cache --type=all
```

Every invalidation goes through `CacheInvalidator` and fires the `CachePurged` event.

## 8. Cache management from the dashboard

Mount the `perf-cache-manager` Livewire component to expose invalidation, warming, and flush controls to operators:

```blade
<livewire:perf-cache-manager />
```

## Related

- [[api/services]] — `PageCacheManager`, `FragmentCache`, `CacheInvalidator`, `CacheStatistics`
- [[api/middleware]] — `PageCache`
- [[api/events]] — `CachePurged`, `CacheWarmed`
- [[api/blade-directives]] — `@cache`
- [[api/livewire]] — `perf-cache-manager`
