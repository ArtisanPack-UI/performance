# Implement slow query logging

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::6" ~"Area::Backend"

## Problem Statement

Slow queries need to be logged for analysis and optimization.

## Proposed Solution

Create `SlowQueryLogger` that captures and stores slow queries.

## Acceptance Criteria

- [ ] Create `src/Database/SlowQueryLogger.php`
- [ ] Configurable threshold (default: 100ms)
- [ ] Log to configured channel
- [ ] Store in database for dashboard
- [ ] Capture query with bindings
- [ ] Capture file and line number
- [ ] Capture stack trace (optional)
- [ ] Retention period configuration
- [ ] Unit tests for logging

## Use Cases

1. Log all queries > 100ms
2. Store in database for dashboard analysis
3. Include source file/line for debugging

## Additional Context

```php
// Configuration
'database' => [
    'slow_query_logging' => [
        'enabled' => true,
        'threshold_ms' => 100,
        'log_channel' => 'performance',
        'store_in_database' => true,
        'retention_days' => 30,
    ],
],
```

**Logged Data:**
```php
[
    'query' => 'SELECT * FROM posts WHERE ...',
    'bindings' => [...],
    'time_ms' => 250,
    'connection' => 'mysql',
    'file' => 'app/Http/Controllers/PostController.php',
    'line' => 45,
    'trace' => [...],
    'route' => 'posts.index',
]
```

**Database Storage:**
Uses `performance_slow_queries` table from migrations.

---

**Related Issues:**
- #040 (QueryAnalyzer)
- #004 (Database Migrations)
