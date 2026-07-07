<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\Ai\AiServiceProvider;
use ArtisanPackUI\Performance\PerformanceServiceProvider;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base Test Case
 *
 * Provides base functionality for all package tests.
 *
 * @since   1.0.0
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Gets package providers.
     *
     * @since 1.0.0
     *
     * @param  Application  $app  The application instance.
     *
     * @return array<int, class-string> Array of service provider class names.
     */
    protected function getPackageProviders( $app ): array
    {
        return [
            LivewireServiceProvider::class,
            AiServiceProvider::class,
            PerformanceServiceProvider::class,
        ];
    }

    /**
     * Defines environment setup.
     *
     * @since 1.0.0
     *
     * @param  Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        // Setup app key for encryption
        $app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );

        // Setup default database to use sqlite :memory:
        $app['config']->set( 'database.default', 'testbench' );
        $app['config']->set( 'database.connections.testbench', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ] );

        // Testbench has no sanctum guard configured, so the shipped
        // `['api', 'auth:sanctum']` default on the AI route group would
        // blow up before any test could exercise the controller. Route
        // middleware is bound at service-provider boot, so this has to
        // land before boot — hence defineEnvironment rather than the
        // per-test AiAgentTestSetup::bootstrap().
        $app['config']->set( 'artisanpack.performance.routes.ai_middleware', [ 'api' ] );
    }
}
