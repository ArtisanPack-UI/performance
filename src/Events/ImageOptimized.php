<?php

/**
 * Image optimized event.
 *
 * Dispatched after an image has been processed by the optimization pipeline
 * and every requested format/size derivative has been generated.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Image optimized event class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class ImageOptimized
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * Creates a new event instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $path    Absolute path to the source image.
	 * @param array<int, string> $formats Formats produced (e.g. `['webp', 'avif']`).
	 * @param array<int, int>    $sizes   Widths produced for responsive variants.
	 */
	public function __construct(
		public string $path,
		public array $formats,
		public array $sizes,
	) {
	}
}
