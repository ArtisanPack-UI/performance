<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Livewire\MetricsChart;
use ArtisanPackUI\Performance\Models\PerformanceMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    Carbon::setTestNow( Carbon::create( 2026, 6, 30, 12, 0, 0 ) );
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

it( 'mounts with a single metric prop', function (): void {
    Livewire::test( MetricsChart::class, [ 'metric' => 'LCP' ] )
        ->assertSet( 'metrics', [ 'LCP' ] );
} );

it( 'mounts with a list of metrics', function (): void {
    Livewire::test( MetricsChart::class, [ 'metrics' => [ 'LCP', 'CLS' ] ] )
        ->assertSet( 'metrics', [ 'LCP', 'CLS' ] );
} );

it( 'falls back to the default range when an invalid one is supplied', function (): void {
    Livewire::test( MetricsChart::class, [ 'dateRange' => 'nonsense' ] )
        ->assertSet( 'dateRange', '7d' );
} );

it( 'rejects an unknown chart type', function (): void {
    Livewire::test( MetricsChart::class, [ 'chartType' => 'pie' ] )
        ->assertSet( 'chartType', 'line' );
} );

it( 'builds a chart payload aligned to one label per day', function (): void {
    PerformanceMetric::create( [
        'date'         => '2026-06-29',
        'metric'       => 'LCP',
        'p50'          => 1500,
        'p75'          => 2000,
        'p90'          => 2400,
        'p99'          => 2400,
        'sample_count' => 50,
    ] );

    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'metric'       => 'LCP',
        'p50'          => 1800,
        'p75'          => 2200,
        'p90'          => 2600,
        'p99'          => 2700,
        'sample_count' => 50,
    ] );

    $component = Livewire::test( MetricsChart::class, [ 'metric' => 'LCP', 'dateRange' => '7d' ] );

    $payload = $component->instance()->buildChartPayload();

    expect( $payload['labels'] )->toHaveCount( 7 )
        ->and( end( $payload['labels'] ) )->toBe( '2026-06-30' )
        ->and( $payload['datasets'][0]['metric'] )->toBe( 'LCP' )
        ->and( $payload['datasets'][0]['threshold'] )->toBe( 2500.0 );

    $values = $payload['datasets'][0]['values'];

    expect( $values[ array_search( '2026-06-30', $payload['labels'], true ) ] )->toBe( 2200.0 )
        ->and( $values[ array_search( '2026-06-29', $payload['labels'], true ) ] )->toBe( 2000.0 );
} );

it( 'omits the threshold when the option is disabled', function (): void {
    $component = Livewire::test( MetricsChart::class, [ 'metric' => 'LCP', 'showThreshold' => false ] );
    $payload   = $component->instance()->buildChartPayload();

    expect( $payload['datasets'][0]['threshold'] )->toBeNull();
} );

it( 'falls back to the default metric when the metrics list resolves to empty', function (): void {
    // Empty array + non-string entries would have produced
    // `WHERE metric IN ()` (MySQL syntax error). The component must
    // assert a sensible default so the query stays well-formed.
    Livewire::test( MetricsChart::class, [ 'metrics' => [] ] )
        ->assertSet( 'metrics', [ 'LCP' ] );

    Livewire::test( MetricsChart::class, [ 'metrics' => [ null, '', 123 ] ] )
        ->assertSet( 'metrics', [ 'LCP' ] );
} );

it( 'lets the user change the date range via setDateRange', function (): void {
    Livewire::test( MetricsChart::class )
        ->call( 'setDateRange', '30d' )
        ->assertSet( 'dateRange', '30d' );
} );

it( 'ignores unknown ranges passed to setDateRange', function (): void {
    Livewire::test( MetricsChart::class )
        ->call( 'setDateRange', 'nonsense' )
        ->assertSet( 'dateRange', '7d' );
} );
