# Adding the package to an existing app

Every feature is disabled after install so a `composer require` +
`perf:install` is safe on a live app. Rolling features on one at a
time avoids surprising cache-invalidation and SEO regressions.

## Phase 1 — install and observe

```bash
composer require artisanpack-ui/performance
php artisan perf:install --no-interaction
```

Turn on **monitoring only**:

```php
'features' => [
    'monitoring' => true,
    'dashboard'  => true,
    'query_optimization' => true, // Slow-query logging only, no caching yet.
],

'query_optimization' => [
    'log_slow_queries' => true,
    'slow_threshold'   => 200,
    'detect_n1'        => true,
    'sample_rate'      => 0.1, // Keep write volume low at first.
],
```

Add `@perfMonitor` to your main layout and wait a week. Now you have a
baseline.

## Phase 2 — safe wins

Toggle on features that don't change output HTML:

```php
'features' => [
    // ... phase 1
    'resource_hints' => true,
    'lazy_loading'   => true,
],
```

## Phase 3 — HTML mutations

Turn on features that rewrite HTML on the way out. Watch for cache
poisoning and CDN edge cases.

```php
'features' => [
    // ... phase 2
    'image_optimization'  => true,
    'script_optimization' => true,
    'html_minification'   => true,
],
```

`html_minification.exclude_routes` should list any route where the
raw HTML matters (webhooks that echo XML, `/humans.txt`, etc.).

## Phase 4 — caching

Full-page caching is the biggest win and the biggest footgun. Roll it
out with a short TTL first and only after you've mapped every mutation
to an invalidation.

```php
'features' => [
    // ... phase 3
    'page_caching'     => true,
    'fragment_caching' => true,
],

'page_cache' => [
    'ttl' => 300, // 5 minutes to start. Grow this after a week of clean logs.
    'exclude_routes' => [
        'admin/*',
        'api/*',
        'account/*',
        'checkout/*',
        'cart/*',
    ],
    'vary_by' => ['auth'],
],
```

Wire model observers to `CacheInvalidator` (see
[`../caching/invalidation.php`](../caching/invalidation.php)) before
you extend the TTL.

## Phase 5 — critical CSS + early hints

Both features need infrastructure the earlier phases don't:

- Critical CSS needs a headless browser (Chrome/Chromium) reachable
  from the CLI.
- Early Hints needs an HTTP/2 or HTTP/3 upstream that respects 103
  interim responses (Nginx 1.13.9+, Caddy, HAProxy 2.7+, Cloudflare).

Toggle them on once those prerequisites are in place.

## Phase 6 — speculative loading

Speculation Rules is a progressive enhancement — browsers that don't
support it just ignore the rules. Turn it on last so you know every
other feature is stable first.

```php
'features' => [
    // ... phase 5
    'speculative_loading' => true,
],
```
