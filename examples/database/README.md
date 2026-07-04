# Database

Snippets for N+1 detection, slow query logging, and index suggestions.

## Examples

- [`n1-detection.php`](n1-detection.php) — turn on N+1 detection and subscribe to the event
- [`slow-query-logging.php`](slow-query-logging.php) — log slow queries and read them from the dashboard / API
- [`index-suggestions.md`](index-suggestions.md) — run `perf:suggest-indexes` to convert observed slow queries into `CREATE INDEX` statements
- [`query-optimization.php`](query-optimization.php) — opt into `CachingEloquentBuilder` for a specific model
