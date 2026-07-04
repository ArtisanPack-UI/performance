<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Css\CriticalCssExtractor;
use ArtisanPackUI\Performance\View\Components\CriticalCss;
use Illuminate\Support\Facades\Route;

beforeEach( function (): void {
    // Rebind the extractor as a shared singleton so the component and
    // the test observe the same registration state.
    $this->app->singleton( CriticalCssExtractor::class, function () {
        return new CriticalCssExtractor;
    } );
} );

it( 'resolves the explicit route argument when provided', function (): void {
    $extractor = $this->app->make( CriticalCssExtractor::class );
    $extractor->registerSource( 'home', 'body { margin: 0; }' );

    $component = new CriticalCss( 'home' );

    expect( $component->resolvedRoute )->toBe( 'home' );
    expect( $component->css )->toContain( 'body' );
} );

it( 'falls back to the extractor default when the route is unresolved', function (): void {
    $component = new CriticalCss;

    expect( $component->resolvedRoute )->toBe( CriticalCssExtractor::DEFAULT_ROUTE );
} );

it( 'reads the current request route name when no argument is passed', function (): void {
    Route::get( '/perf-critical-css-test', function () {
        return 'ok';
    } )->name( 'perf.critical-css-test' );

    $this->get( '/perf-critical-css-test' )->assertOk();

    $extractor = $this->app->make( CriticalCssExtractor::class );
    $extractor->registerSource( 'perf.critical-css-test', 'body { color: red; }' );

    // Rebuild the component inside a request scope so `request()->route()`
    // resolves to the named route above.
    $component = null;

    Route::get( '/perf-critical-css-test-build', function () use ( &$component ) {
        $component = new CriticalCss;

        return response( 'built' );
    } )->name( 'perf.critical-css-test' );

    $this->get( '/perf-critical-css-test-build' )->assertOk();

    expect( $component )->not->toBeNull();
    expect( $component->resolvedRoute )->toBe( 'perf.critical-css-test' );
} );

it( 'returns empty CSS when the extractor cannot be resolved for the route', function (): void {
    $component = new CriticalCss( 'unregistered' );

    expect( $component->css )->toBe( '' );
} );

it( 'points render() at the package template', function (): void {
    $view = ( new CriticalCss( 'home' ) )->render();

    expect( $view->name() )->toBe( 'performance::components.critical-css' );
} );
