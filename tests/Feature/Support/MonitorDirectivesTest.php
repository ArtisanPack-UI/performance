<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Support\MonitorDirectives;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

beforeEach( function (): void {
    config( [ 'artisanpack.performance.monitoring' => [
        'enabled'              => true,
        'collect_web_vitals'   => true,
        'endpoint'             => '/api/performance/metrics',
        'sample_rate'          => 100,
        'store_raw_metrics'    => false,
        'aggregation_interval' => 'hourly',
        'retention_days'       => 90,
    ] ] );
} );

it( 'emits the inline config script and the module script tag', function (): void {
    $output = MonitorDirectives::perfMonitor();

    expect( $output )->toContain( 'window.ArtisanPackPerformance' );
    expect( $output )->toContain( 'window.ArtisanPackPerformance.monitor=' );
    expect( $output )->toContain( '<script type="module" src="/vendor/artisanpack-performance/web-vitals.js"></script>' );
} );

it( 'json-encodes the configuration payload with safe inline-script flags', function (): void {
    $output = MonitorDirectives::renderConfigBlock( [
        'endpoint'   => '/api/perf',
        'sampleRate' => 25,
        'csrfToken'  => null,
        'page'       => '/dashboard',
        'route'      => 'dashboard.index',
        'extra'      => [],
    ] );

    expect( $output )->toContain( '"endpoint":"/api/perf"' );
    expect( $output )->toContain( '"sampleRate":25' );
} );

it( 'hex-encodes raw HTML angle brackets inside string values to prevent script breakout', function (): void {
    // The JSON payload itself must never contain a literal </script>
    // sequence — that would close the surrounding inline block early.
    // Hex flags turn it into </script>.
    $output = MonitorDirectives::renderConfigBlock( [
        'endpoint' => '/m',
        'extra'    => [ 'note' => 'inject </script><script>alert(1)</script>' ],
    ] );

    // Anchored regex so a future change to the inline-script template
    // (extra attributes, trailing whitespace, different closing tag
    // placement) surfaces as an obvious match failure on THIS line
    // instead of silently truncating the payload — the previous
    // sscanf-based assertion stopped at the first `;` and would have
    // passed quietly for several broken templates.
    expect( preg_match( '#window\.ArtisanPackPerformance\.monitor=(?P<payload>.+);</script>$#', $output, $matches ) )
        ->toBe( 1 );

    $payload = $matches['payload'];

    expect( $payload )->not->toContain( '</script>' );
    expect( $payload )->not->toContain( '<script>' );
} );

it( 'returns empty output when monitoring is disabled', function (): void {
    config( [ 'artisanpack.performance.monitoring.enabled' => false ] );

    expect( MonitorDirectives::perfMonitor() )->toBe( '' );
} );

it( 'returns empty output when collect_web_vitals is disabled', function (): void {
    config( [ 'artisanpack.performance.monitoring.collect_web_vitals' => false ] );

    expect( MonitorDirectives::perfMonitor() )->toBe( '' );
} );

it( 'honors per-call endpoint and sampleRate overrides', function (): void {
    $config = MonitorDirectives::buildConfig( [
        'endpoint'   => '/metrics/v2',
        'sampleRate' => 10,
    ] );

    expect( $config['endpoint'] )->toBe( '/metrics/v2' );
    expect( $config['sampleRate'] )->toBe( 10 );
} );

it( 'carries arbitrary extra metadata through to the JS payload', function (): void {
    $output = MonitorDirectives::perfMonitor( [
        'extra' => [ 'tenant' => 'acme', 'env' => 'prod' ],
    ] );

    expect( $output )->toContain( '"extra":{"tenant":"acme","env":"prod"}' );
} );

it( 'honors a src override for bundled deployments', function (): void {
    $output = MonitorDirectives::perfMonitor( [
        'src' => '/build/assets/perf-vitals.js',
    ] );

    expect( $output )->toContain( '<script type="module" src="/build/assets/perf-vitals.js"></script>' );
} );

it( 'allows overrides to blank out server-supplied context', function (): void {
    $config = MonitorDirectives::buildConfig( [
        'csrfToken' => '',
        'page'      => '',
        'route'     => '',
    ] );

    expect( $config['csrfToken'] )->toBe( '' );
    expect( $config['page'] )->toBe( '' );
    expect( $config['route'] )->toBe( '' );
} );

it( 'compiles the @perfMonitor Blade directive into a call to MonitorDirectives', function (): void {
    Route::get( '/dashboard', static fn () => 'ok' )->name( 'dashboard.index' );

    $rendered = Blade::render( '@perfMonitor' );

    expect( $rendered )->toContain( 'window.ArtisanPackPerformance.monitor=' );
    expect( $rendered )->toContain( '<script type="module"' );
} );

it( 'forwards inline Blade arguments to the helper', function (): void {
    $rendered = Blade::render( "@perfMonitor(['extra' => ['flow' => 'checkout']])" );

    expect( $rendered )->toContain( '"extra":{"flow":"checkout"}' );
} );
