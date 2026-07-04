# Database optimization

Detect N+1 queries, log slow queries, cache repeated queries, and generate index suggestions from real traffic.

## 1. Enable the feature

```dotenv
PERF_QUERY_OPTIMIZATION=true
```

## 2. Attach the detector middleware

The canonical wiring point is `DetectSlowQueries` — it enables `QueryAnalyzer`, `N1Detector`, and `SlowQueryLogger` at the start of every request in the group.

```php
use ArtisanPackUI\Performance\Http\Middleware\DetectSlowQueries;

Route::middleware([DetectSlowQueries::class])->group(fn () => …);
```

## 3. Configure N+1 detection

```php
'database' => [
    'n1_detection' => [
        'enabled'     => true,
        'threshold'   => 5,       // same normalized query fires more than N times
        'log_channel' => 'performance',
        'notify'      => false,
    ],
],
```

When a request crosses the threshold, `N1QueryDetected` is dispatched with the normalized query, occurrence count, and route.

## 4. Log slow queries

```php
'slow_query_logging' => [
    'enabled'           => true,
    'threshold_ms'      => 100,
    'store_in_database' => true,
    'retention_days'    => 30,
],
```

Rows land in `performance_slow_queries` and fire `SlowQueryDetected`.

## 5. Cache repeated model queries

Add the `CachesQueries` trait to any Eloquent model:

```php
use ArtisanPackUI\Performance\Traits\CachesQueries;

class Report extends Model
{
    use CachesQueries;
}
```

Then opt individual queries in fluently:

```php
Report::query()
    ->cacheFor(3600)
    ->cacheTags(['reports'])
    ->where('year', 2026)
    ->get();
```

Invalidate on write:

```php
Performance::invalidateFragmentsByTag('reports');
```

## 6. Generate index suggestions

```bash
php artisan perf:suggest-indexes
```

Produces a ranked list of `(table, columns)` candidates from captured slow queries. Emit them as a runnable migration:

```bash
php artisan perf:suggest-indexes --migration
```

Applying a suggestion from the dashboard fires the `IndexMigrationRequested` event so applications can wire in review, approval, and CI-guarded schema changes rather than executing DDL directly.

## 7. Review from the dashboard

Mount `perf-query-analyzer` on an admin route:

```blade
<livewire:perf-query-analyzer />
```

The component groups rows by their normalized signature so repeat offenders collapse to a single row, and lets you sort by total time or occurrence count.

## Related

- [[api/services]] — `QueryAnalyzer`, `N1Detector`, `SlowQueryLogger`, `IndexSuggester`
- [[api/traits]] — `CachesQueries`
- [[api/middleware]] — `DetectSlowQueries`
- [[api/events]] — `SlowQueryDetected`, `N1QueryDetected`, `IndexMigrationRequested`
- [[api/models]] — `SlowQuery`
- [[api/livewire]] — `perf-query-analyzer`
