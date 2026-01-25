# Create QueryAnalyzer Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Frontend"

## Problem Statement

Administrators need UI to view and analyze slow queries.

## Proposed Solution

Create `QueryAnalyzer` Livewire component for displaying query analysis.

## Acceptance Criteria

- [ ] Create `src/Livewire/QueryAnalyzer.php`
- [ ] Create `resources/views/livewire/query-analyzer.blade.php`
- [ ] List slow queries with details
- [ ] Show N+1 detections
- [ ] Show index suggestions
- [ ] Filter by date range
- [ ] Filter by route
- [ ] Sort by time/frequency
- [ ] Export for analysis
- [ ] Unit tests for component

## Use Cases

1. View slowest queries from last 7 days
2. See N+1 patterns and suggestions
3. Export queries for DBA review

## Additional Context

```blade
<livewire:query-analyzer />

{{-- With filters --}}
<livewire:query-analyzer
    :date-range="'7d'"
    :min-time-ms="100"
/>
```

**Display Columns:**
- Query (truncated)
- Time (ms)
- Count
- Route
- File:Line
- Suggestions

**Actions:**
- View full query
- Copy query
- View stack trace
- Export selected

---

**Related Issues:**
- #054 (PerformanceDashboard)
- #042 (Slow Query Logging)
