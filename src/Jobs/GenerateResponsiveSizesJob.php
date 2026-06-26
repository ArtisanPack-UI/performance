<?php

/**
 * Generate responsive sizes queued job.
 *
 * Generates resized variants of a source image via the
 * `ResponsiveImageGenerator`. Use to backfill responsive variants for an
 * existing image or as a chained step in the optimization pipeline.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Jobs;

use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate responsive sizes job class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class GenerateResponsiveSizesJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	/**
	 * Number of times the job may be attempted.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $tries;

	/**
	 * Seconds between retry attempts.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $backoff;

	/**
	 * Creates a new job instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string                  $path    Absolute path to the source image.
	 * @param array<int, int>|null    $sizes   Widths to generate (defaults to configured sizes).
	 * @param array<int, string>|null $formats Formats to convert variants to.
	 */
	public function __construct(
		public string $path,
		public ?array $sizes = null,
		public ?array $formats = null,
	) {
		$this->onQueue( (string) config( 'artisanpack.performance.images.queue', 'default' ) );
		$this->tries   = (int) config( 'artisanpack.performance.images.jobs.tries', 3 );
		$this->backoff = (int) config( 'artisanpack.performance.images.jobs.backoff', 30 );
	}

	/**
	 * Executes the job.
	 *
	 * @since 1.0.0
	 *
	 * @param ResponsiveImageGenerator $generator Responsive image generator resolved from the container.
	 *
	 * @return void
	 */
	public function handle( ResponsiveImageGenerator $generator ): void
	{
		$generator->generate( $this->path, $this->sizes, $this->formats );
	}
}
