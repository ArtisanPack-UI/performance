<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Models\RawMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config( [
        'artisanpack.performance.monitoring.enabled'           => true,
        'artisanpack.performance.monitoring.store_raw_metrics' => true,
        'artisanpack.performance.monitoring.sample_rate'       => 100,
        'artisanpack.performance.routes.enabled'               => true,
        'artisanpack.performance.routes.api_prefix'            => 'api/performance',
        'artisanpack.performance.routes.api_middleware'        => [],
        'artisanpack.performance.routes.api_throttle'          => '1000,1',
    ] );
} );

it( 'accepts a valid Web Vitals payload and stores it', function (): void {
    $response = $this->postJson( '/api/performance/metrics', [
        'name'       => 'LCP',
        'value'      => 2100,
        'delta'      => 2100,
        'id'         => 'v3-1234567890',
        'page'       => '/products',
        'connection' => '4g',
    ] );

    $response->assertSuccessful()
        ->assertJson( [ 'success' => true ] );

    expect( RawMetric::query()->count() )->toBe( 1 );

    $metric = RawMetric::query()->first();

    expect( $metric->name )->toBe( 'LCP' )
        ->and( $metric->value )->toBe( 2100.0 )
        ->and( $metric->vital_id )->toBe( 'v3-1234567890' )
        ->and( $metric->url )->toBe( '/products' )
        ->and( $metric->connection_type )->toBe( '4g' );
} );

it( 'rejects unknown metric names with 422', function (): void {
    $response = $this->postJson( '/api/performance/metrics', [
        'name'  => 'BogusMetric',
        'value' => 100,
    ] );

    $response->assertStatus( 422 )
        ->assertJson( [
            'success' => false,
            'reason'  => 'unknown-metric',
        ] );

    expect( RawMetric::query()->count() )->toBe( 0 );
} );

it( 'returns 422 when validation fails', function (): void {
    $response = $this->postJson( '/api/performance/metrics', [
        'value' => 100,
    ] );

    $response->assertStatus( 422 );
    expect( RawMetric::query()->count() )->toBe( 0 );
} );

it( 'returns 403 when monitoring is disabled', function (): void {
    config( [ 'artisanpack.performance.monitoring.enabled' => false ] );

    $response = $this->postJson( '/api/performance/metrics', [
        'name'  => 'LCP',
        'value' => 1000,
    ] );

    $response->assertStatus( 403 )
        ->assertJson( [ 'success' => false ] );
} );

it( 'does not persist a sample when store_raw_metrics is off', function (): void {
    config( [ 'artisanpack.performance.monitoring.store_raw_metrics' => false ] );

    $response = $this->postJson( '/api/performance/metrics', [
        'name'  => 'CLS',
        'value' => 0.05,
    ] );

    $response->assertSuccessful()
        ->assertJson( [ 'success' => true ] );

    expect( RawMetric::query()->count() )->toBe( 0 );
} );

it( 'drops every sample when sample_rate is zero', function (): void {
    config( [ 'artisanpack.performance.monitoring.sample_rate' => 0 ] );

    $response = $this->postJson( '/api/performance/metrics', [
        'name'  => 'FCP',
        'value' => 1200,
    ] );

    $response->assertSuccessful()
        ->assertJson( [
            'success' => false,
            'reason'  => 'sampled-out',
        ] );

    expect( RawMetric::query()->count() )->toBe( 0 );
} );

it( 'rejects oversized vital_id and rating values with 422', function (): void {
    $this->postJson( '/api/performance/metrics', [
        'name'   => 'INP',
        'value'  => 180,
        'id'     => str_repeat( 'a', 100 ),
        'rating' => str_repeat( 'g', 100 ),
    ] )->assertStatus( 422 )
        ->assertJsonValidationErrors( [ 'id', 'rating' ] );

    expect( RawMetric::query()->count() )->toBe( 0 );
} );

it( 'accepts the deviceType field web-vitals.js sends', function (): void {
    $this->postJson( '/api/performance/metrics', [
        'name'       => 'LCP',
        'value'      => 2100,
        'deviceType' => 'mobile',
        'connection' => '4g',
    ] )->assertSuccessful();

    expect( RawMetric::query()->first()->device_type )->toBe( 'mobile' );
} );

it( 'still accepts the legacy device field for backward compatibility', function (): void {
    $this->postJson( '/api/performance/metrics', [
        'name'   => 'LCP',
        'value'  => 2100,
        'device' => 'desktop',
    ] )->assertSuccessful();

    expect( RawMetric::query()->first()->device_type )->toBe( 'desktop' );
} );

it( 'prefers deviceType over device when both are sent', function (): void {
    $this->postJson( '/api/performance/metrics', [
        'name'       => 'LCP',
        'value'      => 2100,
        'device'     => 'desktop',
        'deviceType' => 'mobile',
    ] )->assertSuccessful();

    expect( RawMetric::query()->first()->device_type )->toBe( 'mobile' );
} );

it( 'persists the extra payload when supplied', function (): void {
    $this->postJson( '/api/performance/metrics', [
        'name'  => 'LCP',
        'value' => 2100,
        'extra' => [ 'tenant' => 42, 'feature' => 'beta' ],
    ] )->assertSuccessful();

    expect( RawMetric::query()->first()->extra )->toBe( [
        'tenant'  => 42,
        'feature' => 'beta',
    ] );
} );

it( 'returns 403 when collect_web_vitals is disabled', function (): void {
    config( [ 'artisanpack.performance.monitoring.collect_web_vitals' => false ] );

    $this->postJson( '/api/performance/metrics', [
        'name'  => 'LCP',
        'value' => 2100,
    ] )->assertStatus( 403 )
        ->assertJson( [
            'success' => false,
            'reason'  => 'web-vitals-disabled',
        ] );

    expect( RawMetric::query()->count() )->toBe( 0 );
} );

it( 'rejects control characters in string fields (separator-poisoning guard)', function (): void {
    $this->postJson( '/api/performance/metrics', [
        'name'   => 'LCP',
        'value'  => 2100,
        'device' => "mobile\x1Fpoison",
    ] )->assertStatus( 422 )
        ->assertJsonValidationErrors( [ 'device' ] );

    expect( RawMetric::query()->count() )->toBe( 0 );
} );
