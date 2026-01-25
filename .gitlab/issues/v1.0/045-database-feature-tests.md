# Create database optimization feature tests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::6" ~"Area::Backend"

## Problem Statement

Database optimization features need comprehensive tests.

## Proposed Solution

Create feature tests for all Phase 6 functionality.

## Acceptance Criteria

- [ ] Tests for QueryAnalyzer
- [ ] Tests for N+1 detection
- [ ] Tests for slow query logging
- [ ] Tests for index suggestions
- [ ] Tests for DetectSlowQueries middleware
- [ ] Tests for database commands
- [ ] All tests pass

## Use Cases

1. CI validates database features work correctly
2. Developers run tests after changes
3. Tests catch regressions

## Additional Context

```php
it('detects n+1 queries', function () {
    config(['artisanpack.performance.database.n1_detection.threshold' => 3]);

    // Create posts with comments
    Post::factory()->count(5)->has(Comment::factory()->count(3))->create();

    // Trigger N+1 (no eager loading)
    $posts = Post::all();
    foreach ($posts as $post) {
        $post->comments->count();
    }

    $detector = app(N1Detector::class);
    $issues = $detector->getDetectedIssues();

    expect($issues)->toHaveCount(1);
    expect($issues[0]['relation'])->toBe('comments');
});

it('logs slow queries', function () {
    config(['artisanpack.performance.database.slow_query_logging.threshold_ms' => 0]);

    DB::select('SELECT SLEEP(0.01)');

    expect(SlowQuery::count())->toBeGreaterThan(0);
});

it('suggests indexes for slow queries', function () {
    SlowQuery::factory()->create([
        'query' => 'SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC',
    ]);

    $this->artisan('perf:suggest-indexes')
        ->expectsOutput('posts')
        ->expectsOutput('user_id, created_at');
});
```

---

**Related Issues:**
All Phase 6 issues
