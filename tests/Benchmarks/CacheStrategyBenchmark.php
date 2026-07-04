<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Cache;
use Tests\Benchmarks\BenchmarkReport;

beforeEach( function (): void {
    config( [ 'cache.default' => 'array' ] );
    Cache::store( 'array' )->flush();
} );

it( 'measures raw Laravel cache put/get on the array driver', function (): void {
    $store = Cache::store( 'array' );

    $put = BenchmarkReport::measure(
        'Cache::put() — array store',
        500,
        static fn () => $store->put( 'bench-key-' . random_int( 0, PHP_INT_MAX ), str_repeat( 'x', 4096 ), 60 ),
    );

    $store->put( 'read-key', str_repeat( 'y', 4096 ), 60 );

    $get = BenchmarkReport::measure(
        'Cache::get() — array store',
        500,
        static fn () => $store->get( 'read-key' ),
    );

    expect( $put['mean_ms'] )->toBeGreaterThanOrEqual( 0.0 );
    expect( $get['mean_ms'] )->toBeGreaterThanOrEqual( 0.0 );
} );
