# Services

All services are singletons resolved from the container and exposed via the `Performance` facade. The top-level `PerformanceService` composes every other service and is what the facade resolves to.

## PerformanceService — `Performance::…`

The central service. Every method below is available directly on the `Performance` facade.

### Feature detection

| Method | Purpose |
|---|---|
| `isFeatureEnabled(string $feature): bool` | Reads `artisanpack.performance.features.<name>` |

### Image optimization

| Method | Purpose |
|---|---|
| `images(): ImageService` | The underlying image service |
| `responsiveImages(): ResponsiveImageGenerator` | Responsive-variant generator |
| `optimizeImage(string $path, array $options = []): array` | Full pipeline — formats × sizes |
| `convertToWebP(string $path, int $quality = 80): string` | Convert to WebP |
| `convertToAvif(string $path, int $quality = 70): string` | Convert to AVIF |
| `getDominantColor(string $path): string` | Hex `#rrggbb` for LQIP |
| `getResponsiveSrcset(string $path, array $sizes): string` | Build a `srcset` attribute |
| `generateResponsiveVariants(string $path, ?array $sizes = null, ?array $formats = null): array` | Generate every size × format |

### Scripts, CSS, resource hints

| Method | Purpose |
|---|---|
| `scripts(): ScriptManager` | Script manager (lazy) |
| `script(string $src): ScriptRegistration` | Register a script; returns fluent registration |
| `getScripts(): array` | All registered scripts in priority order |
| `renderScripts(): string` | Render every registered script as HTML |
| `criticalCss(): CriticalCssExtractor` | Critical CSS extractor (lazy) |
| `resourceHints(): ResourceHintInjector` | Resource hint injector (lazy) |

### Speculative loading

| Method | Purpose |
|---|---|
| `speculativeRules(): SpeculativeRulesGenerator` | Speculation Rules generator |
| `prefetchManager(): PrefetchManager` | Prefetch URL manager |
| `prerenderManager(): PrerenderManager` | Prerender URL manager |
| `prefetch(string\|array $urls, string $priority = 'moderate'): self` | Register prefetch URLs |
| `prerender(string\|array $urls, string $priority = 'conservative'): self` | Register prerender URLs |
| `clearPrefetch(string $pattern): self` | Remove prefetch entries by pattern |
| `clearPrerender(string $pattern): self` | Remove prerender entries by pattern |

### Embeds

| Method | Purpose |
|---|---|
| `embedOptimizer(): EmbedOptimizer` | Lite YouTube/Vimeo embed optimizer |

### Caching

| Method | Purpose |
|---|---|
| `pageCache(): PageCacheManager` | Full-page cache manager |
| `fragmentCache(): FragmentCache` | Fragment cache with tags |
| `cacheInvalidator(): CacheInvalidator` | Combined page + fragment invalidator |
| `remember(string $key, int $ttl, Closure $callback): mixed` | Namespaced remember |
| `rememberForever(string $key, Closure $callback): mixed` | Namespaced remember-forever |
| `fragmentRemember(string $key, int $ttl, Closure $callback, array $tags = []): mixed` | Fragment cache remember |
| `invalidateCache(string $key): bool` | Invalidate a single namespaced key |
| `flushCache(): bool` | Flush the fragment store (refuses to flush default) |
| `invalidatePageCache(string $pattern): int` | Wildcard page-cache invalidation |
| `flushPageCache(): int` | Flush every page cache entry |
| `invalidateFragmentsByTag(string $tag): int` | Fragment invalidation by tag |
| `warmPageCache(array $urls): array` | Warm the given URLs |
| `getCachedPage(Request $request): ?array` | Read a cached page for a request |

### Monitoring

| Method | Purpose |
|---|---|
| `recordMetric(string $name, float $value, array $context = []): void` | Record a single RUM sample |
| `getRecommendations(): array` | List actionable recommendations |

## ImageService — `Performance::images()`

Backing pipeline for every image method above.

| Method | Purpose |
|---|---|
| `optimize(string $path, array $options = []): array` | Run the pipeline |
| `convertFormat(string $path, string $format, int $quality): string` | Convert to WebP / AVIF |
| `extractDominantColor(string $path): string` | Hex `#rrggbb` |
| `resize(string $path, int $width, ?int $height = null): string` | Physical resize |
| `supportsFormat(string $format): bool` | Runtime capability check for the active driver |

## Images\ResponsiveImageGenerator

| Method | Purpose |
|---|---|
| `generate(string $path, ?array $sizes, ?array $formats): array` | Generate every size × format variant |
| `generateSrcset(string $path, array $sizes, ?string $format = null): string` | Build a `srcset` string |

## Images\DominantColorExtractor

| Method | Purpose |
|---|---|
| `extract(string $path): string` | Sample the image and return `#rrggbb` |

## Services\EmbedOptimizer

| Method | Purpose |
|---|---|
| `optimize(string $html): string` | Replace `<iframe>` embeds with lite placeholders |
| `optimizeYouTube(string $videoId, array $attributes = []): string` | Emit a lite YouTube embed |
| `optimizeVimeo(string $videoId, array $attributes = []): string` | Emit a lite Vimeo embed |

## Services\MediaLibraryDetector

| Method | Purpose |
|---|---|
| `isInstalled(): bool` | Is `artisanpack-ui/media-library` autoloadable? |
| `isEnabled(): bool` | Resolve config + install state to the effective toggle |
| `status(): array` | `['installed' => bool, 'enabled' => bool, 'source' => 'auto'\|'config']` |

## Database\QueryAnalyzer

| Method | Purpose |
|---|---|
| `enable(): void` | Attach the DB listener for the current request |
| `disable(): void` | Detach the listener |
| `queries(): array` | Every query captured this request |
| `total(): float` | Total ms across captured queries |

## Database\N1Detector

| Method | Purpose |
|---|---|
| `enable(): void` / `disable(): void` | Toggle listener |
| `detected(): array` | Normalized signatures that crossed the threshold |
| `reset(): void` | Clear captured signatures for the current request |

## Database\SlowQueryLogger

| Method | Purpose |
|---|---|
| `enable(): void` / `disable(): void` | Toggle listener |
| `log(string $query, float $timeMs, array $bindings = []): void` | Record a slow query |
| `recent(int $limit = 50): Collection` | Read the latest rows |

## Database\IndexSuggester

| Method | Purpose |
|---|---|
| `suggest(): array` | Suggested indexes based on captured slow queries |
| `generateMigration(array $suggestions, ?string $path = null): string` | Write a migration file |

## Cache\PageCacheManager

| Method | Purpose |
|---|---|
| `getCachedResponse(Request $request): ?array` | Read the cached payload for a request |
| `cacheResponse(Request $request, Response $response): void` | Store a response |
| `warmPageCache(array $urls): array` | Warm the given URLs |
| `invalidatePattern(string $pattern): int` | Invalidate entries by wildcard |
| `flush(): int` | Flush every stored page |

## Cache\FragmentCache

| Method | Purpose |
|---|---|
| `remember(string $key, int $ttl, Closure $callback, array $tags = []): mixed` | Fragment remember |
| `forget(string $key): bool` | Forget a fragment |
| `invalidateTag(string $tag): int` | Forget every fragment attached to a tag |

## Cache\CacheInvalidator

| Method | Purpose |
|---|---|
| `invalidatePagePattern(string $pattern): int` | Delegates to `PageCacheManager` |
| `flushPageCache(): int` | Flush pages |
| `invalidateFragmentTag(string $tag): int` | Delegates to `FragmentCache` |

## Cache\CacheStatistics

| Method | Purpose |
|---|---|
| `hits(): int` / `misses(): int` | Counters since last reset |
| `hitRate(): float` | 0–1 hit ratio |
| `summary(): array` | Dashboard payload |

## Cache\CacheStrategyManager

| Method | Purpose |
|---|---|
| `driver(?string $name = null): CacheStrategy` | Resolve a strategy (file, redis, memcached) |
| `extend(string $name, Closure $factory): void` | Register a custom strategy |

## Monitoring\WebVitals

| Method | Purpose |
|---|---|
| `record(string $metric, float $value, array $context = []): void` | Ingest a browser sample |
| `metrics(): array` | Supported metric keys (`LCP`, `CLS`, `FID`, `INP`, `TTFB`, `FCP`) |

## Monitoring\MetricsAggregator

| Method | Purpose |
|---|---|
| `aggregate(Carbon $date): int` | Roll raw metrics into `performance_metrics` for a day |
| `backfill(int $days): int` | Aggregate the last N days |

## Monitoring\RecommendationEngine

| Method | Purpose |
|---|---|
| `recommendations(): array` | Ranked list of recommendations for the dashboard |
| `dismiss(string $id): void` | Session-dismiss a recommendation |

## Speculative\PrefetchManager

| Method | Purpose |
|---|---|
| `register(string\|array $urls, string $priority = self::DEFAULT_PRIORITY): void` | Register URLs |
| `all(): array` | Registered URLs |
| `clear(string $pattern): int` | Remove by pattern |

## Speculative\PrerenderManager

Same shape as `PrefetchManager` — separate registry for prerender URLs.

## Speculative\SpeculativeRulesGenerator

| Method | Purpose |
|---|---|
| `generate(): string` | Render the `<script type="speculationrules">` block |
| `payload(): array` | Raw payload for callers that want to serialize themselves |

## JavaScript\ScriptManager

| Method | Purpose |
|---|---|
| `register(string $src): ScriptRegistration` | Register a script; returns a fluent registration |
| `all(): array` | All registered scripts in priority order |
| `render(): string` | Render every script as HTML |

The `ScriptRegistration` returned by `register()` supports fluent strategies: `defer()`, `async()`, `module()`, `inline()`, `onInteraction()`, `onVisible()`, `onIdle()`, `attributes(array)`, `priority(int)`.

## Css\CriticalCssExtractor

| Method | Purpose |
|---|---|
| `extract(string $html): string` | Extract critical CSS from a rendered HTML string |
| `extractFromUrl(string $url): string` | Fetch a URL and extract |

## Output\ResourceHintInjector

| Method | Purpose |
|---|---|
| `preconnect(string $url, bool $crossorigin = false): self` | Register a preconnect |
| `dnsPrefetch(string $url): self` | Register a dns-prefetch |
| `preload(string $url, string $as, array $attrs = []): self` | Register a preload |
| `all(): array` | Registered hints |
| `renderTags(): string` | HTML `<link>` block |
| `renderHeader(): string` | RFC 8288 `Link:` header value |

## Output\HtmlMinifier

| Method | Purpose |
|---|---|
| `minify(string $html): string` | Return a minified HTML string |
