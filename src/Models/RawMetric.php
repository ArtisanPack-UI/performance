<?php

/**
 * Raw performance metric Eloquent model.
 *
 * Wraps the `performance_raw_metrics` table that captures Core Web
 * Vitals samples as they arrive from the browser. Each row is a single
 * measurement (`name`, `value`, `delta`) tagged with enough context for
 * the aggregator to bucket samples by route, device, and connection.
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
 * Raw metric model class.
 *
 *
 * @since      1.0.0
 *
 * @property int $id
 * @property string $name
 * @property float $value
 * @property float|null $delta
 * @property string|null $rating
 * @property string|null $vital_id
 * @property string|null $url
 * @property string|null $route
 * @property string|null $device_type
 * @property string|null $connection_type
 * @property string|null $user_agent
 * @property array<string, mixed>|null $extra
 * @property \Illuminate\Support\Carbon $recorded_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class RawMetric extends Model
{
    /**
     * The database table backing the model.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $table = 'performance_raw_metrics';

    /**
     * The attributes that are mass-assignable.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'value',
        'delta',
        'rating',
        'vital_id',
        'url',
        'route',
        'device_type',
        'connection_type',
        'user_agent',
        'extra',
        'recorded_at',
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
            'value'       => 'float',
            'delta'       => 'float',
            'extra'       => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
