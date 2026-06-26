<?php

/**
 * `perf:generate-webp` artisan command.
 *
 * Batch-converts every supported image under the given path to WebP using
 * the package's `FormatConverter`. Honors the configured quality, supports
 * recursive directory traversal, and prints a per-file outcome summary.
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

use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Generate WebP derivatives.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class GenerateWebPCommand extends Command
{
	/**
	 * Source mime types that can be converted to WebP.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected const CONVERTIBLE_EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'gif', 'bmp' ];

	/**
	 * The console command signature.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $signature = 'perf:generate-webp
		{path : Path to a file or directory of images}
		{--quality= : Output quality (0-100), defaults to the configured format quality}
		{--recursive : Recurse into subdirectories when converting a directory}
		{--force : Overwrite existing WebP files}';

	/**
	 * The console command description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'Convert images at the given path to WebP using the configured driver.';

	/**
	 * Executes the command.
	 *
	 * @since 1.0.0
	 *
	 * @param FormatConverter $converter Format converter service.
	 *
	 * @return int Exit code (`Command::SUCCESS` or `Command::FAILURE`).
	 */
	public function handle( FormatConverter $converter ): int
	{
		$path = (string) $this->argument( 'path' );

		if ( ! file_exists( $path ) ) {
			$this->error( "Path does not exist: {$path}" );

			return self::FAILURE;
		}

		if ( ! $converter->supports( 'webp' ) ) {
			$this->error( "Driver '{$converter->driver()}' cannot encode WebP on this system." );

			return self::FAILURE;
		}

		$quality = $this->resolveQuality();
		$files   = $this->collectFiles( $path, (bool) $this->option( 'recursive' ) );

		if ( empty( $files ) ) {
			$this->warn( 'No convertible images found.' );

			return self::SUCCESS;
		}

		$converted = 0;
		$skipped   = 0;
		$failed    = 0;

		foreach ( $files as $file ) {
			$destination = $this->targetPath( $file );

			if ( file_exists( $destination ) && ! $this->option( 'force' ) ) {
				$this->line( "skip: {$file}" );
				$skipped++;
				continue;
			}

			try {
				$converter->toWebp( $file, $quality );
				$this->line( "ok:   {$file}" );
				$converted++;
			} catch ( Throwable $exception ) {
				$this->error( "fail: {$file} ({$exception->getMessage()})" );
				$failed++;
			}
		}

		$this->info( "Converted {$converted}, skipped {$skipped}, failed {$failed}." );

		return $failed > 0 ? self::FAILURE : self::SUCCESS;
	}

	/**
	 * Resolves the quality value from the option or config.
	 *
	 * @since 1.0.0
	 *
	 * @return int Quality clamped between 0 and 100.
	 */
	protected function resolveQuality(): int
	{
		$quality = $this->option( 'quality' );

		if ( null === $quality || '' === $quality ) {
			$quality = config(
				'artisanpack.performance.images.formats.webp.quality',
				FormatConverter::DEFAULT_WEBP_QUALITY,
			);
		}

		return max( 0, min( 100, (int) $quality ) );
	}

	/**
	 * Collects convertible image files at the given path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path      Source path (file or directory).
	 * @param bool   $recursive Whether to recurse into subdirectories.
	 *
	 * @throws RuntimeException When the directory cannot be opened.
	 *
	 * @return array<int, string> Absolute paths to convertible image files.
	 */
	protected function collectFiles( string $path, bool $recursive ): array
	{
		if ( is_file( $path ) ) {
			return $this->isConvertible( new SplFileInfo( $path ) ) ? [ $path ] : [];
		}

		$files = [];

		if ( $recursive ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
			);
		} else {
			$iterator = new FilesystemIterator( $path, FilesystemIterator::SKIP_DOTS );
		}

		foreach ( $iterator as $file ) {
			if ( $this->isConvertible( $file ) ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Reports whether the given file is a convertible image.
	 *
	 * @since 1.0.0
	 *
	 * @param SplFileInfo $file File handle to inspect.
	 *
	 * @return bool True when the file is a regular image with a convertible extension.
	 */
	protected function isConvertible( SplFileInfo $file ): bool
	{
		if ( ! $file->isFile() ) {
			return false;
		}

		return in_array( strtolower( $file->getExtension() ), self::CONVERTIBLE_EXTENSIONS, true );
	}

	/**
	 * Builds the WebP destination path for the given source file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute source path.
	 *
	 * @return string Absolute WebP destination path.
	 */
	protected function targetPath( string $path ): string
	{
		$directory = dirname( $path );
		$basename  = pathinfo( $path, PATHINFO_FILENAME );

		return $directory . DIRECTORY_SEPARATOR . $basename . '.webp';
	}
}
