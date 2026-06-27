<?php

declare( strict_types=1 );

beforeEach( function (): void {
    clearImageFixtures();
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'fails fast when the supplied path does not exist', function (): void {
    $this->artisan( 'perf:generate-webp', ['path' => '/no/such/path'] )
        ->expectsOutputToContain( 'Path does not exist' )
        ->assertFailed();
} );

it( 'converts a single image file at the configured quality', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source = makeTestImage( 'single.jpg', 100, 100 );

    $this->artisan( 'perf:generate-webp', ['path' => $source, '--quality' => 60] )
        ->expectsOutputToContain( 'Converted 1' )
        ->assertSuccessful();

    $destination = imageFixturesDir() . DIRECTORY_SEPARATOR . 'single.webp';
    expect( file_exists( $destination ) )->toBeTrue();
} );

it( 'converts every image in a directory non-recursively by default', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    makeTestImage( 'a.jpg' );
    makeTestImage( 'b.png', 80, 80, 'png' );

    // Nested file that should be ignored without --recursive.
    mkdir( imageFixturesDir() . '/nested', 0777, true );
    $nested = imageFixturesDir() . '/nested/c.jpg';
    copy( makeTestImage( 'c.jpg' ), $nested );

    $this->artisan( 'perf:generate-webp', ['path' => imageFixturesDir()] )
        ->expectsOutputToContain( 'Converted 3' )
        ->assertSuccessful();

    expect( file_exists( imageFixturesDir() . '/a.webp' ) )->toBeTrue()
        ->and( file_exists( imageFixturesDir() . '/b.webp' ) )->toBeTrue()
        ->and( file_exists( imageFixturesDir() . '/nested/c.webp' ) )->toBeFalse();
} );

it( 'recurses into subdirectories when --recursive is set', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    mkdir( imageFixturesDir() . '/deep', 0777, true );
    $top    = makeTestImage( 'top.jpg' );
    $nested = imageFixturesDir() . '/deep/nested.jpg';
    copy( $top, $nested );

    $this->artisan( 'perf:generate-webp', [
        'path'        => imageFixturesDir(),
        '--recursive' => true,
    ] )->assertSuccessful();

    expect( file_exists( imageFixturesDir() . '/deep/nested.webp' ) )->toBeTrue();
} );

it( 'skips files that already have a WebP sibling unless --force is set', function (): void {
    if ( ! function_exists( 'imagewebp' ) ) {
        $this->markTestSkipped( 'GD WebP support is not available' );
    }

    $source = makeTestImage( 'existing.jpg' );
    touch( imageFixturesDir() . '/existing.webp' );

    $this->artisan( 'perf:generate-webp', ['path' => $source] )
        ->expectsOutputToContain( 'skip:' )
        ->assertSuccessful();

    $this->artisan( 'perf:generate-webp', ['path' => $source, '--force' => true] )
        ->expectsOutputToContain( 'Converted 1' )
        ->assertSuccessful();
} );

it( 'returns success with a warning when no convertible files are found', function (): void {
    $this->artisan( 'perf:generate-webp', ['path' => imageFixturesDir()])
        ->expectsOutputToContain( 'No convertible images found')
        ->assertSuccessful();
});
