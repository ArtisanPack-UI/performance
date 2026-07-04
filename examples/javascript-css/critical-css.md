# Critical CSS

Extract the above-the-fold CSS for a route, cache it, and inline it
into the document head so first paint doesn't wait on a stylesheet
round-trip.

## Enable

```php
// config/artisanpack/performance.php
'features' => [
    'critical_css' => true,
],

'critical_css' => [
    'enabled'          => true,
    'cache_driver'     => env( 'CACHE_STORE', 'redis' ),
    'cache_ttl'        => 86400,
    'viewport_width'   => 1280,
    'viewport_height'  => 900,
    'stylesheets'      => [
        resource_path( 'css/app.css' ),
    ],
    'routes'           => [
        '/',
        '/products',
        '/products/*',
    ],
],
```

## Extract from the CLI

```bash
# Extract critical CSS for every configured route and cache it.
php artisan perf:critical-css

# Extract for a single route.
php artisan perf:critical-css --route=/products

# Force re-extraction (bypass the cache).
php artisan perf:critical-css --force
```

## Inline it in a Blade layout

Add the shipped directive to your layout's `<head>`:

```blade
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>

    {{-- Critical CSS inline for fast first paint. --}}
    @perfCriticalCss

    {{-- Load the full stylesheet asynchronously so it doesn't block render. --}}
    <link
        rel="preload"
        as="style"
        href="{{ mix( 'css/app.css' ) }}"
        onload="this.rel='stylesheet'"
    >
    <noscript>
        <link rel="stylesheet" href="{{ mix( 'css/app.css' ) }}">
    </noscript>
</head>
```

`@perfCriticalCss` reads the cached critical CSS for the current route
and emits a `<style>` block inline. If the route is not in the cache
the directive renders nothing so an unwarmed route still ships a
working page.

## Refresh on deploy

Wire the command into your deploy hook so the critical CSS cache
tracks the deployed stylesheet:

```yaml
# .github/workflows/deploy.yml (fragment)
- name: Refresh critical CSS
  run: php artisan perf:critical-css --force
```
