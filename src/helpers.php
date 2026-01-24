<?php

/**
 * Performance helper functions.
 *
 * This file contains global helper functions for the package.
 * Add your custom helper functions below.
 *
 * @since      1.0.0
 * @subpackage Performance
 *
 * @package    ArtisanPack_UI
 */

use ArtisanPackUI\Performance\Performance;

if ( ! function_exists( 'performance' ) ) {
	/**
	 * Get the Performance instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Performance
	 */
	function performance(): Performance
	{
		return app( 'performance' );
	}
}

// Add your custom helper functions below
