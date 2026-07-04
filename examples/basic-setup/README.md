# Basic Setup

Three snippets that get the package from "installed" to "running".

## 1. Quick start

`composer require` + install command + a single feature toggle. This is
enough to start collecting Core Web Vitals from every page.

```bash
composer require artisanpack-ui/performance
php artisan perf:install --no-interaction
```

Then in `config/artisanpack/performance.php`:

```php
'features' => [
    'monitoring' => true,
],
```

And in `resources/views/layouts/app.blade.php`:

```blade
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- other head tags --}}
    @perfMonitor
</head>
```

Reload any page. Web Vitals now POST to
`/api/performance/metrics` and land in the `performance_metrics` table.

See [`quick-start.md`](quick-start.md) for the fully commented walkthrough.

## 2. Minimal configuration

The smallest sensible config for a production app — page cache, HTML
minification, and monitoring on. Nothing else.

See [`minimal-config.php`](minimal-config.php).

## 3. Full-featured configuration

Every feature on, with production-grade tuning (short cache TTLs, low
sample rate, aggressive image optimization). Copy into your
`config/artisanpack/performance.php` as a starting point and pare back
what you don't need.

See [`full-featured-config.php`](full-featured-config.php).
