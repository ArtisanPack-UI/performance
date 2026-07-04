# Dashboard integration

Three ways to expose the dashboard to your admins.

## 1. Shipped Livewire dashboard

Turn on the `dashboard` feature and define the gate.

```php
// config/artisanpack/performance.php
'features' => [
    'dashboard' => true,
    'monitoring' => true,
],

'dashboard' => [
    'enabled'      => true,
    'route_prefix' => 'admin/performance',
    'middleware'   => ['web', 'auth'],
    'gate'         => 'view-performance-dashboard',
],
```

```php
// app/Providers/AuthServiceProvider.php
Gate::define('view-performance-dashboard', function ($user) {
    return $user->hasRole('admin');
});
```

Visit `/admin/performance`. That's it.

## 2. Embed React components in an existing admin

```tsx
// resources/js/pages/admin/performance.tsx
import {
    PerformanceDashboard,
    MetricsChart,
    CacheManager,
    QueryAnalyzer,
    RecommendationsPanel,
} from '@artisanpack-ui/performance/react'

export default function PerformancePage() {
    return (
        <div className="space-y-8">
            <PerformanceDashboard range="24h" />
            <MetricsChart metrics={['lcp', 'inp', 'cls']} range="7d" showThreshold />
            <CacheManager />
            <QueryAnalyzer range="24h" />
            <RecommendationsPanel />
        </div>
    )
}
```

## 3. Embed Vue components in an existing admin

```vue
<script setup lang="ts">
import {
    PerformanceDashboard,
    MetricsChart,
    CacheManager,
    QueryAnalyzer,
    RecommendationsPanel,
} from '@artisanpack-ui/performance/vue'
</script>

<template>
    <div class="space-y-8">
        <PerformanceDashboard range="24h" />
        <MetricsChart :metrics="['lcp', 'inp', 'cls']" range="7d" show-threshold />
        <CacheManager />
        <QueryAnalyzer range="24h" />
        <RecommendationsPanel />
    </div>
</template>
```

Both React and Vue components resolve the CSRF token from
`<meta name="csrf-token">` and hit the same admin JSON API the Livewire
dashboard uses — no separate auth story.
