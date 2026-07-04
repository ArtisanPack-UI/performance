# Events

Listen for these in `EventServiceProvider` (or attach a closure in a service provider's `boot()` method) to extend behavior without editing the package.

## Cache

| Event | Payload | When fired |
|---|---|---|
| `CachePurged` | `array $keys`, `string $reason` | Any invalidation through `CacheInvalidator` |
| `CacheWarmed` | `array $urls`, `int $count` | Successful `warmPageCache()` run |

## Images

| Event | Payload | When fired |
|---|---|---|
| `ImageOptimized` | `string $path`, `array $formats`, `array $sizes` | End of `ImageService::optimize()` |

## Database

| Event | Payload | When fired |
|---|---|---|
| `SlowQueryDetected` | `string $query`, `float $timeMs`, `array $trace`, `array $bindings` | Query crosses `slow_query_logging.threshold_ms` |
| `N1QueryDetected` | `string $queryNormalized`, `int $count`, `string $route` | Same normalized query fires more than `n1_detection.threshold` times |
| `IndexMigrationRequested` | `string $table`, `array $columns`, `string $recommendationId` | Operator applies an index recommendation from the dashboard |

## Monitoring

| Event | Payload | When fired |
|---|---|---|
| `PerformanceThresholdExceeded` | `string $metric`, `float $value`, `float $threshold` | An aggregated metric exceeds the configured budget |

## Bundled listeners

Wired by default in `PerformanceServiceProvider`:

| Listener | Effect |
|---|---|
| `OptimizeUploadedMedia` | Dispatches `OptimizeMediaJob` whenever a media-library `Media` row is created (or `MediaUploaded` fires if the future event class exists) |

## Disabling a built-in listener

Override `PerformanceServiceProvider::$listen` by subclassing and registering your subclass in `bootstrap/providers.php`, or unsubscribe at runtime:

```php
Event::forget(\ArtisanPackUI\Performance\Events\CachePurged::class);
```

(Forgetting clears **all** listeners — re-attach the ones you want to keep.)
