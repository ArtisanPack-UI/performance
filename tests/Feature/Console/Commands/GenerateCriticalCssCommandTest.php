<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Css\CriticalCssExtractor;

beforeEach( function (): void {
    // Rebind the extractor as a singleton so both the command and the
    // test observe the same instance's registration state.
    $this->app->singleton( CriticalCssExtractor::class, function () {
        return new CriticalCssExtractor;
    } );
} );

it( 'warns when no routes are selected and exits successfully', function (): void {
    $this->artisan( 'perf:critical-css' )
        ->expectsOutput( 'No routes selected. Pass --route=<name> (repeatable) or --all.' )
        ->assertSuccessful();
} );

it( 'generates critical CSS for an explicit --route value', function (): void {
    $extractor = $this->app->make( CriticalCssExtractor::class );
    $extractor->registerSource( 'home', 'body { margin: 0; } .hero { padding: 4rem; }' );

    $this->artisan( 'perf:critical-css', [ '--route' => [ 'home' ] ] )
        ->assertSuccessful();

    expect( $extractor->forRoute( 'home' ) )
        ->toContain( 'body' )
        ->toContain( '.hero' );
} );

it( 'processes every registered route with --all', function (): void {
    $extractor = $this->app->make( CriticalCssExtractor::class );
    $extractor->registerSource( 'home', 'body { margin: 0; }' );
    $extractor->registerSource( 'products', '.hero { padding: 4rem; }' );

    // The command reads registered routes from config for --all.
    config( [ 'artisanpack.performance.css.critical.sources' => [
        'home'     => '',
        'products' => '',
    ] ] );

    $this->artisan( 'perf:critical-css', [ '--all' => true ] )
        ->assertSuccessful();

    expect( $extractor->forRoute( 'home' ) )->toContain( 'body' );
    expect( $extractor->forRoute( 'products' ) )->toContain( '.hero' );
} );

it( 'reports skip when the extracted CSS is empty', function (): void {
    $extractor = $this->app->make( CriticalCssExtractor::class );
    // A CSS body with no critical selectors extracts to an empty
    // string, exercising the "skip" branch.
    $extractor->registerSource( 'boring', '.some-random-class { color: red; }' );

    $this->artisan( 'perf:critical-css', [ '--route' => [ 'boring' ] ] )
        ->expectsOutputToContain( 'skip: boring' )
        ->assertSuccessful();
} );

it( 'returns FAILURE when a source path is unreadable', function (): void {
    $extractor = $this->app->make( CriticalCssExtractor::class );
    $extractor->registerSource( 'home', '/does/not/exist.css' );

    $this->artisan( 'perf:critical-css', [ '--route' => [ 'home' ] ] )
        ->assertFailed();
} );
