<?php

/**
 * Create performance_raw_metrics table migration.
 *
 * Stores raw Core Web Vitals samples as they arrive from the browser
 * before the aggregation pipeline rolls them up into the daily
 * percentiles persisted in `performance_metrics`. Persistence is gated
 * by `monitoring.store_raw_metrics`; when disabled the metric API
 * accepts samples but discards them without writing to this table.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

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
        Schema::create( 'performance_raw_metrics', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'name', 32 );
            $table->float( 'value' );
            $table->float( 'delta' )->nullable();
            $table->string( 'rating', 32 )->nullable();
            $table->string( 'vital_id', 64 )->nullable();
            $table->text( 'url' )->nullable();
            $table->string( 'route' )->nullable();
            $table->string( 'device_type', 32 )->nullable();
            $table->string( 'connection_type', 32 )->nullable();
            $table->text( 'user_agent' )->nullable();
            $table->json( 'extra' )->nullable();
            $table->timestamp( 'recorded_at' )->index();
            $table->timestamps();

            $table->index( [ 'name', 'recorded_at' ] );
            $table->index( [ 'route', 'recorded_at' ] );
        } );
    }

    /**
     * Reverses the migration.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists( 'performance_raw_metrics' );
    }
};
