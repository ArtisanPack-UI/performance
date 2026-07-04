<?php

/**
 * Feature tests covering the admin JSON API endpoints that back the
 * React/Vue companion components. These verify the routes are wired,
 * the auth gate is enforced, and the payload shapes match what the
 * front-end consumers expect.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\Performance\Models\PerformanceMetric;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config( [
        'artisanpack.performance.routes.enabled'         => true,
        'artisanpack.performance.routes.api_prefix'      => 'api/performance',
        'artisanpack.performance.routes.api_middleware'  => [],
        'artisanpack.performance.routes.api_throttle'    => '1000,1',
        'artisanpack.performance.dashboard.gate'         => 'view-performance-dashboard',
    ] );

    Gate::define( 'view-performance-dashboard', static fn (): bool => true );

    $this->actingAs( new GenericUser( [ 'id' => 1 ] ) );
} );

it( 'denies dashboard access when the gate returns false', function (): void {
    Gate::define( 'view-performance-dashboard', static fn (): bool => false );

    $this->getJson( '/api/performance/admin/dashboard' )->assertForbidden();
} );

it( 'falls back to the default gate when the config key is blank', function (): void {
    config( [ 'artisanpack.performance.dashboard.gate' => '' ] );
    Gate::define( 'view-performance-dashboard', static fn (): bool => false );

    $this->getJson( '/api/performance/admin/dashboard' )->assertForbidden();
} );

it( 'returns the dashboard payload with range/overview/pages/cache keys', function (): void {
    PerformanceMetric::query()->create( [
        'metric'       => 'LCP',
        'route'        => '/products',
        'date'         => today()->toDateString(),
        'p50'          => 1800.0,
        'p75'          => 2100.0,
        'p90'          => 2400.0,
        'p99'          => 2800.0,
        'sample_count' => 100,
    ] );

    $response = $this->getJson( '/api/performance/admin/dashboard?range=7d' );

    $response->assertSuccessful()
        ->assertJsonStructure( [
            'range',
            'overview',
            'pages',
            'cache' => [ 'page', 'fragment' ],
        ] );

    expect( $response->json( 'range' ) )->toBe( '7d' );
} );

it( 'coerces an unknown range key to 7d on the dashboard endpoint', function (): void {
    $this->getJson( '/api/performance/admin/dashboard?range=bogus' )
        ->assertSuccessful()
        ->assertJson( [ 'range' => '7d' ] );
} );

it( 'returns a chart payload with datasets keyed by metric', function (): void {
    PerformanceMetric::query()->create( [
        'metric'       => 'LCP',
        'route'        => '/',
        'date'         => today()->toDateString(),
        'p50'          => 1500.0,
        'p75'          => 1800.0,
        'p90'          => 2000.0,
        'p99'          => 2200.0,
        'sample_count' => 50,
    ] );

    $response = $this->getJson( '/api/performance/admin/chart?range=7d&metrics=LCP' );

    $response->assertSuccessful()
        ->assertJsonStructure( [
            'type',
            'labels',
            'datasets' => [ [ 'metric', 'values', 'threshold' ] ],
            'colors'   => [ 'good', 'needs_improvement', 'poor' ],
        ] );

    expect( $response->json( 'datasets.0.metric' ) )->toBe( 'LCP' );
} );

it( 'returns a cache snapshot with summary + entries + tags', function (): void {
    $this->getJson( '/api/performance/admin/cache' )
        ->assertSuccessful()
        ->assertJsonStructure( [
            'summary' => [ 'page', 'fragment' ],
            'page_entries',
            'fragment_tags',
        ] );
} );

it( 'rejects an unknown cache action', function (): void {
    $this->postJson( '/api/performance/admin/cache/actions', [ 'action' => 'nonsense' ] )
        ->assertStatus( 422 );
} );

it( 'reports the empty-URLs case as an error from the warm action', function (): void {
    config( [ 'artisanpack.performance.cache.cache_warming.urls' => [] ] );

    $response = $this->postJson( '/api/performance/admin/cache/actions', [ 'action' => 'warm' ] );

    $response->assertSuccessful()
        ->assertJson( [ 'is_error' => true ] );
} );

it( 'returns the queries payload with rows/available_routes/sort keys', function (): void {
    $response = $this->getJson( '/api/performance/admin/queries?range=7d&sort=time' );

    $response->assertSuccessful()
        ->assertJsonStructure( [ 'rows', 'available_routes', 'sort' ] );

    expect( $response->json( 'sort' ) )->toBe( 'time' );
} );

it( 'coerces an unknown queries sort to "time"', function (): void {
    $this->getJson( '/api/performance/admin/queries?sort=bogus' )
        ->assertSuccessful()
        ->assertJson( [ 'sort' => 'time' ] );
} );

it( 'streams the queries CSV export with a text/csv content type', function (): void {
    $response = $this->get( '/api/performance/admin/queries/export?range=7d' );

    $response->assertSuccessful();
    expect( $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/csv' );
} );

it( 'returns the recommendations payload with items + dismissed', function (): void {
    $response = $this->getJson( '/api/performance/admin/recommendations?range=7d' );

    $response->assertSuccessful()
        ->assertJsonStructure( [ 'items', 'dismissed' ] );
} );

it( 'rejects unknown recommendation actions', function (): void {
    $this->postJson( '/api/performance/admin/recommendations/actions', [ 'action' => 'nonsense' ] )
        ->assertStatus( 422 );
} );

it( 'persists dismissals to the session and returns them on the next read', function (): void {
    $this->postJson( '/api/performance/admin/recommendations/actions', [
        'action' => 'dismiss',
        'id'     => 'perf-slow-lcp',
    ] )
        ->assertSuccessful()
        ->assertJson( [
            'action'   => 'dismiss',
            'is_error' => false,
        ] );

    $response = $this->getJson( '/api/performance/admin/recommendations' );

    $response->assertSuccessful();
    expect( $response->json( 'dismissed' ) )->toContain( 'perf-slow-lcp' );
} );

it( 'clears dismissals on reset', function (): void {
    $this->postJson( '/api/performance/admin/recommendations/actions', [
        'action' => 'dismiss',
        'id'     => 'perf-slow-lcp',
    ] );

    $this->postJson( '/api/performance/admin/recommendations/actions', [
        'action' => 'reset',
    ] )->assertSuccessful();

    expect( $this->getJson( '/api/performance/admin/recommendations' )->json( 'dismissed' ) )->toBe( [] );
} );
