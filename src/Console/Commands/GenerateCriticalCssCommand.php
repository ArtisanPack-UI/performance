<?php

/**
 * `perf:critical-css` artisan command.
 *
 * Generates critical CSS for one or more routes and warms the
 * `CriticalCssExtractor` cache. Run via cron, deploy hook, or manually after
 * stylesheet changes so the `@criticalCss` directive can serve from cache on
 * every request.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Console\Commands;

use ArtisanPackUI\Performance\Css\CriticalCssExtractor;
use Illuminate\Console\Command;
use Throwable;

/**
 * Generate critical CSS command class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class GenerateCriticalCssCommand extends Command
{
	/**
	 * The console command signature.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $signature = 'perf:critical-css
		{--route=* : Specific route name(s) to generate critical CSS for}
		{--all : Generate critical CSS for every registered route}
		{--force : Clear cached entries before regenerating}';

	/**
	 * The console command description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'Generate and cache critical CSS for one or more routes.';

	/**
	 * Executes the command.
	 *
	 * @since 1.0.0
	 *
	 * @param  CriticalCssExtractor $extractor Resolved extractor instance.
	 *
	 * @return int
	 */
	public function handle( CriticalCssExtractor $extractor ): int
	{
		$routes = $this->resolveRoutes( $extractor );

		if ( empty( $routes ) ) {
			$this->warn( 'No routes selected. Pass --route=<name> (repeatable) or --all.' );

			return self::SUCCESS;
		}

		if ( $this->option( 'force' ) ) {
			$extractor->clearCache();
		}

		$generated = 0;
		$failed    = 0;
		$empty     = 0;

		foreach ( $routes as $route ) {
			try {
				$css = $extractor->generate( $route );
			} catch ( Throwable $exception ) {
				$this->error( "fail: {$route} ({$exception->getMessage()})" );
				$failed++;
				continue;
			}

			if ( '' === $css ) {
				$this->line( "skip: {$route} (no CSS produced)" );
				$empty++;
				continue;
			}

			// Generation skipped the cache; trigger forRoute() to warm it so
			// subsequent requests can read straight from the cache layer.
			$extractor->forRoute( $route );

			$this->line( sprintf( 'ok:   %s (%d bytes)', $route, strlen( $css ) ) );
			$generated++;
		}

		$this->info( "Generated {$generated}, empty {$empty}, failed {$failed}." );

		return $failed > 0 ? self::FAILURE : self::SUCCESS;
	}

	/**
	 * Resolves the routes to process.
	 *
	 * `--route=<name>` accepts multiple values via repeated flags. `--all`
	 * processes every route the extractor has sources registered for.
	 *
	 * @since 1.0.0
	 *
	 * @param  CriticalCssExtractor $extractor Resolved extractor instance.
	 *
	 * @return array<int, string>
	 */
	protected function resolveRoutes( CriticalCssExtractor $extractor ): array
	{
		$explicit = (array) $this->option( 'route' );
		$explicit = array_values( array_filter( array_map( 'strval', $explicit ) ) );

		if ( ! empty( $explicit ) ) {
			return $explicit;
		}

		if ( ! $this->option( 'all' ) ) {
			return [];
		}

		// Reach into the extractor to discover registered routes. We only need
		// the set of keys, so list them via the public `sourcesFor()` accessor
		// after pulling the configured registrations.
		$registered = (array) config( 'artisanpack.performance.css.critical.sources', [] );
		$routes     = array_keys( $registered );

		// Application code may also register at runtime via service providers;
		// pull from the extractor itself when it exposes the routes.
		if ( method_exists( $extractor, 'registeredRoutes' ) ) {
			$routes = array_merge( $routes, $extractor->registeredRoutes() );
		}

		return array_values( array_unique( array_map( 'strval', $routes ) ) );
	}
}
