# Implement MetricsAggregator

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Backend"

## Problem Statement

Raw metrics need to be aggregated into percentiles for meaningful analysis.

## Proposed Solution

Create `MetricsAggregator` to calculate p50, p75, p90, p99 from raw data.

## Acceptance Criteria

- [ ] Create `src/Monitoring/MetricsAggregator.php`
- [ ] Calculate percentiles (p50, p75, p90, p99)
- [ ] Aggregate by time period (hourly, daily)
- [ ] Aggregate by route/page
- [ ] Aggregate by device type
- [ ] Aggregate by connection type
- [ ] Store aggregated results
- [ ] Scheduled aggregation job
- [ ] Unit tests for aggregation

## Use Cases

1. Aggregate hourly metrics into daily summaries
2. Calculate p75 LCP for dashboard display
3. Track performance trends over time

## Additional Context

```php
use ArtisanPackUI\Performance\Monitoring\MetricsAggregator;

$aggregator = app(MetricsAggregator::class);

// Aggregate metrics for a date
$aggregator->aggregate('2026-01-24');

// Aggregated data structure
[
    'date' => '2026-01-24',
    'route' => 'products.index',
    'metric' => 'LCP',
    'p50' => 1800,
    'p75' => 2400,
    'p90' => 3200,
    'p99' => 5100,
    'sample_count' => 1250,
]
```

**Scheduling:**
```php
// Schedule aggregation
Schedule::command('perf:aggregate-metrics')->hourly();
```

---

**Related Issues:**
- #052 (Metrics API)
- #004 (Database Migrations)
