# JavaScript API

The package ships a client-side companion published under `@artisanpack-ui/performance` with multiple subpath exports so applications only pay for the pieces they use. Publish the bundle with:

```bash
php artisan vendor:publish --tag=artisanpack-performance-js
```

## Subpath exports

| Import | Purpose |
|---|---|
| `@artisanpack-ui/performance` | Main entry — boots the RUM collector, cache client, and speculative-rules helpers |
| `@artisanpack-ui/performance/web-vitals` | Standalone Web Vitals collector (LCP, CLS, FID, INP, TTFB, FCP) |
| `@artisanpack-ui/performance/metrics-chart` | Chart.js bootstrap consumed by `perf-metrics-chart` |
| `@artisanpack-ui/performance/speculative-rules` | Client helper to install a Speculation Rules document at runtime |
| `@artisanpack-ui/performance/react` | React components + hooks for the dashboard surfaces |
| `@artisanpack-ui/performance/vue` | Vue components + composables for the dashboard surfaces |

Peer dependencies for React and Vue are declared optional — install only the framework you use.

## Main entry

```ts
import { installPerformance } from '@artisanpack-ui/performance'

installPerformance({
    endpoint: '/api/performance/metrics',
    sampleRate: 100,
})
```

Wires up the Web Vitals collector, the cache client, and the Speculation Rules helper against sensible defaults. Every option is optional and falls back to `config/artisanpack/performance.php` values injected via a `<meta>` tag.

## Web Vitals

```ts
import { collectWebVitals } from '@artisanpack-ui/performance/web-vitals'

collectWebVitals({
    endpoint: '/api/performance/metrics',
    onMetric: (metric) => console.log(metric),
})
```

Emits every Web Vital sample to `endpoint` as JSON (`{ metric, value, url, session_id, context }`) — matching the `RawMetric` row shape ingested by `MetricsApiController`.

## Speculative Rules

```ts
import { installSpeculationRules } from '@artisanpack-ui/performance/speculative-rules'

installSpeculationRules({
    prefetch: [{ urls: ['/products', '/about'], eagerness: 'moderate' }],
    prerender: [{ urls: ['/checkout'], eagerness: 'conservative' }],
})
```

Same payload as `@speculativeRules` emits server-side; useful for SPA transitions that mutate the speculation set after the initial navigation.

## React

```tsx
import {
    PerformanceDashboard,
    MetricsChart,
    useWebVitals,
} from '@artisanpack-ui/performance/react'

function Vitals() {
    const vitals = useWebVitals()
    return <MetricsChart metric="LCP" data={vitals.LCP} />
}
```

Dashboard components read from the same admin API endpoints (`/api/performance/admin/*`) that the Livewire components use.

## Vue

```vue
<script setup>
import {
    PerformanceDashboard,
    MetricsChart,
    useWebVitals,
} from '@artisanpack-ui/performance/vue'

const vitals = useWebVitals()
</script>

<template>
    <MetricsChart metric="LCP" :data="vitals.LCP" />
</template>
```

The composable returns reactive refs so templates re-render as new samples arrive.

## REST endpoints

The React/Vue components wrap these endpoints — useful if you're building your own UI:

| Method + path | Purpose |
|---|---|
| `POST /api/performance/metrics` | Ingest a Web Vitals sample |
| `GET /api/performance/admin/dashboard` | Dashboard summary payload |
| `GET /api/performance/admin/chart` | Time-series chart data for a metric |
| `GET /api/performance/admin/queries` | Slow-query and N+1 rows |
| `GET /api/performance/admin/cache` | Cache statistics |
| `POST /api/performance/admin/cache` | Cache mutation actions (purge, warm) |
| `GET /api/performance/admin/recommendations` | Ranked recommendations |

Every admin endpoint requires the same authorization your Livewire dashboard route uses — the package does not register a gate itself.
