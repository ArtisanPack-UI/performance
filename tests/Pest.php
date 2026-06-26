<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend( Tests\TestCase::class )
	->in( 'Feature' );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend( 'toBeOne', function () {
	return $this->toBe( 1 );
} );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Image fixture helpers. Tests that need real image bytes (format conversion,
| dominant color, resize) call these instead of committing binary fixtures.
| `imageFixturesDir()` returns a per-test directory that is wiped in
| `beforeEach`/`afterEach` hooks defined by the consuming test files.
|
*/

function imageFixturesDir(): string
{
	$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'artisanpack-perf-fixtures';

	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true );
	}

	return $dir;
}

function makeTestImage(
	string $filename,
	int $width = 200,
	int $height = 120,
	string $format = 'jpeg',
	array $rgb = [ 220, 30, 60 ],
): string {
	$path  = imageFixturesDir() . DIRECTORY_SEPARATOR . $filename;
	$image = imagecreatetruecolor( $width, $height );

	$color = imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );
	imagefilledrectangle( $image, 0, 0, $width, $height, $color );

	switch ( strtolower( $format ) ) {
		case 'png':
			imagepng( $image, $path );
			break;
		case 'gif':
			imagegif( $image, $path );
			break;
		case 'jpeg':
		case 'jpg':
		default:
			imagejpeg( $image, $path, 90 );
			break;
	}

	imagedestroy( $image );

	return $path;
}

function clearImageFixtures(): void
{
	$dir = imageFixturesDir();

	if ( ! is_dir( $dir ) ) {
		return;
	}

	foreach ( scandir( $dir ) as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}

		$path = $dir . DIRECTORY_SEPARATOR . $entry;

		if ( is_dir( $path ) ) {
			foreach ( scandir( $path ) as $nested ) {
				if ( '.' === $nested || '..' === $nested ) {
					continue;
				}
				@unlink( $path . DIRECTORY_SEPARATOR . $nested );
			}
			@rmdir( $path );
			continue;
		}

		@unlink( $path );
	}
}
