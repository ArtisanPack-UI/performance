<?php

/**
 * Performance service provider.
 *
 * Bootstraps the Performance package by merging configuration, registering
 * the `performance` container binding, loading and publishing database
 * migrations, and reserving the event-listener registration seam used by
 * subsequent phases.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance;

use ArtisanPackUI\Performance\Console\Commands\GenerateWebPCommand;
use ArtisanPackUI\Performance\Images\DominantColorExtractor;
use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use ArtisanPackUI\Performance\Services\ImageService;
use ArtisanPackUI\Performance\Services\PerformanceService;
use ArtisanPackUI\Performance\View\Components\LazyImage;
use ArtisanPackUI\Performance\View\Components\ResponsiveImage;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Performance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class PerformanceServiceProvider extends ServiceProvider
{
	/**
	 * Registers package services.
	 *
	 * Merges the package configuration into a temporary key (re-merged into
	 * the unified `artisanpack.performance` config during boot) and binds
	 * the PerformanceService singleton accessed via the Performance facade.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/performance.php',
			'artisanpack-performance-temp',
		);

		$this->app->singleton( FormatConverter::class, function ( $app ) {
			return new FormatConverter();
		} );

		$this->app->singleton( DominantColorExtractor::class, function ( $app ) {
			return new DominantColorExtractor();
		} );

		$this->app->singleton( ImageService::class, function ( $app ) {
			return new ImageService(
				$app->make( FormatConverter::class ),
				$app->make( DominantColorExtractor::class ),
			);
		} );

		$this->app->singleton( ResponsiveImageGenerator::class, function ( $app ) {
			return new ResponsiveImageGenerator( $app->make( ImageService::class ) );
		} );

		$this->app->singleton( 'performance', function ( $app ) {
			return new PerformanceService( $app->make( ImageService::class ) );
		} );

		$this->app->singleton( PerformanceService::class, function ( $app ) {
			return $app->make( 'performance' );
		} );
	}

	/**
	 * Bootstraps package services.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void
	{
		$this->mergeConfiguration();
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'performance' );
		$this->loadBladeComponents();
		$this->registerEventListeners();
		$this->registerCommands();
		$this->publishConfiguration();
	}

	/**
	 * Registers the package's Blade components under the `perf` prefix.
	 *
	 * The components are also published under `resources/views/components`
	 * so applications can override the templates without forking the
	 * component classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function loadBladeComponents(): void
	{
		$this->loadViewComponentsAs( 'perf', [
			LazyImage::class,
			ResponsiveImage::class,
		] );
	}

	/**
	 * Registers the package's console commands.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerCommands(): void
	{
		if ( $this->app->runningInConsole() ) {
			$this->commands( [
				GenerateWebPCommand::class,
			] );
		}
	}

	/**
	 * Merges the package's defaults into the unified `artisanpack.performance` config.
	 *
	 * Any user overrides published to `config/artisanpack/performance.php`
	 * win over the package defaults. List-valued options (e.g. `images.sizes`,
	 * `exclude_patterns`, `vary_by`) are replaced wholesale rather than merged
	 * per-index so users can shrink them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function mergeConfiguration(): void
	{
		$packageDefaults = config( 'artisanpack-performance-temp', [] );
		$userConfig      = config( 'artisanpack.performance', [] );
		$mergedConfig    = $this->mergeConfigArrays( $packageDefaults, $userConfig );
		config( [ 'artisanpack.performance' => $mergedConfig ] );
	}

	/**
	 * Recursively merges two config arrays, replacing list-valued entries wholesale.
	 *
	 * Behaves like `array_replace_recursive` for associative keys, but for
	 * numerically-indexed (list) arrays the user value replaces the default
	 * entirely. This prevents per-index bleed-through when a user shortens or
	 * reorders a list option.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<mixed, mixed> $defaults The package defaults.
	 * @param  array<mixed, mixed> $overrides The user overrides.
	 *
	 * @return array<mixed, mixed> The merged result.
	 */
	protected function mergeConfigArrays( array $defaults, array $overrides ): array
	{
		foreach ( $overrides as $key => $value ) {
			if (
				is_array( $value )
				&& isset( $defaults[ $key ] )
				&& is_array( $defaults[ $key ] )
				&& ! $this->isList( $value )
				&& ! $this->isList( $defaults[ $key ] )
			) {
				$defaults[ $key ] = $this->mergeConfigArrays( $defaults[ $key ], $value );
				continue;
			}

			$defaults[ $key ] = $value;
		}

		return $defaults;
	}

	/**
	 * Detects whether the given array is a list (sequential integer keys from 0).
	 *
	 * Backports `array_is_list()` for PHP 8.0 compatibility. The package
	 * requires PHP 8.2+ but this guards against any future runtime where the
	 * function is shadowed.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<mixed, mixed> $value The array to inspect.
	 *
	 * @return bool True when the array is a list.
	 */
	protected function isList( array $value ): bool
	{
		return array_is_list( $value );
	}

	/**
	 * Registers default event listeners.
	 *
	 * Phase 1 reserves this seam without binding any listeners. Subsequent
	 * phases bind listeners for image optimization, query analysis, and
	 * threshold alerting here.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerEventListeners(): void
	{
		// Bindings registered by later phases (image, monitoring, alerts).
	}

	/**
	 * Publishes the package configuration file.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function publishConfiguration(): void
	{
		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../config/performance.php' => config_path( 'artisanpack/performance.php' ),
			], 'artisanpack-performance-config' );
		}
	}
}
