<?php

/**
 * `perf:install` artisan command.
 *
 * Bootstraps the Performance package inside a host application. Publishes
 * the package configuration, optionally publishes views, the client-side
 * JavaScript bundle and stylesheet, runs the package's database migrations,
 * validates environment requirements, clears configuration and view caches,
 * and prints the dashboard gate stub plus the next-step guidance a
 * developer needs to finish the install.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

/**
 * Installs the Performance package into a host application.
 *
 * The command is idempotent — re-running it publishes the same assets,
 * skips migrations that already ran, and never mutates existing published
 * files unless `--force` is supplied.
 *
 * @since 1.0.0
 */
class InstallCommand extends Command
{
    /**
     * The console command signature.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'perf:install
			{--config : Only publish the package configuration.}
			{--views : Publish the package Blade views for customization.}
			{--js : Publish the package JavaScript bundle.}
			{--css : Publish the package stylesheet.}
			{--migrations : Publish and run the package migrations.}
			{--skip-migrate : Skip running migrations after publishing.}
			{--skip-checks : Skip environment validation (PHP, Laravel, permissions).}
			{--force : Overwrite any existing published files.}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Install the ArtisanPack UI Performance package: publish assets, run migrations, and print next-step guidance.';

    /**
     * Executes the command.
     *
     * @since 1.0.0
     */
    public function handle(): int
    {
        $this->info( __( 'Installing ArtisanPack UI Performance…' ) );

        if ( ! $this->option( 'skip-checks' ) && ! $this->runEnvironmentChecks() ) {
            return self::FAILURE;
        }

        $force = (bool) $this->option( 'force' );

        // Explicit --config short-circuits every other action so callers can
        // republish the config file without touching migrations or views.
        if ( $this->option( 'config' ) && ! $this->anyOtherPublishFlag() ) {
            $this->publishConfig( $force );
            $this->clearCaches();
            $this->line( __( 'Configuration published. Re-run without --config to complete the install.' ) );

            return self::SUCCESS;
        }

        $this->publishConfig( $force );

        if ( $this->shouldPublishViews() ) {
            $this->publishViews( $force );
        }

        if ( $this->shouldPublishJs() ) {
            $this->publishJs( $force );
        }

        if ( $this->shouldPublishCss() ) {
            $this->publishCss( $force );
        }

        if ( $this->shouldRunMigrations() ) {
            $this->runMigrations();
        }

        $this->clearCaches();
        $this->printGateStub();
        $this->printNextSteps();

        return self::SUCCESS;
    }

    /**
     * Validates the runtime environment against the package's requirements.
     *
     * Fails fast on unsupported PHP, unsupported Laravel, or a
     * non-writable storage path so the developer sees the actionable error
     * before any files are published.
     *
     * @since 1.0.0
     */
    protected function runEnvironmentChecks(): bool
    {
        $this->line( __( 'Verifying environment…' ) );

        if ( version_compare( PHP_VERSION, '8.2.0', '<' ) ) {
            $this->error( __( 'PHP 8.2 or higher is required (running :version).', [ 'version' => PHP_VERSION ] ) );

            return false;
        }

        $laravelVersion = App::version();

        if ( version_compare( $laravelVersion, '10.0.0', '<' ) ) {
            $this->error( __( 'Laravel 10 or higher is required (running :version).', [ 'version' => $laravelVersion ] ) );

            return false;
        }

        $storagePath = storage_path();

        if ( ! is_writable( $storagePath ) ) {
            $this->error( __( 'Storage path is not writable: :path', [ 'path' => $storagePath ] ) );

            return false;
        }

        $this->line( __( '  PHP :php · Laravel :laravel · storage writable.', [
            'php'     => PHP_VERSION,
            'laravel' => $laravelVersion,
        ] ) );

        return true;
    }

    /**
     * Publishes the package configuration file.
     *
     * @since 1.0.0
     *
     * @param  bool  $force  Whether to overwrite an existing config file.
     */
    protected function publishConfig( bool $force ): void
    {
        $this->line( __( 'Publishing configuration…' ) );
        $this->callSilently( 'vendor:publish', array_filter( [
            '--tag'   => 'artisanpack-performance-config',
            '--force' => $force ?: null,
        ] ) );
        $this->info( __( '  ✓ Configuration published to config/artisanpack/performance.php' ) );
    }

    /**
     * Publishes the Blade views so applications can fork any template.
     *
     * @since 1.0.0
     *
     * @param  bool  $force  Whether to overwrite existing published views.
     */
    protected function publishViews( bool $force ): void
    {
        $this->line( __( 'Publishing views…' ) );
        $this->callSilently( 'vendor:publish', array_filter( [
            '--tag'   => 'performance-views',
            '--force' => $force ?: null,
        ] ) );
        $this->info( __( '  ✓ Views published to resources/views/vendor/artisanpack-ui/performance' ) );
    }

    /**
     * Publishes the client-side JavaScript bundle.
     *
     * @since 1.0.0
     *
     * @param  bool  $force  Whether to overwrite existing published JS files.
     */
    protected function publishJs( bool $force ): void
    {
        $this->line( __( 'Publishing JavaScript bundle…' ) );
        $this->callSilently( 'vendor:publish', array_filter( [
            '--tag'   => 'artisanpack-performance-js',
            '--force' => $force ?: null,
        ] ) );
        $this->info( __( '  ✓ JavaScript bundle published to resources/js/vendor/artisanpack-performance' ) );
    }

    /**
     * Publishes the package stylesheet.
     *
     * @since 1.0.0
     *
     * @param  bool  $force  Whether to overwrite an existing published stylesheet.
     */
    protected function publishCss( bool $force ): void
    {
        $this->line( __( 'Publishing stylesheet…' ) );
        $this->callSilently( 'vendor:publish', array_filter( [
            '--tag'   => 'performance-css',
            '--force' => $force ?: null,
        ] ) );
        $this->info( __( '  ✓ Stylesheet published to resources/css/vendor/artisanpack-ui/performance.css' ) );
    }

    /**
     * Runs the package's database migrations.
     *
     * Non-interactive runs (CI/CD via `--no-interaction`) proceed without a
     * prompt; interactive runs default to yes so the happy path stays a
     * single Enter keypress.
     *
     * @since 1.0.0
     */
    protected function runMigrations(): void
    {
        $shouldRun = ! $this->input->isInteractive()
            || $this->confirm( __( 'Run database migrations now?' ), true );

        if ( ! $shouldRun ) {
            $this->comment( __( 'Skipped migrations. Run `php artisan migrate` when ready.' ) );

            return;
        }

        $this->line( __( 'Running migrations…' ) );
        $this->call( 'migrate', [ '--force' => true ] );
        $this->info( __( '  ✓ Migrations complete' ) );
    }

    /**
     * Clears the application configuration and view caches so publishes and
     * config changes take effect on the next request.
     *
     * @since 1.0.0
     */
    protected function clearCaches(): void
    {
        $this->line( __( 'Clearing caches…' ) );
        $this->callSilently( 'config:clear' );
        $this->callSilently( 'view:clear' );
    }

    /**
     * Prints the dashboard gate stub for the developer to paste into their
     * AuthServiceProvider.
     *
     * @since 1.0.0
     */
    protected function printGateStub(): void
    {
        $gate = (string) config( 'artisanpack.performance.dashboard.gate', 'view-performance-dashboard' );

        if ( '' === $gate ) {
            $gate = 'view-performance-dashboard';
        }

        $this->newLine();
        $this->info( __( 'Performance package installed.' ) );
        $this->newLine();
        $this->comment( __( 'Add the following gate definition to your AuthServiceProvider::boot() method to authorize dashboard access:' ) );
        $this->newLine();
        $this->line( '    use Illuminate\\Support\\Facades\\Gate;' );
        $this->newLine();
        $this->line( "    Gate::define('{$gate}', function (\$user) {" );
        $this->line( '        // Customize this to match your authorization model.' );
        $this->line( "        return method_exists(\$user, 'hasRole') && \$user->hasRole('admin');" );
        $this->line( '    });' );
    }

    /**
     * Prints developer-facing next-steps guidance.
     *
     * @since 1.0.0
     */
    protected function printNextSteps(): void
    {
        $this->newLine();
        $this->comment( __( 'Next steps:' ) );
        $this->line( '  1. ' . __( 'Toggle features on in config/artisanpack/performance.php (all features ship disabled).' ) );
        $this->line( '  2. ' . __( 'Define the dashboard gate shown above in AuthServiceProvider.' ) );
        $this->line( '  3. ' . __( 'Add `@perfMonitor` to your layout to start collecting Core Web Vitals.' ) );
        $this->line( '  4. ' . __( 'Visit /admin/performance (or your configured route prefix) to review the dashboard.' ) );
        $this->line( '  5. ' . __( 'For React/Vue hosts, import from `@artisanpack-ui/performance` and rebuild your bundle.' ) );
        $this->newLine();
        $this->comment( __( 'See CHANGELOG.md and the examples/ directory for feature-by-feature usage.' ) );
    }

    /**
     * Reports whether any publish-scope flag other than `--config` was set.
     *
     * @since 1.0.0
     */
    protected function anyOtherPublishFlag(): bool
    {
        return (bool) $this->option( 'views' )
            || (bool) $this->option( 'js' )
            || (bool) $this->option( 'css' )
            || (bool) $this->option( 'migrations' );
    }

    /**
     * Decides whether the views should be published on this run.
     *
     * `--views` always publishes; otherwise the interactive prompt asks and
     * non-interactive runs default to yes so a hands-off install still gets
     * a working dashboard.
     *
     * @since 1.0.0
     */
    protected function shouldPublishViews(): bool
    {
        if ( $this->option( 'views' ) ) {
            return true;
        }

        if ( ! $this->input->isInteractive() ) {
            return true;
        }

        return (bool) $this->confirm( __( 'Publish views for customization?' ), false );
    }

    /**
     * Decides whether the JavaScript bundle should be published on this run.
     *
     * @since 1.0.0
     */
    protected function shouldPublishJs(): bool
    {
        if ( $this->option( 'js' ) ) {
            return true;
        }

        if ( ! $this->input->isInteractive() ) {
            return true;
        }

        return (bool) $this->confirm( __( 'Publish the JavaScript bundle (web-vitals, metrics-chart, etc.)?' ), true );
    }

    /**
     * Decides whether the stylesheet should be published on this run.
     *
     * @since 1.0.0
     */
    protected function shouldPublishCss(): bool
    {
        if ( $this->option( 'css' ) ) {
            return true;
        }

        if ( ! $this->input->isInteractive() ) {
            return true;
        }

        return (bool) $this->confirm( __( 'Publish the package stylesheet?' ), false );
    }

    /**
     * Decides whether migrations should be run on this invocation.
     *
     * @since 1.0.0
     */
    protected function shouldRunMigrations(): bool
    {
        if ( $this->option( 'skip-migrate' ) ) {
            return false;
        }

        if ( $this->option( 'migrations' ) ) {
            return true;
        }

        return true;
    }
}
