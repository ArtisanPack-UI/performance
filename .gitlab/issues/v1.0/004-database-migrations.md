# Create database migrations

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::1" ~"Area::Backend"

## Problem Statement

The package needs database tables to store performance metrics, slow queries, cache entries, and optimized image metadata.

## Proposed Solution

Create migrations for all required database tables with proper indexes.

## Acceptance Criteria

- [ ] Create `performance_metrics` table migration
- [ ] Create `performance_slow_queries` table migration
- [ ] Create `performance_cache_entries` table migration
- [ ] Create `performance_optimized_images` table migration
- [ ] Add proper indexes for performance
- [ ] Migrations published via `vendor:publish`
- [ ] Migrations run via `php artisan migrate`

## Use Cases

1. Developer installs package and runs migrations
2. Metrics are stored for dashboard display
3. Slow queries are logged for analysis

## Additional Context

### `performance_metrics`
```php
$table->id();
$table->date('date');
$table->string('route')->nullable();
$table->string('url')->nullable();
$table->string('metric'); // LCP, FID, CLS, INP, TTFB
$table->float('p50');
$table->float('p75');
$table->float('p90');
$table->float('p99');
$table->unsignedInteger('sample_count');
$table->string('device_type')->nullable();
$table->string('connection_type')->nullable();
$table->timestamps();
```

### `performance_slow_queries`
```php
$table->id();
$table->text('query');
$table->text('query_normalized');
$table->json('bindings')->nullable();
$table->float('time_ms');
$table->string('connection');
$table->string('file')->nullable();
$table->unsignedInteger('line')->nullable();
$table->json('trace')->nullable();
$table->string('route')->nullable();
$table->timestamps();
```

---

**Related Issues:**
- #001 (Package Setup)
