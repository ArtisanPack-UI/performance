<?php

declare( strict_types=1 );

it( 'runs the perf:install command non-interactively to completion', function (): void {
    $this->artisan( 'perf:install', [ '--no-interaction' => true, '--force' => true ] )
        ->assertExitCode( 0 )
        ->expectsOutputToContain( 'Performance package installed.' );
} );

it( 'publishes the package configuration to the config path', function (): void {
    $configPath = config_path( 'artisanpack/performance.php' );

    if ( file_exists( $configPath ) ) {
        @unlink( $configPath );
    }

    $this->artisan( 'perf:install', [
        '--no-interaction' => true,
        '--force'          => true,
        '--skip-migrate'   => true,
    ] )->assertExitCode( 0 );

    expect( file_exists( $configPath ) )->toBeTrue();

    @unlink( $configPath );
} );

it( 'short-circuits with --config to publish only the configuration file', function (): void {
    $this->artisan( 'perf:install', [
        '--config'         => true,
        '--no-interaction' => true,
        '--force'          => true,
    ] )
        ->assertExitCode( 0 )
        ->expectsOutputToContain( 'Configuration published.' );
} );

it( 'prints the dashboard gate stub', function (): void {
    $this->artisan( 'perf:install', [
        '--no-interaction' => true,
        '--force'          => true,
        '--skip-migrate'   => true,
    ] )
        ->assertExitCode( 0 )
        ->expectsOutputToContain( "Gate::define('view-performance-dashboard'" );
} );

it( 'honors a custom dashboard gate name from config', function (): void {
    config()->set( 'artisanpack.performance.dashboard.gate', 'manage-perf' );

    $this->artisan( 'perf:install', [
        '--no-interaction' => true,
        '--force'          => true,
        '--skip-migrate'   => true,
    ] )
        ->assertExitCode( 0 )
        ->expectsOutputToContain( "Gate::define('manage-perf'" );
} );

it( 'skips migrations when --skip-migrate is set', function (): void {
    $this->artisan( 'perf:install', [
        '--no-interaction' => true,
        '--force'          => true,
        '--skip-migrate'   => true,
    ] )
        ->assertExitCode( 0 )
        ->doesntExpectOutput( 'Running migrations…' );
} );

it( 'prints the next-step guidance', function (): void {
    $this->artisan( 'perf:install', [
        '--no-interaction' => true,
        '--force'          => true,
        '--skip-migrate'   => true,
    ] )
        ->assertExitCode( 0 )
        ->expectsOutputToContain( 'Next steps:' );
} );

it( 'reports the PHP and Laravel versions during the environment check', function (): void {
    $this->artisan( 'perf:install', [
        '--no-interaction' => true,
        '--force'          => true,
        '--skip-migrate'   => true,
    ] )
        ->assertExitCode( 0 )
        ->expectsOutputToContain( 'Verifying environment…' );
} );
