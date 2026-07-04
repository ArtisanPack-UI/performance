<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Models\RawMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    Carbon::setTestNow( Carbon::create( 2026, 6, 30, 12, 0, 0 ) );
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

function seedCommandRawMetrics( int $count, Carbon $recordedAt ): void
{
    for ( $i = 0; $i < $count; $i++ ) {
        RawMetric::create( [
            'name'        => 'LCP',
            'value'       => 100 + ( $i * 10 ),
            'route'       => 'products.index',
            'recorded_at' => $recordedAt,
        ] );
    }
}

it( 'aggregates the current day by default', function (): void {
    seedCommandRawMetrics( 20, Carbon::create( 2026, 6, 30, 8, 0, 0 ) );

    $this->artisan( 'perf:aggregate-metrics' )
        ->assertSuccessful();

    expect( PerformanceMetric::query()->count() )->toBeGreaterThan( 0 );
} );

it( 'aggregates an explicit date passed via --date', function (): void {
    $day = Carbon::create( 2026, 6, 25 );
    seedCommandRawMetrics( 10, $day->copy()->setTime( 14, 0 ) );

    $this->artisan( 'perf:aggregate-metrics', [ '--date' => $day->toDateString() ] )
        ->assertSuccessful();

    expect( PerformanceMetric::query()
        ->whereDate( 'date', $day->toDateString() )
        ->count() )->toBeGreaterThan( 0 );
} );

it( 'backfills the requested number of days', function (): void {
    $today     = Carbon::today();
    $yesterday = $today->copy()->subDay();

    seedCommandRawMetrics( 5, $today->copy()->setTime( 9, 0 ) );
    seedCommandRawMetrics( 5, $yesterday->copy()->setTime( 9, 0 ) );

    $this->artisan( 'perf:aggregate-metrics', [ '--backfill' => 2 ] )
        ->assertSuccessful();

    $distinctDates = PerformanceMetric::query()
        ->distinct()
        ->pluck( 'date' )
        ->count();

    expect( $distinctDates )->toBeGreaterThanOrEqual( 2 );
} );

it( 'reports success even when no raw samples exist for the date', function (): void {
    $this->artisan( 'perf:aggregate-metrics' )
        ->assertSuccessful();

    expect( PerformanceMetric::query()->count() )->toBe( 0 );
} );
