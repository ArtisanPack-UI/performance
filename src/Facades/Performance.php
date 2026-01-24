<?php

/**
 * Performance Facade.
 *
 * Provides static access to the Performance class.
 *
 * @since      1.0.0
 * @subpackage Performance
 *
 * @package    ArtisanPack_UI
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Performance Facade.
 *
 * @since      1.0.0
 * @see        \ArtisanPackUI\Performance\Performance
 *
 * @subpackage Performance
 *
 * @package    ArtisanPack_UI
 */
class Performance extends Facade
{
	/**
	 * Get the registered name of the component.
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
