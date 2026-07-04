<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Models\RawMetric;
use ArtisanPackUI\Performance\Monitoring\MetricsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    Carbon::setTestNow( Carbon::create( 2026, 6, 30, 12, 0, 0 ) );
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

function seedRawMetrics( array $samples, ?Carbon $recordedAt = null ): void
{
    $recordedAt ??= Carbon::create( 2026, 6, 30, 10, 0, 0 );

    foreach ( $samples as $sample ) {
        RawMetric::create( array_merge( [
            'name'        => 'LCP',
            'value'       => 0,
            'route'       => 'products.index',
            'recorded_at' => $recordedAt,
        ], $sample ) );
    }
}

it( 'computes nearest-rank percentiles for an unsorted sample list', function (): void {
    $aggregator = new MetricsAggregator;

    // Sorted: [25, 50, 75, 90, 100, 150, 200, 300, 400, 600] (n=10).
    // Nearest-rank: ceil(p * n / 100) - 1.
    //   p50 -> rank 4 = 100, p75 -> rank 7 = 300, p90 -> rank 8 = 400, p99 -> rank 9 = 600.
    $result = $aggregator->computePercentiles( [ 100, 50, 200, 75, 300, 25, 400, 150, 90, 600 ] );

    expect( $result )->toHaveKeys( [ 50, 75, 90, 99 ] )
        ->and( $result[50] )->toBe( 100.0 )
        ->and( $result[75] )->toBe( 300.0 )
        ->and( $result[90] )->toBe( 400.0 )
        ->and( $result[99] )->toBe( 600.0 );
} );

it( 'returns zero percentiles for an empty sample list', function (): void {
    $aggregator = new MetricsAggregator;

    expect( $aggregator->computePercentiles( [] ) )->toBe( [
        50 => 0.0,
        75 => 0.0,
        90 => 0.0,
        99 => 0.0,
    ] );
} );

it( 'aggregates raw samples into a single bucket row', function (): void {
    seedRawMetrics( [
        [ 'value' => 1000 ],
        [ 'value' => 1500 ],
        [ 'value' => 2000 ],
        [ 'value' => 2500 ],
        [ 'value' => 3000 ],
    ] );

    $written = ( new MetricsAggregator )->aggregate( '2026-06-30' );

    expect( $written )->toBe( 1 );

    $row = PerformanceMetric::query()->first();

    // Sorted: [1000, 1500, 2000, 2500, 3000] (n=5).
    // Nearest-rank: p50 -> rank 2 = 2000, p75 -> rank 3 = 2500, p90 -> rank 4 = 3000, p99 -> rank 4 = 3000.
    expect( $row->metric )->toBe( 'LCP' )
        ->and( $row->route )->toBe( 'products.index' )
        ->and( $row->sample_count )->toBe( 5 )
        ->and( $row->p50 )->toBe( 2000.0 )
        ->and( $row->p75 )->toBe( 2500.0 )
        ->and( $row->p90 )->toBe( 3000.0 )
        ->and( $row->p99 )->toBe( 3000.0 );
} );

it( 'buckets by route, metric, device, and connection', function (): void {
    seedRawMetrics( [
        [ 'name' => 'LCP', 'value' => 1000, 'device_type' => 'mobile', 'connection_type' => '4g' ],
        [ 'name' => 'LCP', 'value' => 2000, 'device_type' => 'mobile', 'connection_type' => '4g' ],
        [ 'name' => 'LCP', 'value' => 1500, 'device_type' => 'desktop', 'connection_type' => '4g' ],
        [ 'name' => 'CLS', 'value' => 0.1,  'device_type' => 'mobile', 'connection_type' => '4g' ],
    ] );

    $written = ( new MetricsAggregator )->aggregate( '2026-06-30' );

    expect( $written )->toBe( 3 );
    expect( PerformanceMetric::query()->count() )->toBe( 3 );

    $mobileLcp = PerformanceMetric::query()
        ->where( 'metric', 'LCP' )
        ->where( 'device_type', 'mobile' )
        ->first();

    expect( $mobileLcp->sample_count )->toBe( 2 );
} );

it( 'is idempotent — re-running produces the same row counts', function (): void {
    seedRawMetrics( [
        [ 'value' => 1000 ],
        [ 'value' => 2000 ],
        [ 'value' => 3000 ],
    ] );

    $aggregator = new MetricsAggregator;

    $aggregator->aggregate( '2026-06-30' );
    $aggregator->aggregate( '2026-06-30' );

    expect( PerformanceMetric::query()->count() )->toBe( 1 );

    $row = PerformanceMetric::query()->first();

    expect( $row->sample_count )->toBe( 3 );
} );

it( 'returns zero when there are no raw samples for the date', function (): void {
    expect( ( new MetricsAggregator )->aggregate( '2026-06-29' ) )->toBe( 0 );
    expect( PerformanceMetric::query()->count() )->toBe( 0 );
} );

it( 'aggregates only samples within the requested calendar date', function (): void {
    seedRawMetrics( [
        [ 'value' => 1000 ],
        [ 'value' => 2000 ],
    ], Carbon::create( 2026, 6, 29, 10, 0, 0 ) );

    seedRawMetrics( [
        [ 'value' => 5000 ],
    ], Carbon::create( 2026, 6, 30, 10, 0, 0 ) );

    ( new MetricsAggregator )->aggregate( '2026-06-30' );

    expect( PerformanceMetric::query()->count() )->toBe( 1 );

    $row = PerformanceMetric::query()->first();

    expect( $row->sample_count )->toBe( 1 )
        ->and( $row->p75 )->toBe( 5000.0 );
} );
