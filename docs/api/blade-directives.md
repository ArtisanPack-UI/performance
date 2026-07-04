# Blade directives

Every directive is registered by `PerformanceServiceProvider` from helpers under `src/Support/`.

## Speculative loading

### `@speculativeRules`

Emits the `<script type="speculationrules">` block for every URL registered via `Performance::prefetch()` and `Performance::prerender()` on the current request.

```blade
@speculativeRules
```

## Resource hints

Individual hints — each takes a URL and (where applicable) additional attributes:

| Directive | Emits |
|---|---|
| `@preconnect($url)` | `<link rel="preconnect" href="…">` |
| `@dnsPrefetch($url)` | `<link rel="dns-prefetch" href="…">` |
| `@preload($url, $as)` | `<link rel="preload" href="…" as="…">` |
| `@prefetch($url)` | `<link rel="prefetch" href="…">` |

For batch-registered hints resolved through the `ResourceHintInjector`, apply the `InjectResourceHints` middleware or use `resourceHintInjector()->renderTags()` from Blade.

## Scripts

| Directive | Emits |
|---|---|
| `@deferScript($src)` | `<script src="…" defer></script>` |
| `@asyncScript($src)` | `<script src="…" async></script>` |
| `@moduleScript($src)` | `<script src="…" type="module"></script>` |
| `@conditionalScript($src, $strategy)` | Strategy-loaded (`on-interaction`, `on-visible`, `on-idle`) script |

```blade
@deferScript('/js/analytics.js')
@conditionalScript('/js/chat.js', 'on-interaction')
```

## Critical CSS

### `@criticalCss(...)`

Inline critical CSS extracted at request time or read from a pre-generated cache entry keyed by route.

```blade
<head>
    @criticalCss
</head>
```

## Caching

### `@cache(...)` … `@endcache`

Wraps the enclosed block in a fragment-cache entry. First argument is the cache key; second is the TTL in seconds.

```blade
@cache('sidebar-featured', 900)
    @include('partials.sidebar-featured')
@endcache
```

## Monitoring

### `@perfMonitor`

Injects the RUM bootstrap script that collects Web Vitals and posts them to `monitoring.endpoint`. Place it once in the layout.

```blade
<body>
    …
    @perfMonitor
</body>
```

### `@perfMetricsChartAssets`

Emits the Chart.js runtime assets used by the `perf-metrics-chart` Livewire component. Include it in any layout that renders a metrics chart outside the dashboard.

```blade
@perfMetricsChartAssets
```
