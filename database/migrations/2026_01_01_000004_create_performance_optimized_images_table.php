<?php

/**
 * Create performance_optimized_images table migration.
 *
 * Records every derivative produced by the image optimization pipeline:
 * format (webp/avif), dimensions, byte sizes before/after compression,
 * dominant color, and arbitrary metadata. The unique index prevents
 * duplicate derivatives for the same (path, format, width) tuple.
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
        Schema::create('performance_optimized_images', function (Blueprint $table) {
            $table->id();
            $table->string('original_path');
            $table->string('optimized_path');
            $table->string('format');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('original_size');
            $table->unsignedBigInteger('optimized_size');
            $table->float('compression_ratio');
            $table->string('dominant_color', 7)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('original_path');
            $table->unique(['original_path', 'format', 'width']);
        });
    }

    /**
     * Reverses the migration.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_optimized_images');
    }
};
