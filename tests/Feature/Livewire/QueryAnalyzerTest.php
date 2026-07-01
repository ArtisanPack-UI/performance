<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Livewire\QueryAnalyzer;
use ArtisanPackUI\Performance\Models\SlowQuery;
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

it( 'mounts with the default range, sort, and no route filter', function (): void {
    Livewire::test( QueryAnalyzer::class )
        ->assertSet( 'dateRange', '7d' )
        ->assertSet( 'sort', 'time' )
        ->assertSet( 'routeFilter', '' )
        ->assertSet( 'minTimeMs', 0 );
} );

it( 'falls back to defaults when invalid overrides are supplied', function (): void {
    Livewire::test( QueryAnalyzer::class )
        ->set( 'dateRange', 'nonsense' )
        ->assertSet( 'dateRange', '7d' )
        ->call( 'setSort', 'bogus' )
        ->assertSet( 'sort', 'time' );
} );

it( 'renders an empty state when no slow queries exist', function (): void {
    Livewire::test( QueryAnalyzer::class )
        ->assertSee( 'No slow queries logged for this range.' );
} );

it( 'groups repeated signatures and ranks by peak time by default', function (): void {
    SlowQuery::create( [
        'query'            => 'select * from users where id = 1',
        'query_normalized' => 'select * from users where id = ?',
        'time_ms'          => 120.0,
        'connection'       => 'testbench',
        'route'            => 'home',
    ] );

    SlowQuery::create( [
        'query'            => 'select * from users where id = 2',
        'query_normalized' => 'select * from users where id = ?',
        'time_ms'          => 350.0,
        'connection'       => 'testbench',
        'route'            => 'home',
    ] );

    SlowQuery::create( [
        'query'            => 'select * from orders where user_id = 1',
        'query_normalized' => 'select * from orders where user_id = ?',
        'time_ms'          => 220.0,
        'connection'       => 'testbench',
        'route'            => 'orders',
    ] );

    Livewire::test( QueryAnalyzer::class )
        ->assertSee( 'from users' )
        ->assertSee( 'from orders' )
        ->assertSee( '350.00' );
} );

it( 'switches sort order to frequency', function (): void {
    SlowQuery::create( [
        'query'            => 'select * from posts where slug = "a"',
        'query_normalized' => 'select * from posts where slug = ?',
        'time_ms'          => 105.0,
        'connection'       => 'testbench',
    ] );

    Livewire::test( QueryAnalyzer::class )
        ->call( 'setSort', 'frequency' )
        ->assertSet( 'sort', 'frequency' );
} );

it( 'filters by route', function (): void {
    SlowQuery::create( [
        'query'            => 'select * from posts',
        'query_normalized' => 'select * from posts',
        'time_ms'          => 200.0,
        'connection'       => 'testbench',
        'route'            => 'blog',
    ] );

    SlowQuery::create( [
        'query'            => 'select * from carts',
        'query_normalized' => 'select * from carts',
        'time_ms'          => 200.0,
        'connection'       => 'testbench',
        'route'            => 'checkout',
    ] );

    Livewire::test( QueryAnalyzer::class )
        ->set( 'routeFilter', 'blog' )
        ->assertSee( 'from posts' )
        ->assertDontSee( 'from carts' );
} );

it( 'filters by minimum time', function (): void {
    SlowQuery::create( [
        'query'            => 'select * from a',
        'query_normalized' => 'select * from a',
        'time_ms'          => 150.0,
        'connection'       => 'testbench',
    ] );

    SlowQuery::create( [
        'query'            => 'select * from b',
        'query_normalized' => 'select * from b',
        'time_ms'          => 500.0,
        'connection'       => 'testbench',
    ] );

    Livewire::test( QueryAnalyzer::class )
        ->set( 'minTimeMs', 300 )
        ->assertSee( 'from b' )
        ->assertDontSee( 'from a' );
} );

it( 'toggles expanded state per hash', function (): void {
    SlowQuery::create( [
        'query'            => 'select * from posts',
        'query_normalized' => 'select * from posts',
        'time_ms'          => 200.0,
        'connection'       => 'testbench',
    ] );

    $hash = md5( 'select * from posts' );

    Livewire::test( QueryAnalyzer::class )
        ->call( 'toggleExpanded', $hash )
        ->assertSet( 'expandedSignature', $hash )
        ->call( 'toggleExpanded', $hash )
        ->assertSet( 'expandedSignature', null );
} );

it( 'exports a CSV download of the current result set', function (): void {
    SlowQuery::create( [
        'query'            => 'select * from posts',
        'query_normalized' => 'select * from posts',
        'time_ms'          => 200.0,
        'connection'       => 'testbench',
        'route'            => 'home',
    ] );

    $component = new QueryAnalyzer;
    $component->mount();

    $response = $component->exportCsv();

    // Capture the streamed body and confirm the header row + our fixture appear.
    ob_start();
    $response->sendContent();
    $body = (string) ob_get_clean();

    expect( $body )->toContain( 'query,peak_time_ms' )
        ->and( $body )->toContain( 'select * from posts' )
        ->and( $body )->toContain( 'home' );
} );

it( 'merges host-supplied labels over defaults', function (): void {
    Livewire::test( QueryAnalyzer::class, [
        'labels' => [
            'title'  => 'Slow Query Console',
            'export' => 'Download Report',
        ],
    ] )
        ->assertSee( 'Slow Query Console' )
        ->assertSee( 'Download Report' );
} );

it( 'clamps a negative default minTimeMs on mount', function (): void {
    // Values coming through URL restoration can be arbitrary; mount()
    // clamps them so a hostile ?min=-100 can't slip a negative into
    // the where clause.
    $component            = new QueryAnalyzer;
    $component->minTimeMs = -100;
    $component->mount();

    expect( $component->minTimeMs )->toBe( 0 );
} );
