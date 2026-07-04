<?php

/**
 * Slow query Eloquent model.
 *
 * Wraps the `performance_slow_queries` table created by the
 * `2026_01_01_000002_create_performance_slow_queries_table.php`
 * migration. The model exposes typed casts for the JSON columns
 * (`bindings`, `trace`) so callers can hydrate and inspect captured
 * queries without re-parsing the JSON each time.
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
 * Slow query model class.
 *
 *
 * @since      1.0.0
 *
 * @property int $id
 * @property string $query
 * @property string $query_normalized
 * @property array<int, mixed>|null $bindings
 * @property float $time_ms
 * @property string $connection
 * @property string|null $file
 * @property int|null $line
 * @property array<int|string, mixed>|null $trace
 * @property string|null $route
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class SlowQuery extends Model
{
    /**
     * The database table backing the model.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $table = 'performance_slow_queries';

    /**
     * The attributes that are mass-assignable.
     *
     * The model is package-internal — every column needs to be writable
     * by the SlowQueryLogger and tests. Guarding individual columns
     * would force the logger to use `forceFill()`, which obscures the
     * intent of the writes.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'query',
        'query_normalized',
        'bindings',
        'time_ms',
        'connection',
        'file',
        'line',
        'trace',
        'route',
    ];

    /**
     * Returns the attribute casts.
     *
     * The `bindings` and `trace` columns are JSON in the schema; casting
     * them here means callers can write/read them as native PHP arrays
     * without any encoding ceremony.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bindings' => 'array',
            'trace'    => 'array',
            'time_ms'  => 'float',
            'line'     => 'integer',
        ];
    }
}
