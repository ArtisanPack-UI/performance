<?php

declare( strict_types=1 );

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it( 'no-ops when the media table does not exist', function (): void {
    // The package migrations already ran via the base test case; we only
    // need to prove that the additive `media` migration didn't create a
    // stub table when the media-library table is absent.
    expect( Schema::hasTable( 'media' ) )->toBeFalse();
} );

it( 'adds the optimization columns when the media table exists', function (): void {
    // Recreate a minimal `media` table to simulate what the media-library
    // migration would provide, then run only the additive migration by
    // requiring the migration file and calling `up()` directly.
    Schema::create( 'media', function ( Blueprint $table ): void {
        $table->id();
        $table->string( 'file_path' )->nullable();
        $table->timestamps();
    } );

    try {
        $migration = require __DIR__ . '/../../../database/migrations/2026_01_01_000006_add_performance_columns_to_media_table.php';
        $migration->up();

        expect( Schema::hasColumns( 'media', [
            'dominant_color',
            'optimization_status',
            'optimized_at',
            'optimized_formats',
            'optimized_sizes',
        ] ) )->toBeTrue();

        // Idempotent — running `up()` a second time must not error even
        // though the columns already exist.
        $migration->up();

        expect( Schema::hasColumn( 'media', 'optimization_status' ) )->toBeTrue();
    } finally {
        Schema::dropIfExists( 'media' );
    }
} );

it( 'drops the added columns on down when the table exists', function (): void {
    Schema::create( 'media', function ( Blueprint $table ): void {
        $table->id();
        $table->string( 'file_path' )->nullable();
        $table->timestamps();
    } );

    try {
        $migration = require __DIR__ . '/../../../database/migrations/2026_01_01_000006_add_performance_columns_to_media_table.php';
        $migration->up();
        $migration->down();

        expect( Schema::hasColumn( 'media', 'dominant_color' ) )->toBeFalse()
            ->and( Schema::hasColumn( 'media', 'optimization_status' ) )->toBeFalse()
            ->and( Schema::hasColumn( 'media', 'optimized_at' ) )->toBeFalse()
            ->and( Schema::hasColumn( 'media', 'optimized_formats' ) )->toBeFalse()
            ->and( Schema::hasColumn( 'media', 'optimized_sizes' ) )->toBeFalse();
    } finally {
        Schema::dropIfExists( 'media' );
    }
} );
