# Create events system

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::1" ~"Area::Backend"

## Problem Statement

The package needs events to allow developers to hook into performance operations like image optimization, cache operations, and performance threshold alerts.

## Proposed Solution

Create event classes for all significant package operations.

## Acceptance Criteria

- [ ] Create `ImageOptimized` event
- [ ] Create `CacheWarmed` event
- [ ] Create `CachePurged` event
- [ ] Create `SlowQueryDetected` event
- [ ] Create `N1QueryDetected` event
- [ ] Create `PerformanceThresholdExceeded` event
- [ ] All events are dispatchable
- [ ] Events include relevant payload data
- [ ] Register default listeners in service provider
- [ ] Unit tests for event dispatching

## Use Cases

1. Developer listens to `ImageOptimized` to update custom tracking
2. Developer listens to `SlowQueryDetected` to send alerts
3. Developer listens to `PerformanceThresholdExceeded` for monitoring

## Additional Context

```php
// Events fired by the package
event(new ImageOptimized($path, $formats, $sizes));
event(new CacheWarmed($urls, $count));
event(new SlowQueryDetected($query, $time, $trace));
event(new PerformanceThresholdExceeded('LCP', 4500, 4000));

// Developer can listen
Event::listen(SlowQueryDetected::class, function ($event) {
    Notification::send($admins, new SlowQueryAlert($event));
});
```

---

**Related Issues:**
- #001 (Package Setup)
