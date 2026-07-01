<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Models\SlowQuery;
use ArtisanPackUI\Performance\Monitoring\RecommendationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    Carbon::setTestNow( Carbon::create( 2026, 6, 30, 12, 0, 0 ) );

    config( [
        'artisanpack.performance.page_cache.enabled'                       => true,
        'artisanpack.performance.fragment_cache.enabled'                   => true,
        'artisanpack.performance.database.slow_query_logging.threshold_ms' => 100,
    ] );
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

it( 'returns an empty list when nothing needs attention', function (): void {
    $items = ( new RecommendationEngine )->build();

    expect( $items )->toBe( [] );
} );

it( 'orders items by priority so highs bubble to the top', function (): void {
    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'route'        => 'home',
        'metric'       => 'LCP',
        'p50'          => 6000,
        'p75'          => 6000,
        'p90'          => 6000,
        'p99'          => 6000,
        'sample_count' => 100,
    ] );

    // Also disable fragment cache so we get a low-priority item too.
    config( [ 'artisanpack.performance.fragment_cache.enabled' => false ] );

    $items = ( new RecommendationEngine )->build();

    expect( $items[0]['priority'] )->toBe( 'high' );

    $priorities = array_column( $items, 'priority' );

    // The list is sorted descending by weight; verify no low precedes a high.
    $weights  = [ 'high' => 3, 'medium' => 2, 'low' => 1 ];
    $previous = PHP_INT_MAX;

    foreach ( $priorities as $priority ) {
        $current = $weights[ $priority ] ?? 0;
        expect( $current )->toBeLessThanOrEqual( $previous );
        $previous = $current;
    }
} );

it( 'ignores metric cohorts with fewer than 10 samples', function (): void {
    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'route'        => 'home',
        'metric'       => 'LCP',
        'p50'          => 6000,
        'p75'          => 6000,
        'p90'          => 6000,
        'p99'          => 6000,
        'sample_count' => 5,
    ] );

    $items = ( new RecommendationEngine )->build();

    $metric = array_values( array_filter( $items, static fn ( array $item ): bool => 'web_vital' === $item['type'] ) );

    expect( $metric )->toBe( [] );
} );

it( 'promotes slow queries above 5x threshold to high priority', function (): void {
    SlowQuery::create( [
        'query'            => 'select * from posts',
        'query_normalized' => 'select * from posts',
        'time_ms'          => 800.0,
        'connection'       => 'testbench',
    ] );

    $items = ( new RecommendationEngine )->build();

    $slow = array_values( array_filter( $items, static fn ( array $item ): bool => 'slow_query' === $item['type'] ) );

    expect( $slow )->not->toBeEmpty()
        ->and( $slow[0]['priority'] )->toBe( 'high' );
} );

it( 'emits a cache-warm recommendation when the store is empty and enabled', function (): void {
    // The engine only emits the "cache is empty" recommendation when
    // there's evidence traffic is happening — a metric row satisfies
    // that guard.
    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'route'        => 'home',
        'metric'       => 'LCP',
        'p50'          => 1500,
        'p75'          => 2000,
        'p90'          => 2200,
        'p99'          => 2400,
        'sample_count' => 100,
    ] );

    $items = ( new RecommendationEngine )->build();

    $cache = array_values( array_filter( $items, static fn ( array $item ): bool => 'cache_opportunity' === $item['type'] ) );

    expect( $cache )->not->toBeEmpty()
        ->and( array_column( $cache, 'id' ) )->toContain( 'cache:page_empty' );
} );
