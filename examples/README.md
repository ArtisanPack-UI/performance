# Examples

Runnable snippets that demonstrate how each feature of the
`artisanpack-ui/performance` package fits into a Laravel application.

Every feature ships **disabled** by default. The examples below assume
you have already installed the package and run the install command:

```bash
composer require artisanpack-ui/performance
php artisan perf:install --no-interaction
```

## Directory layout

| Directory | What's inside |
|-----------|---------------|
| [`basic-setup/`](basic-setup) | Quick start, minimal config, and full-featured config |
| [`image-optimization/`](image-optimization) | WebP/AVIF conversion, responsive images, lazy loading, custom sizes |
| [`javascript-css/`](javascript-css) | Script defer/async, critical CSS, resource hints, module loading |
| [`caching/`](caching) | Page caching, fragment caching, cache warming, invalidation patterns |
| [`database/`](database) | N+1 detection, query optimization, slow query logging |
| [`monitoring/`](monitoring) | Dashboard integration, custom metrics, API usage |
| [`integrations/`](integrations) | Media library integration, existing-app integration, multi-tenant setup |

Each subdirectory has its own `README.md` that walks through the
snippets in the order you'd apply them to a real app.
