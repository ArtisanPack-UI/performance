<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Livewire\RecommendationsPanel;
use ArtisanPackUI\Performance\Models\PerformanceMetric;
use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    Carbon::setTestNow( Carbon::create( 2026, 6, 30, 12, 0, 0 ) );

    config( [
        'artisanpack.performance.page_cache.enabled'     => true,
        'artisanpack.performance.fragment_cache.enabled' => true,
    ] );
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

it( 'renders the empty state when nothing needs attention', function (): void {
    Livewire::test( RecommendationsPanel::class )
        ->assertSee( 'All tracked signals are in the good band.' );
} );

it( 'surfaces a Core Web Vitals recommendation for a poor metric', function (): void {
    PerformanceMetric::create( [
        'date'         => '2026-06-30',
        'route'        => 'home',
        'metric'       => 'LCP',
        'p50'          => 3800,
        'p75'          => 6000,
        'p90'          => 8000,
        'p99'          => 9000,
        'sample_count' => 100,
    ] );

    Livewire::test( RecommendationsPanel::class )
        ->assertSee( 'LCP is poor' )
        ->assertSee( 'High priority' );
} );

it( 'skips metric recommendations for cohorts under 10 samples', function (): void {
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

    Livewire::test( RecommendationsPanel::class )
        ->assertDontSee( 'LCP is poor' );
} );

it( 'surfaces a slow-query recommendation when the corpus has one', function (): void {
    config( [ 'artisanpack.performance.database.slow_query_logging.threshold_ms' => 100 ] );

    SlowQuery::create( [
        'query'            => 'select * from posts where slug = "a"',
        'query_normalized' => 'select * from posts where slug = ?',
        'time_ms'          => 620.0,
        'connection'       => 'testbench',
    ] );

    Livewire::test( RecommendationsPanel::class )
        ->assertSee( 'Slow query repeated' );
} );

it( 'suggests enabling page cache when the flag is off', function (): void {
    config( [ 'artisanpack.performance.page_cache.enabled' => false ] );

    Livewire::test( RecommendationsPanel::class )
        ->assertSee( 'Enable page cache' );
} );

it( 'dismisses a recommendation and hides it from the list', function (): void {
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

    Livewire::test( RecommendationsPanel::class )
        ->assertSee( 'LCP is poor' )
        ->call( 'dismiss', 'metric:LCP' )
        ->assertDontSee( 'LCP is poor' );

    expect( Session::get( RecommendationsPanel::DISMISSAL_KEY ) )->toContain( 'metric:LCP' );
} );

it( 'restores dismissed recommendations when reset', function (): void {
    Session::put( RecommendationsPanel::DISMISSAL_KEY, [ 'metric:LCP' ] );

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

    Livewire::test( RecommendationsPanel::class )
        ->assertDontSee( 'LCP is poor' )
        ->call( 'resetDismissals' )
        ->assertSee( 'LCP is poor' );

    expect( Session::get( RecommendationsPanel::DISMISSAL_KEY ) )->toBeNull();
} );

it( 'reports an error when applying a nonexistent recommendation', function (): void {
    Livewire::test( RecommendationsPanel::class )
        ->call( 'applyAction', 'metric:LCP' )
        ->assertSet( 'statusIsError', true );
} );

it( 'dispatches a navigation event for the view-query-analyzer action', function (): void {
    config( [ 'artisanpack.performance.database.slow_query_logging.threshold_ms' => 100 ] );

    SlowQuery::create( [
        'query'            => 'select * from posts',
        'query_normalized' => 'select * from posts',
        'time_ms'          => 200.0,
        'connection'       => 'testbench',
    ] );

    $signatureHash = md5( 'select * from posts' );

    Livewire::test( RecommendationsPanel::class )
        ->call( 'applyAction', 'slow_query:' . $signatureHash )
        ->assertDispatched( 'performance:navigate', tab: 'queries' );
} );

it( 'strips non-string values before persisting dismissals to the session', function (): void {
    // A hostile client can push non-string values into the public
    // $dismissed array via a Livewire update. dismiss() must filter
    // before writing to the session so we don't persist junk that
    // could bloat storage or trip downstream string casts.
    Livewire::test( RecommendationsPanel::class )
        ->set( 'dismissed', [ 'metric:LCP', [ 'evil' ], 42 ] )
        ->call( 'dismiss', 'cache:page_disabled' );

    expect( Session::get( RecommendationsPanel::DISMISSAL_KEY ) )
        ->toBe( [ 'metric:LCP', 'cache:page_disabled' ] );
} );

it( 'rejects a hostile non-string label value on re-hydration', function (): void {
    // Seed a recommendation so the 'Apply fix' label actually renders.
    config( [ 'artisanpack.performance.page_cache.enabled' => false ] );

    Livewire::test( RecommendationsPanel::class )
        ->set( 'labels', [ 'title' => [ 'evil' => 1 ] ] )
        ->assertOk()
        ->assertSee( 'Recommendations' );
} );

it( 'accepts a parent-passed dateRange prop that overrides the URL default', function (): void {
    Livewire::test( RecommendationsPanel::class, [ 'dateRange' => '30d' ] )
        ->assertSet( 'dateRange', '30d' );
} );

it( 'merges host-supplied labels over defaults', function (): void {
    Livewire::test( RecommendationsPanel::class, [
        'labels' => [
            'title'   => 'Priorities',
            'dismiss' => 'Hide',
        ],
    ] )
        ->assertSee( 'Priorities' );
} );
