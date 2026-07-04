<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\PageCacheManager;
use ArtisanPackUI\Performance\Events\CachePurged;
use ArtisanPackUI\Performance\Events\CacheWarmed;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    config( [ 'cache.default' => 'array' ] );
    config( [ 'artisanpack.performance.page_cache' => [
        'enabled'             => true,
        'driver'              => 'array',
        'ttl'                 => 3600,
        'exclude_routes'      => [],
        'exclude_when'        => [],
        'vary_by'             => [],
        'cache_query_strings' => false,
    ] ] );

    Cache::store( 'array' )->flush();
} );

function htmlResponse( string $body = '<html><body>x</body></html>' ): Response
{
    return new Response( $body, 200, [ 'Content-Type' => 'text/html' ] );
}

it( 'caches and retrieves a response for the request', function (): void {
    $manager = new PageCacheManager;
    $request = Request::create( '/products', 'GET' );

    $manager->cacheResponse( $request, htmlResponse( 'hello' ) );

    $cached = $manager->getCachedResponse( $request );

    expect( $cached )->toBeArray()
        ->and( $cached['content'] )->toBe( 'hello' )
        ->and( $cached['status'] )->toBe( 200 );
} );

it( 'skips caching for POST requests', function (): void {
    $manager = new PageCacheManager;
    $request = Request::create( '/products', 'POST' );

    $manager->cacheResponse( $request, htmlResponse() );

    expect( $manager->getCachedResponse( $request ) )->toBeNull();
} );

it( 'skips caching for excluded routes', function (): void {
    config( [ 'artisanpack.performance.page_cache.exclude_routes' => [ 'admin/*' ] ] );
    $manager = new PageCacheManager;

    $manager->cacheResponse( Request::create( '/admin/users', 'GET' ), htmlResponse() );

    expect( $manager->getCachedResponse( Request::create( '/admin/users', 'GET' ) ) )->toBeNull();
} );

it( 'skips caching for 5xx responses', function (): void {
    $manager = new PageCacheManager;

    $manager->cacheResponse(
        Request::create( '/products', 'GET' ),
        new Response( '<html>nope</html>', 500, [ 'Content-Type' => 'text/html' ] ),
    );

    expect( $manager->getCachedResponse( Request::create( '/products', 'GET' ) ) )->toBeNull();
} );

it( 'skips caching for non-HTML content types', function (): void {
    $manager = new PageCacheManager;

    $manager->cacheResponse(
        Request::create( '/products', 'GET' ),
        new Response( 'binary', 200, [ 'Content-Type' => 'application/octet-stream' ] ),
    );

    expect( $manager->getCachedResponse( Request::create( '/products', 'GET' ) ) )->toBeNull();
} );

it( 'skips caching for application/json responses to avoid cross-user leakage', function (): void {
    // The default cache key omits query strings, so /api/me?user=1 and
    // ?user=2 would collide and one user would see the other's data.
    // Restricting page cache to HTML-only sidesteps that whole class of bug.
    $manager = new PageCacheManager;

    $manager->cacheResponse(
        Request::create( '/api/me', 'GET' ),
        new Response( '{"user":1}', 200, [ 'Content-Type' => 'application/json' ] ),
    );

    expect( $manager->getCachedResponse( Request::create( '/api/me', 'GET' ) ) )->toBeNull();
} );

it( 'skips caching when Cache-Control: no-store is set', function (): void {
    $manager = new PageCacheManager;

    $manager->cacheResponse(
        Request::create( '/dashboard', 'GET' ),
        new Response( '<html>x</html>', 200, [
            'Content-Type'  => 'text/html',
            'Cache-Control' => 'no-store, private',
        ] ),
    );

    expect( $manager->getCachedResponse( Request::create( '/dashboard', 'GET' ) ) )->toBeNull();
} );

it( 'still caches the default Laravel Response (Cache-Control: no-cache, private)', function (): void {
    // Symfony's default Cache-Control header is `no-cache, private`. If we
    // treated that as an opt-out, no plain Laravel response would ever be
    // cached. Regression guard for the over-aggressive Cache-Control check
    // that the initial fix introduced.
    $manager  = new PageCacheManager;
    $response = new Response( '<html>ok</html>', 200, [ 'Content-Type' => 'text/html' ] );

    $manager->cacheResponse( Request::create( '/products', 'GET' ), $response );

    expect( $manager->getCachedResponse( Request::create( '/products', 'GET' ) ) )->not->toBeNull();
} );

it( 'skips caching empty 200 responses', function (): void {
    // An empty body should not poison the cache as a permanent blank page.
    $manager = new PageCacheManager;

    $manager->cacheResponse(
        Request::create( '/products', 'GET' ),
        new Response( '', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    expect( $manager->getCachedResponse( Request::create( '/products', 'GET' ) ) )->toBeNull();
} );

it( 'preserves multi-value Link headers on round trip', function (): void {
    // The HeaderBag stores multi-value headers as a list; using ->get()
    // would only return the first. The cached payload must preserve every
    // entry so preload/preconnect chains survive a HIT.
    $manager  = new PageCacheManager;
    $response = new Response( '<html>x</html>', 200, [ 'Content-Type' => 'text/html' ] );
    $response->headers->set( 'Link', '</a.css>; rel=preload', false );
    $response->headers->set( 'Link', '</b.js>; rel=preload', false );

    $manager->cacheResponse( Request::create( '/products', 'GET' ), $response );

    $payload = $manager->getCachedResponse( Request::create( '/products', 'GET' ) );

    expect( $payload['headers']['link'] ?? [] )->toHaveCount( 2 )
        ->and( $payload['headers']['link'][0] )->toContain( '/a.css' )
        ->and( $payload['headers']['link'][1] )->toContain( '/b.js' );
} );

it( 'varies cache by configured headers', function (): void {
    config( [ 'artisanpack.performance.page_cache.vary_by' => [ 'Accept-Encoding' ] ] );
    $manager = new PageCacheManager;

    $gzip = Request::create( '/products', 'GET' );
    $gzip->headers->set( 'Accept-Encoding', 'gzip' );

    $br = Request::create( '/products', 'GET' );
    $br->headers->set( 'Accept-Encoding', 'br' );

    $manager->cacheResponse( $gzip, htmlResponse( 'gzip-body' ) );
    $manager->cacheResponse( $br, htmlResponse( 'br-body' ) );

    expect( $manager->getCachedResponse( $gzip )['content'] )->toBe( 'gzip-body' )
        ->and( $manager->getCachedResponse( $br )['content'] )->toBe( 'br-body' );
} );

it( 'invalidates pages matching a pattern', function (): void {
    Event::fake();
    $manager = new PageCacheManager;

    $manager->cacheResponse( Request::create( '/products', 'GET' ), htmlResponse( 'index' ) );
    $manager->cacheResponse( Request::create( '/products/42', 'GET' ), htmlResponse( 'show' ) );
    $manager->cacheResponse( Request::create( '/about', 'GET' ), htmlResponse( 'about' ) );

    $purged = $manager->invalidatePageCache( 'products*' );

    expect( $purged )->toBe( 2 )
        ->and( $manager->getCachedResponse( Request::create( '/products', 'GET' ) ) )->toBeNull()
        ->and( $manager->getCachedResponse( Request::create( '/products/42', 'GET' ) ) )->toBeNull()
        ->and( $manager->getCachedResponse( Request::create( '/about', 'GET' ) ) )->not->toBeNull();

    Event::assertDispatched( CachePurged::class );
} );

it( 'flushes only the page cache entries it wrote', function (): void {
    $manager = new PageCacheManager;

    Cache::store( 'array' )->put( 'unrelated', 'app-data', 60 );
    $manager->cacheResponse( Request::create( '/products', 'GET' ), htmlResponse() );
    $manager->cacheResponse( Request::create( '/about', 'GET' ), htmlResponse() );

    $count = $manager->flushPageCache();

    expect( $count )->toBe( 2 )
        ->and( Cache::store( 'array' )->get( 'unrelated' ) )->toBe( 'app-data' )
        ->and( $manager->getCachedResponse( Request::create( '/products', 'GET' ) ) )->toBeNull();
} );

it( 'warms the page cache and dispatches CacheWarmed', function (): void {
    Event::fake();
    config( [ 'app.url' => 'https://example.test' ] );

    Http::fake( [
        'https://example.test/products' => Http::response( 'ok', 200 ),
        'https://example.test/about'    => Http::response( 'nope', 500 ),
    ] );

    $results = ( new PageCacheManager )->warmPageCache( [ '/products', '/about' ] );

    expect( $results['/products']['ok'] )->toBeTrue()
        ->and( $results['/about']['ok'] )->toBeFalse();

    Event::assertDispatched( CacheWarmed::class );
} );

it( 'skips caching when an authenticated rule applies', function (): void {
    config( [ 'artisanpack.performance.page_cache.exclude_when' => [ 'authenticated' ] ] );
    $manager = new PageCacheManager;

    $request = Request::create( '/products', 'GET' );
    $request->setUserResolver( static fn () => (object) [ 'id' => 1 ] );

    $manager->cacheResponse( $request, htmlResponse() );

    expect( $manager->getCachedResponse( $request ) )->toBeNull();
} );

it( 'still caches when the session has empty _flash buckets', function (): void {
    // Laravel's session middleware seeds `_flash.new` and `_flash.old` as
    // empty arrays on every request — those empty buckets MUST NOT trip
    // the has_flash exclusion. Regression guard for the browser-only false
    // positive that was bypassing the cache on every real request.
    config( [ 'artisanpack.performance.page_cache.exclude_when' => [ 'has_flash' ] ] );

    $session = new Illuminate\Session\Store( 'test', new Illuminate\Session\ArraySessionHandler( 60 ) );
    $session->put( '_flash.new', [] );
    $session->put( '_flash.old', [] );

    $request = Request::create( '/products', 'GET' );
    $request->setLaravelSession( $session );

    $manager = new PageCacheManager;
    $manager->cacheResponse( $request, htmlResponse( 'should-be-cached' ) );

    expect( $manager->getCachedResponse( $request )['content'] )->toBe( 'should-be-cached' );
} );

it( 'skips caching when the session has populated _flash data', function (): void {
    config( [ 'artisanpack.performance.page_cache.exclude_when' => [ 'has_flash' ] ] );

    $session = new Illuminate\Session\Store( 'test', new Illuminate\Session\ArraySessionHandler( 60 ) );
    $session->put( '_flash.new', [] );
    $session->put( '_flash.old', [ 'success' ] );
    $session->put( 'success', 'Saved!' );

    $request = Request::create( '/products', 'GET' );
    $request->setLaravelSession( $session );

    $manager = new PageCacheManager;
    $manager->cacheResponse( $request, htmlResponse() );

    expect( $manager->getCachedResponse( $request ) )->toBeNull();
} );
