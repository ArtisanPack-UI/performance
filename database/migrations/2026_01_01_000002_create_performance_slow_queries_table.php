<?php

/**
 * Create performance_slow_queries table migration.
 *
 * Stores queries whose execution time exceeded the configured slow-query
 * threshold. The normalized form is used for grouping equivalent queries
 * across runs while preserving the original SQL for inspection.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
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
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create( 'performance_slow_queries', function ( Blueprint $table ) {
			$table->id();
			$table->text( 'query' );
			$table->text( 'query_normalized' );
			$table->json( 'bindings' )->nullable();
			$table->float( 'time_ms' );
			$table->string( 'connection' );
			$table->string( 'file' )->nullable();
			$table->unsignedInteger( 'line' )->nullable();
			$table->json( 'trace' )->nullable();
			$table->string( 'route' )->nullable();
			$table->timestamps();

			$table->index( 'created_at' );
			$table->index( 'time_ms' );
		} );
	}

	/**
	 * Reverses the migration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'performance_slow_queries' );
	}
};
