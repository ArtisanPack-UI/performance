# Create CacheManager Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Frontend"

## Problem Statement

Administrators need UI controls for cache management.

## Proposed Solution

Create `CacheManager` Livewire component for viewing and managing caches.

## Acceptance Criteria

- [ ] Create `src/Livewire/CacheManager.php`
- [ ] Create `resources/views/livewire/cache-manager.blade.php`
- [ ] Display cache statistics (size, entries, hit rate)
- [ ] List cache entries
- [ ] Invalidate specific entries
- [ ] Invalidate by tag
- [ ] Flush all caches
- [ ] Trigger cache warming
- [ ] Confirmation dialogs for destructive actions
- [ ] Unit tests for component

## Use Cases

1. View cache hit/miss rates
2. Manually invalidate product cache
3. Warm cache after deployment

## Additional Context

```blade
<livewire:cache-manager />

{{-- With customization --}}
<livewire:cache-manager
    :labels="[
        'purge' => 'Clear Cache',
        'warm' => 'Pre-load Cache',
    ]"
/>
```

**Component Methods:**
```php
public function invalidate(string $key): void;
public function invalidateByTag(string $tag): void;
public function flushAll(): void;
public function warmCache(): void;
```

**Statistics Display:**
- Page cache: X entries, Y MB, Z% hit rate
- Fragment cache: X entries, Y MB, Z% hit rate
- Query cache: X entries, Y MB, Z% hit rate

---

**Related Issues:**
- #054 (PerformanceDashboard)
- #036 (Cache Invalidation)
