<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Images\DominantColorExtractor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

beforeEach( function (): void {
    clearImageFixtures();
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'extracts a dominant color as a 7-character lowercase hex string', function (): void {
    $source    = makeTestImage( 'avg.jpg', 60, 60, 'jpeg', [200, 50, 100] );
    $extractor = new DominantColorExtractor( 'average', new Repository( new ArrayStore ) );

    $color = $extractor->extract( $source );

    expect( $color )->toMatch( '/^#[0-9a-f]{6}$/' );
} );

it( 'falls back to the configured algorithm when the caller passes null', function (): void {
    config( ['artisanpack.performance.images.dominant_color.algorithm' => 'quantize'] );

    $source    = makeTestImage( 'config-algo.jpg', 60, 60, 'jpeg', [30, 220, 60] );
    $extractor = new DominantColorExtractor( null, new Repository( new ArrayStore ) );

    expect( $extractor->algorithm() )->toBe( 'quantize' );
    expect( $extractor->extract( $source ) )->toMatch( '/^#[0-9a-f]{6}$/' );
} );

it( 'supports both average and quantize algorithms', function ( string $algorithm ): void {
    $source    = makeTestImage( "alg-{$algorithm}.jpg", 80, 80, 'jpeg', [100, 150, 200] );
    $extractor = new DominantColorExtractor( $algorithm, new Repository( new ArrayStore ) );

    expect( $extractor->extract( $source ) )->toMatch( '/^#[0-9a-f]{6}$/' );
} )->with( ['average', 'quantize'] );

it( 'caches the extracted color between calls so the image is sampled once', function (): void {
    $source    = makeTestImage( 'cached.jpg', 40, 40, 'jpeg', [90, 90, 90] );
    $cache     = new Repository( new ArrayStore );
    $extractor = new DominantColorExtractor( 'average', $cache );

    $first = $extractor->extract( $source );

    // Locate the cached key by file fingerprint and overwrite with a sentinel.
    // The next extract() call must read the sentinel rather than re-sample.
    $expectedKey = sprintf(
        'performance:dominant_color:average:%s:%d',
        md5( $source ),
        filemtime( $source ),
    );

    expect( $cache->get( $expectedKey ) )->toBe( $first );

    $cache->forever( $expectedKey, '#abcdef' );

    expect( $extractor->extract( $source ) )->toBe( '#abcdef' );
} );

it( 'skips the cache when `$useCache` is false', function (): void {
    $source    = makeTestImage( 'no-cache.jpg', 40, 40, 'jpeg', [10, 20, 30] );
    $cache     = new Repository( new ArrayStore );
    $extractor = new DominantColorExtractor( 'average', $cache );

    $extractor->extract( $source, null, false );

    $expectedKey = sprintf(
        'performance:dominant_color:average:%s:%d',
        md5( $source ),
        filemtime( $source ),
    );

    expect( $cache->get( $expectedKey ) )->toBeNull();
} );

it( 'throws when the source image is missing', function (): void {
    $extractor = new DominantColorExtractor( 'average', new Repository( new ArrayStore ) );

    expect( fn () => $extractor->extract( '/no/such/file.jpg' ) )
        ->toThrow( RuntimeException::class, 'Source image is not readable' );
} );

it( 'throws for unknown algorithms', function (): void {
    $source    = makeTestImage( 'bad-algo.jpg' );
    $extractor = new DominantColorExtractor( 'bogus', new Repository( new ArrayStore ) );

    expect( fn () => $extractor->extract( $source ) )
        ->toThrow( RuntimeException::class, 'Unknown dominant color algorithm' );
} );

it( 'returns a white fallback when every sample is transparent', function (): void {
    // Pin the GD driver — Imagick's mergeImageLayers flatten and GD's
    // "skip alpha then default to white" both happen to return `#ffffff`,
    // so without pinning a driver the test would assert a coincidence
    // rather than the documented GD branch behavior.
    config( ['artisanpack.performance.images.driver' => 'gd'] );

    $source = imageFixturesDir() . DIRECTORY_SEPARATOR . 'transparent.png';

    $image       = imagecreatetruecolor( 20, 20 );
    $transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
    imagealphablending( $image, false );
    imagesavealpha( $image, true );
    imagefilledrectangle( $image, 0, 0, 20, 20, $transparent);
    imagepng( $image, $source);
    imagedestroy( $image);

    $extractor = new DominantColorExtractor( 'average', new Repository( new ArrayStore));

    expect( $extractor->extract( $source))->toBe( '#ffffff');
});
