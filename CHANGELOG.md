# Changelog

All notable changes to the `artisanpack-ui/performance` package are documented
in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-07-07

Adds two AI-powered features owned by the performance package, plus the Livewire, React, and Vue trigger surfaces that drive them.

### Added

#### AI features

- `PerformanceInsightAgent` (`performance.query_insight`) — given a slow query plus its EXPLAIN plan and schema, explains the bottleneck and suggests indexes and rewrites. Suggests only; never emits DDL. Default model `claude-sonnet-4-6`; `$cacheable = false` because the output is a point-in-time diagnostic snapshot.
- `OptimizationSuggestionAgent` (`performance.optimization_suggestion`) — reads aggregate metrics over a date range and returns ranked focus areas (impact / effort / rationale / actions), quick wins, and caveats. Short-circuits without a model call when the metrics list is empty. Default model `claude-sonnet-4-6`; `$cacheable = false`.
- Both agents self-register via the service provider's `aiFeatures()` method, so `artisanpack-ui/ai` auto-discovers them.

#### Trigger surfaces (Livewire, React, Vue)

- `QueryInsightPanel` — Livewire component `perf-ai-query-insight-panel`, React `QueryInsightPanel`, Vue `QueryInsightPanel`. All three accept the query, EXPLAIN plan, schema hint (JSON or plain text), connection driver, and observed duration; render the returned diagnosis with bottlenecks, suggested indexes, rewrites, and caveats.
- `OptimizationSuggestionPanel` — Livewire component `perf-ai-optimization-suggestion-panel`, React `OptimizationSuggestionPanel`, Vue `OptimizationSuggestionPanel`. The Livewire panel pulls aggregate rows out of `performance_metrics` directly with a bounded rolling window (`MAX_WINDOW_DAYS = 90`, `MAX_METRICS_ROWS = 2000`) so it works out of the box; the React and Vue panels accept a caller-supplied metrics batch and delegate to the client.
- React and Vue components ship inside the performance package (not in `@artisanpack-ui/react` / `@artisanpack-ui/vue`) so both frameworks are supported without adding coupling to the shared UI packages.

#### API surface

- `POST /api/performance/ai/query-insight` and `POST /api/performance/ai/optimization-suggestion` dispatch to the two agents through `AiAgentApiController`. Responses are shaped as `{ data: <agent output>, feature_key: string }`. Disabled features return `409`, missing credentials `412`, agent validation errors `422`, unknown features `404`, gate denial `403`.
- Dedicated `ai_middleware` config (default `['api', 'auth:sanctum']`) so AI endpoints do not share the anonymous stack used by the Web Vitals ingest.
- Default `performance.ai.use` authorization Gate — permissive out of the box (any authenticated user), overridable in the host's `AuthServiceProvider` to enforce stricter policies or per-tenant quotas.

#### Client (JavaScript)

- `PerformanceClient.suggestQueryInsight(input)` and `PerformanceClient.suggestOptimization(input)` methods for driving the AI endpoints from vanilla JS, React, or Vue.
- New `PerformanceAiError` class carries the HTTP status and feature key so callers can render disabled / misconfigured states without parsing exception messages.

### Changed

- `artisanpack-ui/ai: ^1.0` promoted from optional to a hard `require` in `composer.json`.
- The service provider now registers a `performance.ai.use` Gate during `boot()` (guarded so a host-registered override wins).

## [1.0.0] - 2026-07-04

Initial public release of the ArtisanPack UI Performance package. Every
feature ships opt-in via `config/artisanpack/performance.php` so an install
adds zero overhead until a feature is toggled on.

### Added

#### Core infrastructure

- `PerformanceServiceProvider` — registers configuration, migrations, event
  listeners, Blade directives, Livewire components, middleware aliases, the
  admin route file, and every console command; publishes the package's four
  publish tags (`artisanpack-performance-config`, `artisanpack-performance-js`,
  `performance-views`, `performance-css`).
- `perf:install` Artisan command — publishes the package configuration,
  optionally publishes views / JavaScript / stylesheet, runs migrations,
  validates PHP and Laravel versions plus storage writability, clears
  configuration and view caches, and prints the dashboard gate stub with
  next-step guidance. Idempotent; skips migrations already run and only
  overwrites existing published files with `--force`. Flags: `--config`,
  `--views`, `--js`, `--css`, `--migrations`, `--skip-migrate`,
  `--skip-checks`, `--force`, and standard `--no-interaction`.
- `Performance` facade + container singleton for programmatic access to
  every service.
- Global helper functions autoloaded via Composer for the common
  request-lifecycle operations (feature toggles, cache reads, hint
  registration, RUM ingestion).
- Event system — package emits `PerformanceMetricRecorded`,
  `CacheHit`, `CacheMissed`, `SlowQueryDetected`, `N1QueryDetected`,
  `ImageOptimized`, and `RecommendationGenerated` events so applications
  can subscribe without patching the package.
- Database migrations for performance metrics, slow queries, cache
  entries, and optimized images tables.

#### Image optimization

- WebP and AVIF conversion pipeline with quality controls per format and
  per source image.
- Responsive image generation (`ResponsiveImageGenerator`) with configurable
  breakpoints, `srcset`/`sizes` output, and per-breakpoint cropping rules.
- Lazy loading with placeholder generation — low-quality image placeholders
  (LQIP), dominant-color placeholders via `DominantColorExtractor`, and
  configurable intersection-observer thresholds.
- `fetchpriority` hint support on above-the-fold imagery.
- Queued image processing so uploads never block the request thread.
- `perf:generate-webp` Artisan command for bulk conversion of the media
  library.

#### JavaScript & CSS

- Script loading strategies — `AsyncStrategy`, `DeferStrategy`,
  `ModuleStrategy`, `InlineStrategy`, and `ConditionalStrategy`, all
  routed through `ScriptManager`.
- Critical CSS extraction (`CriticalCssExtractor`) with per-route caching,
  above-the-fold selectors, and inline injection in the document head.
- Resource hints (`preload`, `prefetch`, `preconnect`, `dns-prefetch`)
  with auto-detected hints for critical assets and a manual registration
  API for developer-supplied hints.
- Blade directives and view components for hint registration and script
  strategy selection.
- `perf:critical-css` Artisan command for regenerating critical CSS across
  the site.

#### Speculative loading

- Speculation Rules API support with configurable eagerness levels and
  URL pattern matching (`UrlPatternMatcher`).
- `<x-perf-prefetch>` component for per-link prefetch/prerender opt-in.
- Embed optimizer for YouTube / Vimeo / Twitter embeds — replaces heavy
  iframes with click-to-load placeholders.

#### Caching

- Full-page caching with configurable exclude routes, exclude closures,
  query-string handling, and vary-by keys.
- Fragment caching keyed by tags with TTL and cache-warming support.
- Cache warming CLI command (`perf:warm-cache`) that pre-fetches configured
  URLs or Livewire component states on a schedule.
- Automatic invalidation via `CacheInvalidator` — wire model events or
  application events to a cache-tag map to clear related fragments on
  writes.
- `CacheStrategyManager` — selects between page cache, fragment cache, and
  model-level caching based on the request context.
- `CachingEloquentBuilder` — opt-in Eloquent macro that transparently
  caches query results with automatic tag invalidation.
- `perf:purge-cache` Artisan command with `--type=page|fragment|all` and
  `--pattern`/`--tag` targeting.

#### Database optimization

- N+1 query detection (`N1Detector`) that logs offending relations and
  suggests eager-load fixes.
- Slow query logging (`SlowQueryLogger`) with configurable threshold and
  aggregation.
- Index suggestions (`IndexSuggester`) based on observed slow queries.
- `perf:suggest-indexes` Artisan command for offline analysis of the slow
  query log.
- `QueryAnalyzer` for grouping slow queries by normalized SQL fingerprint.

#### Server-side

- HTML minification middleware (`MinifyHtml` / `perf.minify`) — runs HTML
  responses through `HtmlMinifier`, skipping streamed / binary responses,
  non-2xx status codes, non-HTML content types, and routes matched by
  `html_minification.exclude_routes`. Updates `Content-Length` when the
  original response declared one.
- HTTP/2 Early Hints middleware (`EarlyHints` / `perf.early-hints`) —
  emits an HTTP 103 interim response with preload / preconnect Link
  headers before the controller runs, and mirrors the same hints into the
  final response so 103-unaware intermediaries (older caches, simple
  reverse proxies) still see the metadata. Hints merge config-supplied
  `manual_hints` with auto-detected entries from `ResourceHintInjector`;
  both sources are deduplicated by `(rel, href, as)`. SAPI emission uses
  `header()` + `flush()` (or `fastcgi_finish_request` under FPM) and is
  swappable for tests.
- `OutputBuffer` (`ArtisanPackUI\Performance\Output\OutputBuffer`) —
  instance-scoped wrapper around PHP's output-buffering primitives with
  nested `start()`/`end()`, exception-safe `capture()`, and a transformer
  pipeline used by downstream response mutators. Registered as a container
  singleton with an Octane request-received reset hook so a leaked open
  buffer can't carry across requests.

#### Performance monitoring

- Core Web Vitals RUM collector — LCP, FID, CLS, INP, and TTFB posted to
  the ingest endpoint as they resolve.
- `@perfMonitor` Blade directive and `Support\MonitorDirectives` helper —
  bootstraps the Core Web Vitals RUM collector by emitting an inline
  configuration script (endpoint, sample rate, CSRF token, route/page
  context) followed by a deferred `<script type="module">` tag pointing at
  the published `web-vitals.js`. Accepts per-call overrides (`endpoint`,
  `sampleRate`, `extra`, `src`) for per-page customization. Renders nothing
  when `monitoring.enabled` or `monitoring.collect_web_vitals` is off so
  opted-out environments don't ship dead JavaScript.
- `resources/js/web-vitals.js` Core Web Vitals collector — ESM module that
  imports the `web-vitals` npm package and posts metrics to the configured
  endpoint as they resolve. Session-level sampling, connection / device-type
  capture, `sendBeacon`-first transport with a `fetch({ keepalive: true })`
  fallback, and CSRF token forwarding via `X-CSRF-TOKEN`. Published under
  `resources/js/vendor/artisanpack-performance/` via the
  `artisanpack-performance-js` publish tag.
- Livewire performance dashboard — overview, per-page metrics, cache
  manager, query analyzer, and recommendations panel.
- `RecommendationEngine` — scans aggregated metrics for LCP hotspots,
  N+1 offenders, oversized assets, and cache-miss patterns.
- `MetricsAggregator` — rolls raw metric events into 24h, 7d, 30d, and
  90d buckets, dispatched by the `perf:aggregate-metrics` scheduled
  command.

#### Admin JSON API

- New endpoints under `POST/GET /api/performance/admin/*` back the
  React/Vue components (Livewire dashboards previously kept state on the
  server and could not be consumed from a bundler-based front-end). All
  endpoints run through `AdminApiController::authorizeAdmin()`, which
  resolves the ability name from `artisanpack.performance.dashboard.gate`
  and falls back to `view-performance-dashboard` when the config is
  blanked out — a missing or empty config cannot inadvertently expose the
  endpoints.
  - `GET /admin/dashboard` (`DashboardAdminApiController`) — overview +
    pages + cache summary payload keyed by the requested range
    (`24h|7d|30d|90d`).
  - `GET /admin/chart` (`ChartAdminApiController`) — chart payload
    matching the shape the bundled `metrics-chart.js` renders; accepts
    `metrics`, `range`, `show_threshold`, `type`.
  - `GET /admin/cache` + `POST /admin/cache/actions`
    (`CacheAdminApiController`) — page and fragment cache snapshot plus
    mutating actions (`flush`, `warm`, `invalidate-key`,
    `invalidate-tag`).
  - `GET /admin/queries` + `GET /admin/queries/export`
    (`QueriesAdminApiController`) — grouped slow-query rows with
    `IndexSuggester` hints; CSV export uses a formula-injection
    sanitizer.
  - `GET /admin/recommendations` + `POST /admin/recommendations/actions`
    (`RecommendationsAdminApiController`) — recommendation list with
    `apply`/`dismiss`/`reset` actions. Dismissals share the
    `artisanpack.performance.dismissed_recommendations` session key with
    the Livewire panel so users see a consistent state across both
    surfaces.
- Metrics ingest endpoint (`POST /api/performance/metrics`) — accepts
  Core Web Vitals payloads from the browser collector, validates them
  against `monitoring.sample_rate`, and dispatches
  `PerformanceMetricRecorded` for downstream storage.

#### React + Vue companion components

- Shared vanilla client at `resources/js/performance.ts` — typed
  `PerformanceClient` with methods for the dashboard, chart, cache,
  queries, recommendations, and metrics-ingest endpoints. Auto-resolves
  the CSRF token from a `<meta name="csrf-token">` tag; consumers can
  override the base URL and fetch implementation.
- React entry: `@artisanpack-ui/performance/react`. Components:
  `LazyImage`, `ResponsiveImage`, `PerfEmbed`, `PerfPrefetch`,
  `SpeculativeRules`, `PerformanceDashboard`, `MetricsChart`,
  `CacheManager`, `QueryAnalyzer`, `RecommendationsPanel`. Hook:
  `usePerformance` — thin wrapper that returns the shared client's
  methods and a small `useAsyncPayload` helper for load-on-mount
  patterns.
- Vue entry: `@artisanpack-ui/performance/vue`. Same component set as
  `.vue` SFCs plus a `usePerformance` composable that mirrors the React
  hook's API. `LazyImage`, `PerfEmbed`, and `SpeculativeRules` use
  `<Teleport>`/head-portal patterns where applicable so the injected
  `<script>` / `<link>` elements land in `<head>`.
- `package.json` at the package root declares
  `@artisanpack-ui/performance` with optional peer dependencies on
  `react`, `react-dom`, and `vue`. Sub-path exports (`./react`, `./vue`,
  `./web-vitals`, `./metrics-chart`, `./speculative-rules`) so hosts
  pull in only what they need.
- `tsconfig.json` mirrors the privacy package's setup (`ES2022`, bundler
  resolution, `jsx: preserve`, `.vue` in the include list).

#### Documentation

- `docs/` tree with feature guides for image optimization, caching,
  database optimization, speculative loading, monitoring, and the admin
  API.
- `examples/` directory with runnable snippets covering the quick-start
  path, image optimization, JavaScript / CSS optimization, caching,
  database, monitoring, and existing-app integration.
- `CHANGELOG.md` following Keep a Changelog format.
- `README.md`, `CONTRIBUTING.md`, and inline PHPDoc on every public
  class and method.

### Changed

- `illuminate/support` constraint now allows `^13.0` alongside the
  existing `^10.0|^11.0|^12.0` range. Laravel 13 requires PHP 8.3+; the
  PHP floor for users staying on older Laravel versions is unchanged.

### Security

- Admin JSON API endpoints fall back to the
  `view-performance-dashboard` ability when the configured gate name is
  blank so a mis-published config cannot inadvertently expose the
  endpoints.
- Slow-query CSV export sanitizes formula-injection payloads
  (`=`, `+`, `-`, `@`, tab, carriage return) in every cell before
  writing.
- Metrics ingest endpoint validates payload shape and rejects unknown
  metric names so a compromised browser cannot poison the aggregated
  metrics store.

### Migration notes

- Fresh install: run `php artisan perf:install --no-interaction` after
  `composer require artisanpack-ui/performance`. Every feature is off by
  default; enable only what you need in
  `config/artisanpack/performance.php`.
- Define the `view-performance-dashboard` gate (or your configured gate
  name) in `AuthServiceProvider::boot()` before the dashboard route is
  reachable.
- Add `@perfMonitor` to your main layout to start collecting Core Web
  Vitals data.
- Publish and rebuild the JavaScript bundle when integrating the
  React / Vue components: `php artisan vendor:publish --tag=artisanpack-performance-js`
  followed by your normal `npm run build`.

[Unreleased]: https://github.com/ArtisanPack-UI/performance/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ArtisanPack-UI/performance/releases/tag/v1.0.0
