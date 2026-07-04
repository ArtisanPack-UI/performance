# Monitoring & dashboard

The RUM collector emits Web Vitals from the browser to a configurable endpoint, aggregates them on a schedule, and surfaces the results in a Livewire dashboard backed by a recommendations engine.

## 1. Enable the features

```dotenv
PERF_MONITORING=true
PERF_DASHBOARD=true
```

```php
'monitoring' => [
    'enabled'              => true,
    'collect_web_vitals'   => true,
    'endpoint'             => '/api/performance/metrics',
    'sample_rate'          => 100,  // percentage of sessions to sample
    'aggregation_interval' => 'hourly',
    'retention_days'       => 90,
],
```

## 2. Publish the client bundle

```bash
php artisan vendor:publish --tag=artisanpack-performance-js
```

## 3. Bootstrap the collector

Add the RUM monitor to your layout once. It reads the endpoint and sample rate from a `<meta>` tag injected by the directive:

```blade
<body>
    …
    @perfMonitor
</body>
```

The collector posts a JSON payload matching the `RawMetric` row shape to `monitoring.endpoint` for each Web Vital (`LCP`, `CLS`, `FID`, `INP`, `TTFB`, `FCP`).

## 4. Record custom metrics

From the browser:

```ts
import { installPerformance } from '@artisanpack-ui/performance'

installPerformance()

window.performance.mark('checkout-start')
// …
performance.recordMetric?.('checkout.duration', performance.now(), { step: 'shipping' })
```

From PHP:

```php
Performance::recordMetric('checkout.duration', 4200.0, ['step' => 'shipping']);
```

## 5. Aggregate on a schedule

Raw rows in `performance_raw_metrics` are rolled into daily buckets in `performance_metrics` by `perf:aggregate-metrics`. Schedule it hourly (or match `aggregation_interval`):

```php
// routes/console.php
Schedule::command('perf:aggregate-metrics')->hourly();
```

Backfill on demand:

```bash
php artisan perf:aggregate-metrics --backfill=30
```

## 6. Mount the dashboard

```blade
<livewire:perf-performance-dashboard />
```

The service provider does not register a gate for the dashboard components — wrap the route with your existing authorization surface (a gate, policy, `auth.admin` middleware, etc.).

## 7. Act on recommendations

```blade
<livewire:perf-recommendations-panel />
```

The recommendation engine inspects aggregated metrics, cache statistics, slow queries, and feature toggles to surface actionable suggestions ("consider enabling the page cache on `/products/*`", "index `orders.customer_id`", "reduce LCP on `home` by preloading `hero.webp`"). Dismissals are session-scoped by default.

## 8. Alert on threshold breaches

```php
'alerts' => [
    'enabled'    => true,
    'thresholds' => [
        'LCP' => 2500,  // milliseconds — p75 budget
        'CLS' => 0.1,
        'INP' => 200,
    ],
],
```

When aggregation records a p75 above the budget, `PerformanceThresholdExceeded` fires with the metric name, value, and threshold — hook a listener to notify Slack, PagerDuty, or your incident system.

## Related

- [[api/services]] — `WebVitals`, `MetricsAggregator`, `RecommendationEngine`
- [[api/models]] — `PerformanceMetric`, `RawMetric`
- [[api/events]] — `PerformanceThresholdExceeded`
- [[api/blade-directives]] — `@perfMonitor`, `@perfMetricsChartAssets`
- [[api/livewire]] — `perf-performance-dashboard`, `perf-metrics-chart`, `perf-recommendations-panel`
- [[api/javascript]] — `@artisanpack-ui/performance/web-vitals`
