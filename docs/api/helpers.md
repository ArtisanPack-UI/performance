# Helper functions

Global helpers registered by `src/helpers.php`. Every helper is wrapped in a `function_exists()` guard so applications can redeclare them to override behavior.

| Function | Purpose |
|---|---|
| `performance(): PerformanceService` | The package's facade target / singleton |
| `perfFeatureEnabled(string $feature): bool` | Check whether a feature toggle is on |
| `perfOptimizeImage(string $path, array $options = []): array` | Run the image-optimization pipeline |
| `perfConvertToWebP(string $path, int $quality = 80): string` | Convert to WebP (Blade-safe — returns source path when driver can't encode) |
| `perfConvertToAvif(string $path, int $quality = 70): string` | Convert to AVIF (Blade-safe — same fallback) |
| `perfGetDominantColor(string $path): string` | Extract dominant color hex |
| `perfGetResponsiveSrcset(string $path, array $sizes): string` | Build a `srcset` string |
| `perfRemember(string $key, int $ttl, Closure $callback): mixed` | Cache remember (namespaced under `performance:`) |
| `perfRememberForever(string $key, Closure $callback): mixed` | Cache remember-forever |
| `perfInvalidateCache(string $key): bool` | Invalidate a namespaced cache key |
| `perfFlushCache(): bool` | Flush the fragment cache store (refuses when it would also flush the framework default) |
| `perfFragmentRemember(string $key, int $ttl, Closure $callback, array $tags = []): mixed` | Fragment cache remember with tags |
| `perfInvalidatePageCache(string $pattern): int` | Wildcard page-cache invalidation |
| `perfFlushPageCache(): int` | Flush every page cache entry |
| `perfInvalidateFragmentsByTag(string $tag): int` | Fragment invalidation by tag |
| `perfWarmPageCache(array $urls): array` | Warm the page cache for the given URLs |
| `perfRecordMetric(string $name, float $value, array $context = []): void` | Record a custom RUM sample |
| `perfGetRecommendations(): array` | Get performance recommendations |

## Blade-safe format conversion

`perfConvertToWebP()` and `perfConvertToAvif()` call `Performance::images()->supportsFormat()` before converting. When the active driver (GD or Imagick) can't encode the target format, they return the original `$path` unchanged instead of throwing — so templates degrade gracefully to the source image rather than 500ing. Callers that want explicit error handling should use the `Performance` facade directly.

## Namespacing

Every cache helper prefixes keys with `performance:` internally, so `perfRemember('products', …)` writes to `performance:products` in the underlying store. This keeps the package's cache entries isolated from the rest of the application's cache namespace.
