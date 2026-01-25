# Implement index suggestion

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::6" ~"Area::Backend"

## Problem Statement

Missing database indexes cause slow queries. Automatic suggestions help developers add them.

## Proposed Solution

Create `IndexSuggester` that analyzes slow queries and suggests missing indexes.

## Acceptance Criteria

- [ ] Create `src/Database/IndexSuggester.php`
- [ ] Analyze WHERE clauses for index opportunities
- [ ] Analyze ORDER BY clauses
- [ ] Analyze JOIN conditions
- [ ] Generate index suggestions
- [ ] Estimate potential impact
- [ ] Artisan command: `php artisan perf:suggest-indexes`
- [ ] Optional: generate migration file
- [ ] Unit tests for suggestions

## Use Cases

1. Analyze slow queries for index opportunities
2. Suggest composite indexes for common patterns
3. Generate migration file for suggested indexes

## Additional Context

**Command:**
```bash
php artisan perf:suggest-indexes
```

**Output:**
```
Analyzing slow queries...

Suggested Indexes:
┌─────────────────┬──────────────────────────────────┬─────────────────┐
│ Table           │ Suggested Index                  │ Potential Impact│
├─────────────────┼──────────────────────────────────┼─────────────────┤
│ posts           │ INDEX (user_id, created_at)      │ High            │
│ comments        │ INDEX (post_id, approved)        │ Medium          │
│ orders          │ INDEX (status, created_at DESC)  │ High            │
└─────────────────┴──────────────────────────────────┴─────────────────┘

Generate migration? [y/N]
```

**Generated Migration:**
```php
Schema::table('posts', function (Blueprint $table) {
    $table->index(['user_id', 'created_at']);
});
```

---

**Related Issues:**
- #042 (Slow Query Logging)
