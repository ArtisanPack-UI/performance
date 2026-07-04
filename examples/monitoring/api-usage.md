# Admin API usage

The admin JSON API sits behind the same gate as the Livewire dashboard
(`view-performance-dashboard` by default). Every endpoint accepts JSON
and returns JSON.

## Auth

All admin endpoints require:

1. A session cookie for an authenticated user, and
2. That user to pass the configured gate.

Set `<meta name="csrf-token" content="{{ csrf_token() }}">` in your
layout so the shipped `PerformanceClient` can find the token.

## Endpoints

### Dashboard overview

```http
GET /api/performance/admin/dashboard?range=24h
```

```json
{
    "overview": {
        "requests": 12483,
        "lcp_p75": 1820,
        "inp_p75": 148,
        "cls_p75": 0.04
    },
    "pages": [...],
    "cache": { "hit_rate": 0.83, "size_mb": 142 }
}
```

### Chart payload

```http
GET /api/performance/admin/chart?metrics[]=lcp&metrics[]=inp&range=7d&show_threshold=1
```

### Cache manager

```http
GET  /api/performance/admin/cache
POST /api/performance/admin/cache/actions
Content-Type: application/json
X-CSRF-TOKEN: ...

{ "action": "flush" }
{ "action": "warm" }
{ "action": "invalidate-tag", "tag": "nav" }
{ "action": "invalidate-key", "key": "products/*" }
```

### Slow queries

```http
GET /api/performance/admin/queries?range=24h
GET /api/performance/admin/queries/export?range=7d
```

### Recommendations

```http
GET  /api/performance/admin/recommendations
POST /api/performance/admin/recommendations/actions

{ "action": "dismiss", "id": "rec-abc" }
{ "action": "apply",   "id": "rec-abc" }
{ "action": "reset" }
```

## From your own front-end

```ts
import { PerformanceClient } from '@artisanpack-ui/performance'

const client = new PerformanceClient({ baseUrl: '/api/performance/admin' })

const dashboard = await client.dashboard({ range: '24h' })
const chart     = await client.chart({ metrics: ['lcp', 'inp'], range: '7d' })

await client.cache.action('flush')
await client.cache.action('invalidate-tag', { tag: 'nav' })

const recs = await client.recommendations.list()
await client.recommendations.action('dismiss', { id: recs[0].id })
```

## Metrics ingest

The one endpoint that does *not* sit behind the admin gate is the
metrics ingest endpoint. It accepts CSRF-protected POSTs from the
browser Web Vitals collector.

```http
POST /api/performance/metrics
Content-Type: application/json
X-CSRF-TOKEN: ...

{
    "name": "LCP",
    "value": 1820,
    "id": "v3-1699999999-1234567890",
    "route": "products.show",
    "url": "/products/mug"
}
```

`sample_rate` in `artisanpack.performance.monitoring` throttles what
the browser sends; the server also validates the metric name against
an allowlist so custom metrics from a compromised browser cannot
poison the store.
