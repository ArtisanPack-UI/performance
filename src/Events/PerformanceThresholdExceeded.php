<?php

/**
 * Performance threshold exceeded event.
 *
 * Dispatched when an aggregated metric value crosses a configured alert
 * threshold (e.g. LCP > 4000ms).
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
 * Performance threshold exceeded event class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class PerformanceThresholdExceeded
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * Creates a new event instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $metric    Metric name (e.g. `LCP`).
	 * @param float  $value     Observed value.
	 * @param float  $threshold Configured alert threshold.
	 */
	public function __construct(
		public string $metric,
		public float $value,
		public float $threshold,
	) {
	}
}
