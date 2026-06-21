<?php

declare( strict_types=1 );

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses( RefreshDatabase::class );

it( 'creates the performance_metrics table with the expected columns', function (): void {
	expect( Schema::hasTable( 'performance_metrics' ) )->toBeTrue()
		->and( Schema::hasColumns( 'performance_metrics', [
			'id', 'date', 'route', 'url', 'metric',
			'p50', 'p75', 'p90', 'p99',
			'sample_count', 'device_type', 'connection_type',
			'created_at', 'updated_at',
		] ) )->toBeTrue();
} );

it( 'creates the performance_slow_queries table with the expected columns', function (): void {
	expect( Schema::hasTable( 'performance_slow_queries' ) )->toBeTrue()
		->and( Schema::hasColumns( 'performance_slow_queries', [
			'id', 'query', 'query_normalized', 'bindings',
			'time_ms', 'connection', 'file', 'line', 'trace', 'route',
		] ) )->toBeTrue();
} );

it( 'creates the performance_cache_entries table with the expected columns', function (): void {
	expect( Schema::hasTable( 'performance_cache_entries' ) )->toBeTrue()
		->and( Schema::hasColumns( 'performance_cache_entries', [
			'id', 'key', 'type', 'route',
			'size_bytes', 'hits', 'misses', 'expires_at',
		] ) )->toBeTrue();
} );

it( 'creates the performance_optimized_images table with the expected columns', function (): void {
	expect( Schema::hasTable( 'performance_optimized_images' ) )->toBeTrue()
		->and( Schema::hasColumns( 'performance_optimized_images', [
			'id', 'original_path', 'optimized_path', 'format',
			'width', 'height', 'original_size', 'optimized_size',
			'compression_ratio', 'dominant_color', 'metadata',
		] ) )->toBeTrue();
} );
