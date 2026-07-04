# Artisan commands

Every command is namespaced under `perf:` and supports `--help` for full option details.

## `perf:install`

Publishes the package's assets, runs migrations, and validates the host environment.

```
perf:install
    {--config      : Only publish the package configuration.}
    {--views       : Publish the package Blade views for customization.}
    {--js          : Publish the package JavaScript bundle.}
    {--css         : Publish the package stylesheet.}
    {--migrations  : Publish and run the package migrations.}
    {--skip-migrate: Skip running migrations after publishing.}
    {--skip-checks : Skip environment validation (PHP, Laravel, permissions).}
    {--force       : Overwrite any existing published files.}
```

## `perf:generate-webp`

Bulk-converts existing JPEG/PNG images to WebP.

```
perf:generate-webp
    {path            : Path to a file or directory of images}
    {--quality=      : Output quality (0-100), defaults to the configured format quality}
    {--recursive     : Recurse into subdirectories when converting a directory}
    {--force         : Overwrite existing WebP files}
```

## `perf:critical-css`

Extracts critical CSS for one or more routes.

```
perf:critical-css
    {--route=* : Specific route name(s) to generate critical CSS for}
    {--all    : Generate critical CSS for every registered route}
    {--force  : Clear cached entries before regenerating}
```

## `perf:warm-cache`

Warms the page cache from routes, URLs, or a sitemap.

```
perf:warm-cache
    {--type=page   : Cache type to warm (currently only "page" is supported)}
    {--routes=     : Comma-separated list of route names to warm}
    {--urls=       : Comma-separated list of URLs to warm}
    {--sitemap=    : Path to a sitemap.xml file whose URLs should be warmed}
    {--concurrent= : Override the configured concurrency level}
    {--delay=      : Override the configured inter-request delay in milliseconds}
```

## `perf:purge-cache`

Purges the page cache, the fragment cache, or both.

```
perf:purge-cache
    {--type=all  : Cache type to purge (page, fragment, all)}
    {--pattern=  : Page cache wildcard pattern (only with --type=page)}
    {--tag=      : Fragment cache tag (only with --type=fragment)}
```

## `perf:suggest-indexes`

Analyzes captured slow queries and suggests indexes; optionally emits a ready-to-run migration.

```
perf:suggest-indexes
    {--migration : Generate a migration file containing the suggested indexes}
    {--path=     : Override the migration directory (defaults to database/migrations)}
```

Applying a suggestion from the dashboard fires the `IndexMigrationRequested` event so applications can wire in review, approval, and CI-guarded schema changes.

## `perf:aggregate-metrics`

Rolls raw metrics from `performance_raw_metrics` into daily buckets in `performance_metrics`.

```
perf:aggregate-metrics
    {--date=      : The calendar date to aggregate (YYYY-MM-DD). Defaults to today.}
    {--backfill=  : Backfill the last N days inclusive of today.}
```

Schedule this hourly (or match `monitoring.aggregation_interval`) via Laravel's task scheduler.
