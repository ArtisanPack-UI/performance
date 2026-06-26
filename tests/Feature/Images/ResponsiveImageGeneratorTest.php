<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use ArtisanPackUI\Performance\Services\ImageService;

beforeEach( function (): void {
	clearImageFixtures();
} );

afterEach( function (): void {
	clearImageFixtures();
} );

it( 'generates resized variants for every requested width', function (): void {
	$source    = makeTestImage( 'sizes.jpg', 800, 400 );
	$generator = new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) );

	$variants = $generator->generateSizes( $source, [ 200, 400 ] );

	expect( $variants )->toHaveCount( 2 )
		->and( $variants )->toHaveKey( 200 )
		->and( $variants )->toHaveKey( 400 );

	foreach ( $variants as $path ) {
		expect( file_exists( $path ) )->toBeTrue();
	}
} );

it( 'clamps requested widths to the source width to avoid upscaling', function (): void {
	$source    = makeTestImage( 'clamp.jpg', 400, 200 );
	$generator = new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) );

	$effective = $generator->effectiveSizes( $source, [ 200, 800, 1600 ] );

	expect( $effective )->toBe( [ 200, 400 ] );
} );

it( 'builds a srcset with widths in ascending order', function (): void {
	$source    = makeTestImage( 'srcset.jpg', 800, 400 );
	$generator = new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) );

	$srcset = $generator->generateSrcset( $source, [ 200, 400 ] );

	expect( $srcset )->toContain( '200w' )
		->and( $srcset )->toContain( '400w' )
		->and( substr_count( $srcset, ',' ) )->toBe( 1 );
} );

it( 'generates variants in every enabled format alongside the original', function (): void {
	if ( ! function_exists( 'imagewebp' ) ) {
		$this->markTestSkipped( 'GD WebP support is not available' );
	}

	config( [
		'artisanpack.performance.images.formats' => [
			'webp' => [ 'enabled' => true, 'quality' => 70 ],
			'avif' => [ 'enabled' => false, 'quality' => 60 ],
		],
	] );

	$source    = makeTestImage( 'multi.jpg', 600, 300 );
	$generator = new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) );

	$variants = $generator->generate( $source, [ 150, 300 ] );

	$formats = array_unique( array_column( $variants, 'format' ) );
	sort( $formats );

	expect( $formats )->toBe( [ 'jpg', 'webp' ] );

	foreach ( $variants as $variant ) {
		expect( file_exists( $variant['path'] ) )->toBeTrue();
	}
} );

it( 'skips unsupported formats rather than failing the generation pass', function (): void {
	$source = makeTestImage( 'skip.jpg', 200, 200 );

	$converter = new class( 'gd' ) extends FormatConverter {
		public function supports( string $format ): bool
		{
			return false;
		}
	};

	$generator = new ResponsiveImageGenerator( new ImageService( $converter ) );

	$variants = $generator->generate( $source, [ 100 ], [ 'webp' ] );

	$formats = array_unique( array_column( $variants, 'format' ) );

	expect( $formats )->toBe( [ 'jpg' ] );
} );

it( 'returns an empty srcset for an unsupported requested format', function (): void {
	$source = makeTestImage( 'unsupported.jpg', 200, 200 );

	$converter = new class( 'gd' ) extends FormatConverter {
		public function supports( string $format ): bool
		{
			return false;
		}
	};

	$generator = new ResponsiveImageGenerator( new ImageService( $converter ) );

	expect( $generator->generateSrcset( $source, [ 100 ], 'webp' ) )->toBe( '' );
} );

it( 'throws when the source image is unreadable', function (): void {
	$generator = new ResponsiveImageGenerator( new ImageService( new FormatConverter( 'gd' ) ) );

	expect( fn () => $generator->generate( '/no/such/file.jpg' ) )
		->toThrow( RuntimeException::class, 'Source image is not readable' );
} );
