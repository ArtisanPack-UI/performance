# Create RecommendationsPanel Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Frontend"

## Problem Statement

Administrators need actionable recommendations for performance improvements.

## Proposed Solution

Create `RecommendationsPanel` Livewire component with prioritized suggestions.

## Acceptance Criteria

- [ ] Create `src/Livewire/RecommendationsPanel.php`
- [ ] Create `resources/views/livewire/recommendations-panel.blade.php`
- [ ] Create `src/Monitoring/RecommendationEngine.php`
- [ ] Priority-ordered recommendations
- [ ] Impact indicators (high/medium/low)
- [ ] One-click fixes where possible
- [ ] Progress tracking
- [ ] Dismissible recommendations
- [ ] Unit tests for component

## Use Cases

1. View top performance issues
2. Apply one-click fix for image optimization
3. Track resolved issues

## Additional Context

```blade
<livewire:recommendations-panel />
```

**Recommendation Types:**
1. Unoptimized images (one-click: optimize all)
2. Missing lazy loading (code example)
3. Slow queries (link to query analyzer)
4. Missing indexes (one-click: generate migration)
5. Cache opportunities (configuration example)
6. Large JavaScript bundles (code splitting guide)

**Recommendation Structure:**
```php
[
    'type' => 'image_optimization',
    'priority' => 'high',
    'title' => '15 images need optimization',
    'description' => 'Optimizing these images could save 2.5MB',
    'impact' => 'high',
    'action' => 'optimize-images', // One-click action
    'manual_steps' => [...], // If no one-click available
]
```

---

**Related Issues:**
- #054 (PerformanceDashboard)
