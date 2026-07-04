<?php

/**
 * Add performance columns to the media library `media` table.
 *
 * Adds the optimization metadata the Performance package writes back to each
 * media row after processing an upload: dominant color for LQIP placeholders,
 * a lifecycle status (`pending` → `processing` → `completed`/`failed`), a
 * completion timestamp, and JSON maps for the produced formats and sizes.
 *
 * The migration is a no-op when the media-library package's `media` table
 * is absent, so applications that pull the Performance package without
 * media-library never fail their migrate step.
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
        if ( ! Schema::hasTable( 'media' ) ) {
            return;
        }

        Schema::table( 'media', function ( Blueprint $table ): void {
            if ( ! Schema::hasColumn( 'media', 'dominant_color' ) ) {
                $table->string( 'dominant_color', 7 )->nullable();
            }

            if ( ! Schema::hasColumn( 'media', 'optimization_status' ) ) {
                $table->string( 'optimization_status' )->default( 'pending' );
            }

            if ( ! Schema::hasColumn( 'media', 'optimized_at' ) ) {
                $table->timestamp( 'optimized_at' )->nullable();
            }

            if ( ! Schema::hasColumn( 'media', 'optimized_formats' ) ) {
                $table->json( 'optimized_formats' )->nullable();
            }

            if ( ! Schema::hasColumn( 'media', 'optimized_sizes' ) ) {
                $table->json( 'optimized_sizes' )->nullable();
            }
        } );
    }

    /**
     * Reverses the migration.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        if ( ! Schema::hasTable( 'media' ) ) {
            return;
        }

        Schema::table( 'media', function ( Blueprint $table ): void {
            $columns = [
                'dominant_color',
                'optimization_status',
                'optimized_at',
                'optimized_formats',
                'optimized_sizes',
            ];

            foreach ( $columns as $column ) {
                if ( Schema::hasColumn( 'media', $column ) ) {
                    $table->dropColumn( $column );
                }
            }
        } );
    }
};
