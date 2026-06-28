<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    config( [ 'app.url' => 'https://example.test' ] );
    config( [ 'artisanpack.performance.cache_warming' => [
        'enabled'             => true,
        'routes'              => [],
        'urls'                => [],
        'concurrent_requests' => 1,
        'delay_ms'            => 0,
    ] ] );
} );

it( 'warms URLs supplied via --urls', function (): void {
    Http::fake( [
        'https://example.test/products' => Http::response( 'ok', 200 ),
    ] );

    $this->artisan( 'perf:warm-cache', [ '--urls' => '/products' ] )
        ->expectsOutputToContain( 'Warmed 1 URLs' )
        ->assertSuccessful();
} );

it( 'skips unknown --routes with a warning instead of failing', function (): void {
    Http::fake( fn () => Http::response( 'ok', 200 ) );

    // Route name doesn't exist — the command should warn about the
    // unknown name, drop it from the warmable list, and fall through
    // to "No URLs to warm" without failing.
    $this->artisan( 'perf:warm-cache', [ '--routes' => 'no.such.route' ] )
        ->expectsOutputToContain( 'Skipping unknown route name: no.such.route' )
        ->assertSuccessful();
} );

it( 'reports failures and exits non-zero when any URL fails', function (): void {
    Http::fake( [
        'https://example.test/broken' => Http::response( 'nope', 500 ),
    ] );

    $this->artisan( 'perf:warm-cache', [ '--urls' => '/broken' ] )
        ->expectsOutputToContain( '0 URLs, 1 failed' )
        ->assertFailed();
} );

it( 'warns when no URLs are supplied', function (): void {
    $this->artisan( 'perf:warm-cache' )
        ->expectsOutputToContain( 'No URLs to warm' )
        ->assertSuccessful();
} );

it( 'extracts URLs from a sitemap.xml file', function (): void {
    $tempFile = tempnam( sys_get_temp_dir(), 'sitemap' );
    file_put_contents( $tempFile, <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset>
    <url><loc>https://example.test/a</loc></url>
    <url><loc>https://example.test/b</loc></url>
</urlset>
XML );

    Http::fake( [ '*' => Http::response( 'ok', 200 ) ] );

    $this->artisan( 'perf:warm-cache', [ '--sitemap' => $tempFile ] )
        ->expectsOutputToContain( 'Warmed 2 URLs' )
        ->assertSuccessful();

    @unlink( $tempFile );
} );

it( 'rejects an unsupported --type', function (): void {
    $this->artisan( 'perf:warm-cache', [ '--type' => 'nope' ] )
        ->expectsOutputToContain( 'Unsupported --type' )
        ->assertFailed();
} );

it( 'decodes XML entities and unwraps CDATA when extracting sitemap URLs', function (): void {
    $tempFile = tempnam( sys_get_temp_dir(), 'sitemap' );
    file_put_contents( $tempFile, <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset>
    <url><loc>https://example.test/p?a=1&amp;b=2</loc></url>
    <url><loc><![CDATA[https://example.test/cdata]]></loc></url>
</urlset>
XML );

    // Capture each warmed URL so we can assert decoding happened. Without
    // the html_entity_decode / CDATA strip, the first URL would arrive as
    // 'https://example.test/p?a=1&amp;b=2' and the second as
    // '<![CDATA[https://example.test/cdata]]>'.
    $hit = [];
    Http::fake( function ( $request ) use ( &$hit ) {
        $hit[] = (string) $request->url();

        return Http::response( 'ok', 200 );
    } );

    $this->artisan( 'perf:warm-cache', [ '--sitemap' => $tempFile ] )
        ->assertSuccessful();

    expect( $hit )->toContain( 'https://example.test/p?a=1&b=2' )
        ->and( $hit )->toContain( 'https://example.test/cdata' );

    @unlink( $tempFile );
} );

it( 'lets --delay=0 override a configured non-zero delay', function (): void {
    // Regression: `(int) $opt ?: config(...)` treated 0 as falsy and
    // silently fell through to the config value, so operators couldn't
    // disable pacing for a fast local-warm.
    config( [ 'artisanpack.performance.cache_warming.delay_ms' => 500 ] );
    Http::fake( fn () => Http::response( 'ok', 200 ) );

    $start = hrtime( true );
    $this->artisan( 'perf:warm-cache', [ '--urls' => '/a,/b,/c', '--delay' => 0 ] )
        ->assertSuccessful();
    $elapsedMs = ( hrtime( true ) - $start ) / 1_000_000;

    // With the configured 500ms delay applied between 3 URLs we'd expect
    // ≥1000ms. With --delay=0 honored, total should land well under that.
    expect( $elapsedMs )->toBeLessThan( 800.0 );
} );

it( 'dispatches a single CacheWarmed event regardless of URL count', function (): void {
    Illuminate\Support\Facades\Event::fake();
    Http::fake( fn () => Http::response( 'ok', 200 ) );

    $this->artisan( 'perf:warm-cache', [ '--urls' => '/a,/b,/c' ] )
        ->assertSuccessful();

    Illuminate\Support\Facades\Event::assertDispatchedTimes(
        ArtisanPackUI\Performance\Events\CacheWarmed::class,
        1,
    );
} );
