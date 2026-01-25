# Implement Core Web Vitals JavaScript

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Frontend"

## Problem Statement

Real user monitoring of Core Web Vitals requires JavaScript to collect metrics.

## Proposed Solution

Create JavaScript module using web-vitals library to collect and send metrics.

## Acceptance Criteria

- [ ] Create `resources/js/web-vitals.js`
- [ ] Collect LCP (Largest Contentful Paint)
- [ ] Collect FID (First Input Delay)
- [ ] Collect CLS (Cumulative Layout Shift)
- [ ] Collect INP (Interaction to Next Paint)
- [ ] Collect TTFB (Time to First Byte)
- [ ] Send metrics to API endpoint
- [ ] Configurable sample rate
- [ ] Include page context (URL, route)
- [ ] Include device/connection info
- [ ] Blade directive: `@perfMonitor`
- [ ] Published with package assets

## Use Cases

1. Collect real user performance metrics
2. Sample only percentage of users
3. Send to API for aggregation

## Additional Context

```blade
{{-- Add to layout before </body> --}}
@perfMonitor
```

```javascript
// web-vitals.js
import { onLCP, onFID, onCLS, onINP, onTTFB } from 'web-vitals';

function sendToAnalytics(metric) {
    fetch('/api/performance/metrics', {
        method: 'POST',
        body: JSON.stringify({
            name: metric.name,
            value: metric.value,
            delta: metric.delta,
            id: metric.id,
            page: window.location.pathname,
            connection: navigator.connection?.effectiveType,
        }),
    });
}

onLCP(sendToAnalytics);
onFID(sendToAnalytics);
onCLS(sendToAnalytics);
onINP(sendToAnalytics);
onTTFB(sendToAnalytics);
```

---

**Related Issues:**
- #002 (Configuration)
