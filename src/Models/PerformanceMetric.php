<?php

/**
 * Aggregated performance metric Eloquent model.
 *
 * Wraps the `performance_metrics` table that holds per-day, per-route,
 * per-device, per-connection percentile aggregates produced by the
 * `MetricsAggregator`. Each row represents a single metric (LCP, FID,
 * CLS, INP, TTFB) summarized across the matching raw samples.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Performance metric model class.
 *
 *
 * @since      1.0.0
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $route
 * @property string|null $url
 * @property string $metric
 * @property float $p50
 * @property float $p75
 * @property float $p90
 * @property float $p99
 * @property int $sample_count
 * @property string|null $device_type
 * @property string|null $connection_type
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PerformanceMetric extends Model
{
    /**
     * The database table backing the model.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $table = 'performance_metrics';

    /**
     * The attributes that are mass-assignable.
     *
     * The aggregator and the API both need to write every column; gating
     * the columns individually would force callers into `forceFill()` and
     * obscure the intent of straightforward upserts.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'date',
        'route',
        'url',
        'metric',
        'p50',
        'p75',
        'p90',
        'p99',
        'sample_count',
        'device_type',
        'connection_type',
    ];

    /**
     * Returns the attribute casts.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'p50'          => 'float',
            'p75'          => 'float',
            'p90'          => 'float',
            'p99'          => 'float',
            'sample_count' => 'integer',
        ];
    }
}
