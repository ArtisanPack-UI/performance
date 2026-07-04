# View Customization

The performance package ships every user-facing view as an overridable
template. This guide is the reference for the three levers you have —
publishing the templates for direct edits, tweaking component props and
slots without touching templates, and layering CSS custom properties on
top of the base stylesheet.

## Publishing Views

Publish the Blade templates when you want to fork the markup itself
(different class names, different DOM structure, additional wrapper
elements):

```bash
php artisan vendor:publish --tag=performance-views
```

Templates land at `resources/views/vendor/artisanpack-ui/performance/`
and Laravel prefers the published copy over the package copy at render
time. Directory layout under that root:

```
components/
├── critical-css.blade.php
├── lazy-image.blade.php
├── perf-embed.blade.php
├── perf-prefetch.blade.php
├── perf-script.blade.php
├── resource-hints.blade.php
├── responsive-image.blade.php
└── speculative-rules.blade.php
livewire/
├── cache-manager.blade.php
├── metrics-chart.blade.php
├── performance-dashboard.blade.php
├── query-analyzer.blade.php
└── recommendations-panel.blade.php
```

You can also publish the base CSS and the JS bundle if you want to fork
those:

```bash
php artisan vendor:publish --tag=performance-css
php artisan vendor:publish --tag=artisanpack-performance-js
```

- CSS publishes to `resources/css/vendor/artisanpack-ui/performance.css`
- JS publishes to `resources/js/vendor/artisanpack-performance/`

Republishing overwrites your local edits, so keep them under source
control and re-publish deliberately.

## Blade Component Props

Every prop below is optional. Attributes not listed pass through to the
outer element via the component's attribute bag.

### `<x-perf-lazy-image />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `src` | `string` | — | Image source URL (required). |
| `alt` | `string` | `''` | Alt text. Falls back to an empty string; supply one for accessibility. |
| `width` | `?int` | `null` | Rendered width. Sets `width` and CSS `aspect-ratio` when height is also present. |
| `height` | `?int` | `null` | Rendered height. |
| `lazy` | `bool` | `true` | Emit `loading="lazy"` and set up the intersection-observer placeholder. |
| `placeholder` | `?string` | `null` | Placeholder URL (dominant color, SQIP, blur-hash, etc.). |
| `dominantColor` | `?string` | `null` | Optional hex color used as a background before the image paints. |
| `blurSrc` | `?string` | `null` | Blur-up thumbnail source. |
| `fetchpriority` | `?string` | `null` | `high` / `low` / `auto`. |
| `threshold` | `?string` | `null` | Intersection-observer root margin (e.g. `200px`). |
| `sizes` | `?string` | `null` | `sizes` attribute. |
| `srcset` | `?string` | `null` | Explicit `srcset`. When absent, `<x-perf-responsive-image>` is a better fit. |
| `class` | `?string` | `null` | Extra classes merged into the wrapper. |

### `<x-perf-responsive-image />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `src` | `string` | — | Base image source (required). |
| `sizes` | `?array` | `null` | Width breakpoints to generate. Defaults to the package's configured sizes. |
| `sizesAttr` | `?string` | `null` | Explicit `sizes` attribute (overrides the derived one). |
| `width` | `?int` | `null` | Rendered width. |
| `height` | `?int` | `null` | Rendered height. |
| `lazy` | `bool` | `true` | Enable lazy loading. |
| `placeholder` | `?string` | `null` | Placeholder URL. |
| `dominantColor` | `?string` | `null` | Placeholder color. |
| `fetchpriority` | `?string` | `null` | `high` / `low` / `auto`. |
| `formats` | `?array` | `null` | Modern formats to emit (`['webp', 'avif']`). |
| `class` | `?string` | `null` | Extra classes. |

### `<x-perf-script />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `src` | `string` | — | Script URL (required). |
| `strategy` | `?string` | config default | `defer`, `async`, `module`, `inline`, or `conditional`. |
| `priority` | `?int` | `null` | Sort key for the script queue. |
| `name` | `?string` | `null` | Handle emitted as `data-script-name`. |
| `loadOn` | `?string\|array` | `null` | Conditional triggers (`visible`, `idle`, `click`, `mouseover`, `network:4g`). Passing this implicitly switches strategy to `conditional`. |
| `target` | `?string` | `null` | Selector for viewport / interaction triggers. |

Any additional HTML attributes (`integrity`, `crossorigin`, `nonce`,
`type` for non-conditional strategies, custom `data-*`) pass through
unchanged.

### `<x-perf-conditional-script />`

Identical prop surface to `<x-perf-script />` except `strategy` is
forced to `conditional` and `loadOn` defaults to `visible`.

### `<x-perf-embed />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `provider` | `string` | — | `youtube`, `vimeo`, `twitter` (or `x`). |
| `id` | `string` | — | Provider-specific ID. |
| `title` | `?string` | provider default | Accessible label for the facade / iframe. |
| `lazy` | `bool` | `true` | Emit a facade that swaps in the provider iframe on click. |
| `showFacade` | `bool` | `true` | When lazy is true, whether to render a thumbnail/play button. |
| `thumbnail` | `?string` | provider CDN | Override the facade thumbnail. |
| `params` | `array` | `[]` | Provider params (`['start' => 30, 'autoplay' => true]`). |
| `width` | `?int` | `null` | Rendered width. |
| `height` | `?int` | `null` | Rendered height. |
| `class` | `?string` | `null` | Extra classes. |

### `<x-perf-prefetch />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `urls` | `array\|string` | — | One or more URLs / route names. |
| `as` | `?string` | `null` | Optional resource type (`script`, `style`, `image`). |
| `crossorigin` | `?string` | `null` | Cross-origin mode. |

### `<x-perf-speculative-rules />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `prefetch` | `?array` | `null` | Prefetch rules override. |
| `prerender` | `?array` | `null` | Prerender rules override. |

Both keys accept the raw
[Speculation Rules API](https://developer.mozilla.org/en-US/docs/Web/API/Speculation_Rules_API)
`urls`/`where` shape. Omitting them makes the component emit the rules
built from `artisanpack.performance.speculative_loading` config.

### `<x-perf-resource-hints />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `hints` | `?array` | `null` | Inline descriptors (`['rel' => 'preconnect', 'href' => 'https://cdn.example']`). Omitting reads from the injector singleton. |
| `only` | `?array\|string` | `null` | Filter — only render hints whose `rel` matches. |

### `<x-perf-critical-css />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `route` | `?string` | current route | Explicit route name to look up in the cache. |

## Livewire Component Props and Slots

The dashboard components use the same public prop convention as Laravel
components — set them via `<livewire:perf-performance-dashboard :label="…" />`
or `Livewire::mount(PerformanceDashboard::class, ['labels' => [...]])`.

### `<livewire:perf-performance-dashboard />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `class` | `string` | `''` | Extra classes on the outer wrapper. |
| `cardClasses` | `string` | `''` | Extra classes for the header container. |
| `dateRange` | `string` | `'7d'` | Initial date range (`24h`, `7d`, `30d`, `90d`). |
| `activeTab` | `string` | `'overview'` | Initial tab. |
| `labels` | `array` | `[]` | Label overrides. Keyed by canonical label name; unmatched keys are ignored. |

Available slots (via `<x-slot:name>`):

| Slot | Description |
| ---- | ----------- |
| `header` | Rendered above the toolbar, inside a `.performance-dashboard__header` block. |
| `beforeMetrics` | Rendered above the metric cards. |
| `afterRecommendations` | Rendered below the recommendations block. |
| `footer` | Rendered at the very bottom of the dashboard. |

### `<livewire:perf-cache-manager />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `labels` | `array` | `[]` | Override individual button/label strings — supported keys: `purge`, `warm`, `flush`, `invalidate`. |

### `<livewire:perf-query-analyzer />`

| Prop | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `labels` | `array` | `[]` | Override the exported CSV / table labels. |
| `dateRange` | `?string` | config default | Same shape as the dashboard's range. |

### `<livewire:perf-metrics-chart />` and `<livewire:perf-recommendations-panel />`

Both accept a `labels` prop for the same purpose. See each component's
class docblock for the specific label keys.

## CSS Variables

The base stylesheet lives at `resources/css/performance.css` and defines
every visual token as a CSS custom property. Override values by setting
them at any wrapping scope:

```css
:root {
    --perf-color-good: #22c55e;
    --perf-color-poor: #ef4444;
    --perf-radius: 0.75rem;
    --perf-font-family: "InterVariable", sans-serif;
}
```

### Reference

| Variable | Default | What it controls |
| -------- | ------- | ---------------- |
| `--perf-color-surface` | `#ffffff` | Panel backgrounds |
| `--perf-color-surface-alt` | `#f8fafc` | Recommendation card background |
| `--perf-color-border` | `#e2e8f0` | Table row / card borders |
| `--perf-color-text` | `#0f172a` | Body text |
| `--perf-color-muted` | `#64748b` | Secondary text |
| `--perf-color-good` | `#16a34a` | Passing Core Web Vitals badge |
| `--perf-color-needs-improvement` | `#f59e0b` | Warning badge |
| `--perf-color-poor` | `#dc2626` | Failing badge |
| `--perf-color-unknown` | `#94a3b8` | Missing-data badge |
| `--perf-color-priority-high` | `#dc2626` | High-priority recommendation border |
| `--perf-color-priority-medium` | `#f59e0b` | Medium-priority recommendation border |
| `--perf-color-priority-low` | `#0284c7` | Low-priority recommendation border |
| `--perf-color-button-bg` | `#0f172a` | Button background |
| `--perf-color-button-text` | `#ffffff` | Button text |
| `--perf-font-family` | `system-ui, …` | Font stack |
| `--perf-font-size-base` | `0.9375rem` | Body font size |
| `--perf-font-size-heading` | `1.25rem` | Heading font size |
| `--perf-space-xs` | `0.25rem` | Space scale (extra-small) |
| `--perf-space-sm` | `0.5rem` | Space scale (small) |
| `--perf-space-md` | `1rem` | Space scale (medium) |
| `--perf-space-lg` | `1.5rem` | Space scale (large) |
| `--perf-radius` | `0.5rem` | Card / panel border radius |
| `--perf-shadow` | subtle shadow | Card / panel shadow |

### Dark Mode

Two entry points cover both auto and manual theming:

```css
/* System-driven — no code changes required. */
@media (prefers-color-scheme: dark) {
    :root { --perf-color-surface: #0f172a; /* … */ }
}

/* Manual toggle — add or remove `perf-theme-dark` on <html> or any wrapper. */
.perf-theme-dark { --perf-color-surface: #0f172a; /* … */ }
```

The bundled stylesheet already includes dark-mode variables for both
entry points. Overwrite them the same way you overwrite the light-mode
values.

## Component Extension

For deeper changes than props allow, extend the component class.

### Custom perf-script variant

```php
<?php

namespace App\View\Components\Performance;

use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;
use ArtisanPackUI\Performance\View\Components\PerfScript;

final class AnalyticsScript extends PerfScript
{
    public function __construct( string $src )
    {
        parent::__construct(
            src: $src,
            strategy: 'defer',
            name: 'analytics',
        );
    }

    protected function applyStrategy( ScriptRegistration $registration ): void
    {
        // Route all analytics scripts through the conditional strategy
        // when Do-Not-Track is respected in the current request.
        if ( '1' === request()->header( 'DNT' ) ) {
            $registration->conditional();

            return;
        }

        parent::applyStrategy( $registration );
    }
}
```

Register it via `AppServiceProvider::boot()`:

```php
Blade::component( 'analytics-script', AnalyticsScript::class );
```

### Custom chart implementation

`MetricsChart` reads its Chart.js payload from the `data-chart-payload`
attribute. Extend the Livewire component to swap in a different chart
library or a server-rendered SVG:

```php
<?php

namespace App\Livewire\Performance;

use ArtisanPackUI\Performance\Livewire\MetricsChart as BaseMetricsChart;

class MetricsChart extends BaseMetricsChart
{
    public function render()
    {
        return view( 'components.performance.metrics-chart-apex', [
            'series' => $this->buildApexSeries(),
        ] );
    }

    protected function buildApexSeries(): array
    {
        return [
            [
                'name' => 'LCP',
                'data' => $this->timeSeriesFor( 'LCP' ),
            ],
        ];
    }
}
```

Then re-alias the Livewire component in a service provider:

```php
\Livewire\Livewire::component(
    'perf-metrics-chart',
    \App\Livewire\Performance\MetricsChart::class,
);
```

### Method overriding

Every non-final protected method on the bundled components is intended
to be overridable. Common extension points:

| Component | Method | Purpose |
| --------- | ------ | ------- |
| `PerfScript` | `applyStrategy(ScriptRegistration)` | Switch strategies at request time. |
| `PerfEmbed` | `resolveFacade()` | Route unusual embed shapes through a custom optimizer. |
| `CriticalCss` | `resolveRoute(?string)` | Map non-standard route resolution (e.g. subdomain-based). |
| `PerformanceDashboard` | `refreshMetrics()` | Add custom telemetry before the metric fetch fires. |
| `CacheManager` | `flushAll()` | Wrap the base action with authorization / audit logging. |

## JavaScript Customization

The bundled JS lives at `resources/js/`:

- `web-vitals.js` — RUM collector.
- `speculative-rules.js` — Speculation Rules polyfill + embed activator.
- `metrics-chart.js` — Chart.js bootstrap.

### Event hooks

`speculative-rules.js` exposes hooks on `window.PerformanceSpeculativeLoader`:

```js
window.PerformanceSpeculativeLoader.inject({
    prerender: [ { where: { href_matches: '/pricing' }, eagerness: 'moderate' } ],
});
```

`web-vitals.js` respects the following dataset attributes when discovering
the endpoint:

- `<script data-perf-vitals-endpoint="/api/perf/metrics">` — override the
  POST target.
- `<script data-perf-vitals-sampled-out="true">` — client-side sampling
  gate that suppresses the beacon entirely.

### Alpine.js customization

The Livewire templates work without Alpine, but attach freely if it is
already on the page:

```blade
<div
    x-data="{ open: false }"
    @toggle-cache-controls.window="open = !open"
>
    <livewire:perf-cache-manager />
    <div x-show="open"> … custom drawer … </div>
</div>
```

Wire your Alpine directives against Livewire's DOM by dispatching
browser events from a subclassed component — this avoids fighting the
Livewire morph pass on every render.

### JS configuration options

`web-vitals.js` reads its runtime config from the `data-` attributes on
its own `<script>` tag. Any of the following can be set when you include
the bundle:

| Attribute | Effect |
| --------- | ------ |
| `data-perf-vitals-endpoint` | Endpoint URL. Defaults to `/api/performance/metrics`. |
| `data-perf-vitals-sampled-out` | When `true`, disable the beacon entirely. |
| `data-perf-vitals-route` | Explicit route name sent with each sample. |
| `data-perf-vitals-device` | Client-side device classifier (`desktop`, `mobile`, `tablet`). |
| `data-perf-vitals-connection` | Manual connection type override (rare). |

`speculative-rules.js` uses `data-prefetch` / `data-prerender` markers on
anchors. Add them via server-rendered HTML or via
`document.querySelectorAll` in the host app to trigger the fallback
`<link rel="prefetch">` injection on browsers that lack the
Speculation Rules API.

## Where to go next

- `README.md` — feature-by-feature configuration reference.
- `docs/security/audit-1.0.0.md` — the pre-1.0 security audit.
- `docs/development/code-style.md` — code-style tooling / pre-commit
  workflow.
- The `resources/views` directory itself — the templates are short and
  well-commented; reading them is often faster than searching for the
  hook you want.
