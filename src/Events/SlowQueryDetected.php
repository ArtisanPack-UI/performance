<?php

/**
 * Slow query detected event.
 *
 * Dispatched when a database query exceeds the configured slow-query
 * threshold.
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
 * Slow query detected event class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class SlowQueryDetected
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * Creates a new event instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string                    $query    The SQL statement that ran.
	 * @param float                     $timeMs   The execution time in milliseconds.
	 * @param array<int, array<string>> $trace    Stack trace frames captured at execution.
	 * @param array<int, mixed>         $bindings Bindings passed to the query.
	 */
	public function __construct(
		public string $query,
		public float $timeMs,
		public array $trace = [],
		public array $bindings = [],
	) {
	}
}
