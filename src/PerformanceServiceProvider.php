<?php

/**
 * Performance service provider.
 *
 * Bootstraps the Performance by registering services and bindings.
 *
 * @since      1.0.0
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @package    ArtisanPack_UI
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Performance.
 *
 * Bootstraps the Performance by registering services and bindings.
 * Extend this class with your package's configuration, migrations,
 * routes, views, and other service registrations.
 *
 * @since      1.0.0
 * @subpackage Performance
 *
 * @package    ArtisanPack_UI
 */
class PerformanceServiceProvider extends ServiceProvider
{
	/**
	 * Registers any application services.
	 *
	 * Binds the Performance class as a singleton in the container.
	 * Add additional service registrations here.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( 'performance', function ( $app ) {
			return new Performance();
		} );
	}

	/**
	 * Bootstraps any application services.
	 *
	 * Add package bootstrapping here such as:
	 * - Configuration publishing: $this->publishes([...])
	 * - Migration loading: $this->loadMigrationsFrom(...)
	 * - View loading: $this->loadViewsFrom(...)
	 * - Route loading: $this->loadRoutesFrom(...)
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void
	{
		// Add your package bootstrapping here
	}
}
