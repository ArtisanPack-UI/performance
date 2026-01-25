# Create performance monitoring feature tests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Backend"

## Problem Statement

Performance monitoring features need comprehensive tests.

## Proposed Solution

Create feature tests for all Phase 8 functionality.

## Acceptance Criteria

- [ ] Tests for metrics API endpoint
- [ ] Tests for MetricsAggregator
- [ ] Tests for PerformanceDashboard component
- [ ] Tests for MetricsChart component
- [ ] Tests for CacheManager component
- [ ] Tests for QueryAnalyzer component
- [ ] Tests for RecommendationsPanel component
- [ ] Tests for view customization
- [ ] All tests pass

## Use Cases

1. CI validates monitoring works correctly
2. Developers run tests after changes
3. Tests catch regressions

## Additional Context

```php
it('stores metrics via API', function () {
    $response = $this->postJson('/api/performance/metrics', [
        'name' => 'LCP',
        'value' => 2100,
        'page' => '/products',
    ]);

    $response->assertSuccessful();
    expect(PerformanceMetric::count())->toBe(1);
});

it('aggregates metrics into percentiles', function () {
    // Create raw metrics
    foreach (range(1, 100) as $i) {
        PerformanceMetric::factory()->create(['value' => $i * 100]);
    }

    $aggregator = app(MetricsAggregator::class);
    $result = $aggregator->aggregate(now()->toDateString());

    expect($result['p50'])->toBe(5000);
    expect($result['p75'])->toBe(7500);
});

it('renders performance dashboard', function () {
    Livewire::test(PerformanceDashboard::class)
        ->assertSee('Core Web Vitals')
        ->assertSee('LCP')
        ->assertSee('FID')
        ->assertSee('CLS');
});

it('supports view customization props', function () {
    Livewire::test(PerformanceDashboard::class, [
        'class' => 'custom-class',
    ])->assertSeeHtml('custom-class');
});
```

---

**Related Issues:**
All Phase 8 issues
