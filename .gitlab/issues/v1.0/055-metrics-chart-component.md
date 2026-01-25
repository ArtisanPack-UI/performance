# Create MetricsChart Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Frontend"

## Problem Statement

Performance metrics need visual representation as charts.

## Proposed Solution

Create `MetricsChart` Livewire component for visualizing metrics over time.

## Acceptance Criteria

- [ ] Create `src/Livewire/MetricsChart.php`
- [ ] Create `resources/views/livewire/metrics-chart.blade.php`
- [ ] Line chart for time series data
- [ ] Configurable metric selection
- [ ] Configurable date range
- [ ] Show threshold lines (good/poor)
- [ ] Responsive design
- [ ] Chart library integration (Chart.js or similar)
- [ ] Unit tests for component

## Use Cases

1. View LCP trend over 7 days
2. Compare multiple metrics
3. See threshold crossings

## Additional Context

```blade
<livewire:metrics-chart
    metric="LCP"
    :date-range="'7d'"
    :show-threshold="true"
/>

{{-- Multiple metrics --}}
<livewire:metrics-chart
    :metrics="['LCP', 'FID', 'CLS']"
    :date-range="'30d'"
/>
```

**Chart Options:**
```php
'charts' => [
    'type' => 'line',        // line, bar, area
    'show_grid' => true,
    'animate' => true,
    'colors' => [
        'good' => '#22c55e',
        'needs_improvement' => '#f59e0b',
        'poor' => '#ef4444',
    ],
],
```

---

**Related Issues:**
- #054 (PerformanceDashboard)
