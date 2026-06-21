<?php

/**
 * Cache purged event.
 *
 * Dispatched after one or more cache entries are invalidated.
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
 * Cache purged event class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class CachePurged
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * Creates a new event instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $keys   Cache keys that were purged.
	 * @param string             $reason Free-form reason describing the purge.
	 */
	public function __construct(
		public array $keys,
		public string $reason = '',
	) {
	}
}
