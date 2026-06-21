<?php

/**
 * Create performance_cache_entries table migration.
 *
 * Tracks page, fragment, and object cache entries managed by the package
 * along with hit/miss counters and expiration metadata. The dashboard
 * surfaces this data to support cache invalidation decisions.
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
		Schema::create( 'performance_cache_entries', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'key' );
			$table->string( 'type' );
			$table->string( 'route' )->nullable();
			$table->unsignedBigInteger( 'size_bytes' );
			$table->unsignedBigInteger( 'hits' )->default( 0 );
			$table->unsignedBigInteger( 'misses' )->default( 0 );
			$table->timestamp( 'expires_at' )->nullable();
			$table->timestamps();

			$table->index( 'key' );
			$table->index( 'type' );
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
		Schema::dropIfExists( 'performance_cache_entries' );
	}
};
