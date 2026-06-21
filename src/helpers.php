<?php

/**
 * Performance helper functions.
 *
 * Global helpers exposed by the Performance package. Mirrors the public
 * API on the Performance facade for use in templates and lightweight
 * application code where dependency injection is impractical.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */

use ArtisanPackUI\Performance\Services\PerformanceService;

if ( ! function_exists( 'performance' ) ) {
	/**
	 * Resolves the PerformanceService instance from the container.
	 *
	 * @since 1.0.0
	 *
	 * @return PerformanceService
	 */
	function performance(): PerformanceService
	{
		return app( 'performance' );
	}
}
