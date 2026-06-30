<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Livewire\PerformanceDashboard;
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

it( 'mounts with the default range and tab', function (): void {
    Livewire::test( PerformanceDashboard::class )
        ->assertSet( 'dateRange', '7d' )
        ->assertSet( 'activeTab', 'overview' );
} );

it( 'falls back to the default range when an invalid override is supplied', function (): void {
    Livewire::test( PerformanceDashboard::class, [ 'defaultDateRange' => 'nonsense' ] )
        ->assertSet( 'dateRange', '7d' );
} );

it( 'accepts a supported range override on mount', function (): void {
    Livewire::test( PerformanceDashboard::class, [ 'defaultDateRange' => '30d' ] )
        ->assertSet( 'dateRange', '30d' );
} );

it( 'switches tabs via setTab', function (): void {
    Livewire::test( PerformanceDashboard::class )
        ->call( 'setTab', 'cache' )
        ->assertSet( 'activeTab', 'cache' );
} );

it( 'ignores unknown tabs', function (): void {
    Livewire::test( PerformanceDashboard::class )
        ->call( 'setTab', 'bogus' )
        ->assertSet( 'activeTab', 'overview' );
} );

it( 'switches the date range via setDateRange', function (): void {
    Livewire::test( PerformanceDashboard::class )
        ->call( 'setDateRange', '30d' )
        ->assertSet( 'dateRange', '30d' );
} );

it( 'classifies LCP statuses against web vitals thresholds', function (): void {
    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'route'        => 'home',
        'metric'       => 'LCP',
        'p50'          => 1500,
        'p75'          => 2000,
        'p90'          => 2400,
        'p99'          => 2400,
        'sample_count' => 100,
    ] );

    Livewire::test( PerformanceDashboard::class )
        ->assertSee( 'LCP' )
        ->assertSee( 'good' );
} );

it( 'renders a recommendation when a metric is in the poor band', function (): void {
    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'route'        => 'home',
        'metric'       => 'LCP',
        'p50'          => 4000,
        'p75'          => 6000,
        'p90'          => 8000,
        'p99'          => 9000,
        'sample_count' => 100,
    ] );

    Livewire::test( PerformanceDashboard::class )
        ->call( 'setTab', 'recommendations' )
        ->assertSee( 'LCP is poor' );
} );

it( 'dispatches a refresh event when refreshMetrics is called', function (): void {
    Livewire::test( PerformanceDashboard::class )
        ->call( 'refreshMetrics' )
        ->assertDispatched( 'performance-dashboard:refreshed' );
} );

it( 'classifies metrics with metric-specific poor thresholds (not a flat 2x)', function (): void {
    // INP good=200, poor=500 (per web.dev). A p75 of 450 is needs-improvement.
    // The old flat-2x rule would have marked it poor.
    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'route'        => 'home',
        'metric'       => 'INP',
        'p50'          => 300,
        'p75'          => 450,
        'p90'          => 480,
        'p99'          => 490,
        'sample_count' => 100,
    ] );

    Livewire::test( PerformanceDashboard::class )
        ->assertSee( 'INP' )
        ->assertSee( 'Needs improvement' );
} );

it( 'respects a URL-restored range over the host defaultDateRange prop', function (): void {
    // Simulate a reload where the browser sent `?range=24h`. The
    // host page passes :default-date-range="'30d'", but the URL
    // value should win so the user's bookmark survives. We assert
    // the inverse of the buggy behavior — if mount() unconditionally
    // overwrites the property, the URL value cannot stick when the
    // host supplies a default.
    $request = new Illuminate\Http\Request( [ 'range' => '24h' ] );

    $component            = new PerformanceDashboard;
    $component->dateRange = '24h';
    $component->mount( $request, '30d' );

    expect( $component->dateRange )->toBe( '24h' );
} );

it( 'applies the defaultDateRange when no URL parameter is present', function (): void {
    $request = new Illuminate\Http\Request;

    $component = new PerformanceDashboard;
    $component->mount( $request, '30d' );

    expect( $component->dateRange )->toBe( '30d' );
} );

it( 'only builds the active tab payload to avoid eager-rendering every tab', function (): void {
    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'route'        => 'home',
        'metric'       => 'LCP',
        'p50'          => 1500,
        'p75'          => 2000,
        'p90'          => 2400,
        'p99'          => 2400,
        'sample_count' => 100,
    ] );

    // Pages tab data should not render content from the Overview build
    Livewire::test( PerformanceDashboard::class )
        ->call( 'setTab', 'pages' )
        ->assertSee( 'Pages' )
        ->assertDontSee( 'Core Web Vitals' );
} );
