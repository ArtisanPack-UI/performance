<?php

/**
 * Slow query logging.
 *
 * SlowQueryLogger writes every query slower than the configured
 * threshold to the `performance_slow_queries` table with a normalized
 * SQL fingerprint so the dashboard can group them.
 */

return [

    'features' => [
        'query_optimization' => true,
    ],

    'query_optimization' => [

        'log_slow_queries' => true,

        // Anything slower than this (ms) gets logged. Start high and
        // tighten as you clean up offenders.
        'slow_threshold' => 100,

        // Sample rate keeps write volume in check on high-traffic apps.
        // 1.0 = log every slow query; 0.1 = log 10%.
        'sample_rate' => 1.0,

        // Optional allowlist. When set, only queries against these
        // connections are logged.
        'connections' => [ 'mysql', 'analytics' ],
    ],
];

/*
 * Read slow queries from the API:
 *
 *   GET /api/performance/admin/queries?range=24h
 *
 * Or from Tinker:
 *
 *   use ArtisanPackUI\Performance\Models\PerformanceSlowQuery;
 *
 *   PerformanceSlowQuery::query()
 *       ->where('duration_ms', '>=', 500)
 *       ->groupBy('normalized_sql')
 *       ->selectRaw('normalized_sql, count(*) as hits, avg(duration_ms) as avg_ms')
 *       ->orderByDesc('avg_ms')
 *       ->take(20)
 *       ->get();
 */
