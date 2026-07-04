# Quick start

The shortest path from a fresh Laravel app to a working Performance
package install.

## 1. Install

```bash
composer require artisanpack-ui/performance

# Publishes config, runs migrations, prints the dashboard gate stub.
php artisan perf:install --no-interaction
```

## 2. Toggle on the features you want

Every feature is off after install. Open
`config/artisanpack/performance.php` and set the toggles for the
features you plan to use:

```php
'features' => [
    'image_optimization'  => true,
    'lazy_loading'        => true,
    'script_optimization' => true,
    'page_caching'        => true,
    'monitoring'          => true,
    'dashboard'           => true,
],
```

Every feature also has a matching env var (`PERF_IMAGE_OPTIMIZATION`,
`PERF_MONITORING`, …) so a feature can be toggled per-environment
without editing config.

## 3. Wire the dashboard gate

Add to `AuthServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('view-performance-dashboard', function ($user) {
    return $user->hasRole('admin');
});
```

## 4. Add the RUM collector

In your main layout:

```blade
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- everything else in <head> --}}
    @perfMonitor
</head>
```

Web Vitals now POST to `/api/performance/metrics` as visitors browse.

## 5. Visit the dashboard

`https://your-app.test/admin/performance`

You'll see the overview, per-page metrics, cache manager, query
analyzer, and recommendations panel. All of the panels are also
available as React and Vue components under
`@artisanpack-ui/performance/react` / `/vue`.
