# Create PerformanceDashboard Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Frontend"

## Problem Statement

Administrators need a dashboard to view and analyze performance metrics.

## Proposed Solution

Create `PerformanceDashboard` Livewire component with tabs for different views.

## Acceptance Criteria

- [ ] Create `src/Livewire/PerformanceDashboard.php`
- [ ] Create `resources/views/livewire/performance-dashboard.blade.php`
- [ ] Date range selector
- [ ] Overview tab with Core Web Vitals summary
- [ ] Pages tab with per-page breakdown
- [ ] Images tab with optimization status
- [ ] Cache tab with hit/miss rates
- [ ] Queries tab with slow queries
- [ ] Recommendations tab
- [ ] Responsive design
- [ ] View customization support (props, slots)
- [ ] Unit tests for component

## Use Cases

1. Admin views overall performance summary
2. Admin identifies slowest pages
3. Admin sees actionable recommendations

## Additional Context

```blade
{{-- Include in admin layout --}}
<livewire:performance-dashboard />

{{-- With customization --}}
<livewire:performance-dashboard
    class="custom-dashboard"
    :default-date-range="'30d'"
>
    <x-slot:header>
        <h1 class="text-2xl">Performance</h1>
    </x-slot:header>
</livewire:performance-dashboard>
```

**Tabs:**
1. Overview - Core Web Vitals with pass/fail status
2. Pages - Per-page performance metrics
3. Images - Optimization status and actions
4. Cache - Hit/miss rates, manual controls
5. Queries - Slow queries, N+1 detections
6. Recommendations - Actionable improvements

---

**Related Issues:**
- #053 (MetricsAggregator)
