# Implement prefetch/prerender management

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::4" ~"Area::Backend"

## Problem Statement

Managing which URLs to prefetch/prerender requires logic beyond static rules.

## Proposed Solution

Create `PrefetchManager` and `PrerenderManager` for dynamic URL management.

## Acceptance Criteria

- [ ] Create `src/Speculative/PrefetchManager.php`
- [ ] Create `src/Speculative/PrerenderManager.php`
- [ ] Register URLs programmatically
- [ ] Priority-based prefetching
- [ ] Limit management for prerenders
- [ ] URL pattern matching
- [ ] Integration with analytics data (if available)
- [ ] Unit tests for managers

## Use Cases

1. Prefetch most likely next pages based on analytics
2. Limit prerenders to prevent resource exhaustion
3. Dynamically add/remove URLs from speculation

## Additional Context

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Register prefetch URLs
Performance::prefetch([
    '/products/popular',
    '/blog/latest',
]);

// Register prerender for high-confidence navigation
Performance::prerender('/checkout', priority: 'high');

// Clear prefetch for specific pattern
Performance::clearPrefetch('/temporary/*');
```

**Eagerness Levels:**
| Level | Trigger | Use Case |
|-------|---------|----------|
| immediate | Page load | Highly confident next page |
| eager | Hover (200ms) | Likely navigation |
| moderate | Hover intent | Possible navigation |
| conservative | Pointer down | Confirmed intent |

---

**Related Issues:**
- #026 (SpeculativeRulesGenerator)
