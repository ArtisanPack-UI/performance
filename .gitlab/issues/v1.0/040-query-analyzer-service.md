# Implement QueryAnalyzer service

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::6" ~"Area::Backend"

## Problem Statement

Database query performance issues need detection and analysis tools.

## Proposed Solution

Create `QueryAnalyzer` service for analyzing query patterns and performance.

## Acceptance Criteria

- [ ] Create `src/Database/QueryAnalyzer.php`
- [ ] Analyze query execution time
- [ ] Identify query patterns
- [ ] Normalize queries for grouping
- [ ] Track query frequency
- [ ] Integration with Laravel query log
- [ ] Unit tests for analyzer

## Use Cases

1. Identify slow queries in application
2. Group similar queries for analysis
3. Track query patterns over time

## Additional Context

```php
use ArtisanPackUI\Performance\Services\DatabaseService;

$databaseService = app(DatabaseService::class);

// Analyze a query
$analysis = $databaseService->analyzeQuery($query);
// Returns: ['time_ms' => 250, 'normalized' => '...', 'suggestions' => [...]]

// Enable query logging
$databaseService->enableQueryLogging();

// Get analysis after request
$queries = $databaseService->getLoggedQueries();
```

**Query normalization:**
```sql
-- Original
SELECT * FROM posts WHERE user_id = 5 AND status = 'published'

-- Normalized (for grouping)
SELECT * FROM posts WHERE user_id = ? AND status = ?
```

---

**Related Issues:**
- #003 (Performance Facade)
