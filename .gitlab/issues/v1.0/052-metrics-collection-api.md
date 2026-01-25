# Create metrics collection API

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::8" ~"Area::Backend"

## Problem Statement

Frontend metrics need a backend API to receive and store them.

## Proposed Solution

Create API endpoint and controller for receiving performance metrics.

## Acceptance Criteria

- [ ] Create `src/Http/Controllers/Api/MetricsApiController.php`
- [ ] POST endpoint for receiving metrics
- [ ] Validate incoming metric data
- [ ] Store raw metrics (optional)
- [ ] Aggregate metrics by period
- [ ] Rate limiting to prevent abuse
- [ ] API routes registered
- [ ] Unit tests for API

## Use Cases

1. JavaScript sends metrics to API
2. API validates and stores metrics
3. Metrics aggregated for dashboard

## Additional Context

```php
// API Endpoint
POST /api/performance/metrics
{
    "name": "LCP",
    "value": 2100,
    "delta": 2100,
    "id": "v3-1234567890",
    "page": "/products",
    "connection": "4g"
}

// Response
{
    "success": true
}
```

**Routes:**
```php
Route::prefix('api/performance')
    ->middleware(['api', 'throttle:60,1'])
    ->group(function () {
        Route::post('/metrics', [MetricsApiController::class, 'store']);
    });
```

**Config:**
```php
'routes' => [
    'enabled' => true,
    'api_prefix' => 'api/performance',
    'api_middleware' => ['api'],
],
```

---

**Related Issues:**
- #051 (Web Vitals JavaScript)
