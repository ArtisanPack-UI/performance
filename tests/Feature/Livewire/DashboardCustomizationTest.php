<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Livewire\PerformanceDashboard;
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

it( 'applies the class prop to the outer container', function (): void {
    Livewire::test( PerformanceDashboard::class, [
        'class' => 'app-dashboard tenant-acme',
    ] )
        ->assertSeeHtml( 'app-dashboard tenant-acme' );
} );

it( 'merges host-supplied labels over the defaults', function (): void {
    Livewire::test( PerformanceDashboard::class, [
        'labels' => [
            'refresh' => 'Reload metrics',
        ],
    ] )
        ->assertSee( 'Reload metrics' );
} );

it( 'hides a tab when the config toggles it off', function (): void {
    config( [ 'artisanpack.performance.ui.tabs.queries' => false ] );

    Livewire::test( PerformanceDashboard::class )
        ->assertDontSee( 'Queries' );
} );

it( 'coerces the active tab to a visible tab when the selection is hidden', function (): void {
    config( [ 'artisanpack.performance.ui.tabs.pages' => false ] );

    Livewire::test( PerformanceDashboard::class )
        ->set( 'activeTab', 'pages' )
        ->assertSet( 'activeTab', 'overview' );
} );

it( 'still shows overview when every tab is disabled', function (): void {
    config( [
        'artisanpack.performance.ui.tabs.overview'        => false,
        'artisanpack.performance.ui.tabs.pages'           => false,
        'artisanpack.performance.ui.tabs.images'          => false,
        'artisanpack.performance.ui.tabs.cache'           => false,
        'artisanpack.performance.ui.tabs.queries'         => false,
        'artisanpack.performance.ui.tabs.recommendations' => false,
    ] );

    // Every tab disabled shouldn't crash the dashboard; we fall back
    // to a single overview tab so the surface remains reachable.
    Livewire::test( PerformanceDashboard::class )
        ->assertSet( 'activeTab', 'overview' );
} );
