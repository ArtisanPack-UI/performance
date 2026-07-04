<?php

declare( strict_types=1 );

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Benchmarks\BenchmarkReport;
use Tests\Fixtures\CachesQueriesPostStub;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    BenchmarkReport::skipIfNotEnabled( $this );

    Schema::create( 'caches_queries_posts', function ( $table ): void {
        $table->increments( 'id' );
        $table->string( 'title' );
        $table->boolean( 'published' )->default( false );
    } );

    // Insert a non-trivial number of rows so the DB round-trip is
    // meaningful vs the cache HIT.
    $rows = [];
    for ( $i = 0; $i < 200; $i++ ) {
        $rows[] = [
            'title'     => 'row-' . $i,
            'published' => 0 === $i % 2,
        ];
    }
    DB::table( 'caches_queries_posts' )->insert( $rows );
} );

afterEach( function (): void {
    Schema::dropIfExists( 'caches_queries_posts' );
} );

it( 'compares uncached vs cached query wall-time', function (): void {
    // Baseline: no cache.
    $uncached = BenchmarkReport::measure(
        'Eloquent get() — no cache',
        100,
        static fn () => CachesQueriesPostStub::query()->get(),
    );

    // Warm the cache first, then measure HITs only.
    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    $cached = BenchmarkReport::measure(
        'Eloquent get() — cacheFor(60)',
        100,
        static fn () => CachesQueriesPostStub::query()->cacheFor( 60 )->get(),
    );

    // Both should complete but the cached mean should be much lower.
    expect( $uncached['mean_ms'] )->toBeGreaterThan( 0.0 );
    expect( $cached['mean_ms'] )->toBeGreaterThan( 0.0 );

    printf(
        "  BENCH  query cache speedup                                  %.2fx (uncached %.3fms → cached %.3fms)\n",
        $uncached['mean_ms'] / max( 0.001, $cached['mean_ms'] ),
        $uncached['mean_ms'],
        $cached['mean_ms'],
    );
} );
