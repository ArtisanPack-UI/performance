<?php

/**
 * Performance facade.
 *
 * Static accessor for the PerformanceService. Resolves the `performance`
 * container binding.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 * @see \ArtisanPackUI\Performance\Services\PerformanceService
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Performance facade.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class Performance extends Facade
{
	/**
	 * Returns the container binding name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor(): string
	{
		return 'performance';
	}
}
