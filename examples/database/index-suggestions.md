# Index suggestions

`perf:suggest-indexes` scans the `performance_slow_queries` table,
groups by normalized SQL fingerprint, runs `EXPLAIN` on the slowest
member of each group, and outputs `CREATE INDEX` statements for
columns the planner filtered / sorted on without an index.

## Run it

```bash
# Print index suggestions to STDOUT.
php artisan perf:suggest-indexes

# Only look at queries logged in the last 24 hours.
php artisan perf:suggest-indexes --range=24h

# Only queries slower than 500ms.
php artisan perf:suggest-indexes --threshold=500

# Write the suggested statements to a migration file.
php artisan perf:suggest-indexes --output=database/migrations/2026_07_04_000000_add_perf_indexes.php
```

## Example output

```sql
-- Slow: orders.user_id filtered, no index. avg 812ms, 43 hits, 24h.
CREATE INDEX orders_user_id_index ON orders (user_id);

-- Slow: order_items.order_id + created_at composite filter. avg 611ms, 38 hits, 24h.
CREATE INDEX order_items_order_id_created_at_index ON order_items (order_id, created_at);
```

## Review before applying

The command is a suggestion engine, not a schema migrator — it never
runs `CREATE INDEX` for you. Review each suggestion, drop redundant
indexes, and roll them out as normal migrations. Long-running index
creation on a large table needs the usual care (`ALGORITHM=INPLACE`,
`LOCK=NONE`, off-peak deploy).
