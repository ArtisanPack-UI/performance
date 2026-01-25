# Implement N+1 query detection

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::6" ~"Area::Backend"

## Problem Statement

N+1 queries are a common performance issue. Automatic detection helps developers fix them.

## Proposed Solution

Create `N1Detector` that identifies N+1 query patterns during request lifecycle.

## Acceptance Criteria

- [ ] Create `src/Database/N1Detector.php`
- [ ] Detect repeated similar queries
- [ ] Configurable threshold (default: 5)
- [ ] Identify model and relation
- [ ] Suggest eager loading fix
- [ ] Log detected N+1 issues
- [ ] Fire event for notifications
- [ ] Unit tests for detection

## Use Cases

1. Detect 25 repeated `SELECT * FROM comments WHERE post_id = ?`
2. Suggest `Post::with('comments')`
3. Alert developers in development

## Additional Context

```php
// Configuration
'database' => [
    'n1_detection' => [
        'enabled' => true,
        'threshold' => 5, // Trigger after 5 similar queries
        'log_channel' => 'performance',
        'notify' => true,
    ],
],
```

**Detection Output:**
```
[PERFORMANCE WARNING] N+1 Query Detected
Model: App\Models\Post
Relation: comments
Query Count: 25
Suggestion: Use ->with('comments') for eager loading

Query Pattern:
SELECT * FROM comments WHERE post_id = ?
Executed 25 times
```

**Event:**
```php
event(new N1QueryDetected(
    model: Post::class,
    relation: 'comments',
    count: 25,
    query: 'SELECT * FROM comments WHERE post_id = ?'
));
```

---

**Related Issues:**
- #040 (QueryAnalyzer)
- #005 (Events System)
