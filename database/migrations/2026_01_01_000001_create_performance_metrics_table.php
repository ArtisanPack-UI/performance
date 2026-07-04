<?php

/**
 * Create performance_metrics table migration.
 *
 * Stores aggregated Core Web Vitals (LCP, FID, INP, CLS, TTFB) and other
 * performance metrics grouped by date, route, device type, and connection
 * type. Percentile fields (p50/p75/p90/p99) are precomputed by the
 * aggregation pipeline.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Runs the migration.
     *
     * @since 1.0.0
     */
    public function up(): void
    {
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('route')->nullable();
            $table->text('url')->nullable();
            $table->string('metric');
            $table->float('p50');
            $table->float('p75');
            $table->float('p90');
            $table->float('p99');
            $table->unsignedInteger('sample_count');
            $table->string('device_type')->nullable();
            $table->string('connection_type')->nullable();
            $table->timestamps();

            $table->index(['date', 'metric']);
            $table->index(['route', 'date']);
        });
    }

    /**
     * Reverses the migration.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};
