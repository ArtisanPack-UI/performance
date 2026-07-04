# Caching

Snippets for page caching, fragment caching, cache warming, and
invalidation patterns.

## Examples

- [`page-cache-setup.php`](page-cache-setup.php) — turn on full-page caching with exclude routes and vary-by keys
- [`fragment-cache.blade.php`](fragment-cache.blade.php) — cache an expensive Blade fragment with `@perfCache`
- [`cache-warming.php`](cache-warming.php) — schedule `perf:warm-cache` to keep hot URLs in cache
- [`invalidation.php`](invalidation.php) — model observer + `CacheInvalidator` pattern for automatic tag invalidation
