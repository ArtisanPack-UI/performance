<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\PageCacheManager;
use ArtisanPackUI\Performance\Http\Middleware\PageCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

beforeEach( function (): void {
    config( [ 'cache.default' => 'array' ] );
    config( [ 'artisanpack.performance.features.page_cache' => true ] );
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
    app()->forgetInstance( PageCacheManager::class );
} );

function runPageCacheMiddleware( Request $request, SymfonyResponse $response ): SymfonyResponse
{
    $middleware = app( PageCache::class );

    return $middleware->handle( $request, static fn () => $response );
}

it( 'tags fresh responses with X-Page-Cache: MISS and caches them', function (): void {
    $request  = Request::create( '/products', 'GET' );
    $response = new Response( '<html>fresh</html>', 200, [ 'Content-Type' => 'text/html' ] );

    $result = runPageCacheMiddleware( $request, $response );

    expect( $result->headers->get( 'X-Page-Cache' ) )->toBe( 'MISS' );

    $second = runPageCacheMiddleware(
        Request::create( '/products', 'GET' ),
        new Response( '<html>different</html>', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    expect( $second->headers->get( 'X-Page-Cache' ) )->toBe( 'HIT' )
        ->and( $second->getContent() )->toBe( '<html>fresh</html>' );
} );

it( 'short-circuits when the feature is disabled', function (): void {
    config( [ 'artisanpack.performance.features.page_cache' => false ] );

    $result = runPageCacheMiddleware(
        Request::create( '/products', 'GET' ),
        new Response( '<html>x</html>', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    expect( $result->headers->has( 'X-Page-Cache' ) )->toBeFalse();
} );

it( 'does not cache POST requests', function (): void {
    $first = runPageCacheMiddleware(
        Request::create( '/products', 'POST' ),
        new Response( '<html>post</html>', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    expect( $first->headers->has( 'X-Page-Cache' ) )->toBeFalse();
} );

it( 'preserves Content-Type when returning a HIT', function (): void {
    runPageCacheMiddleware(
        Request::create( '/products', 'GET' ),
        new Response( '<html>x</html>', 200, [ 'Content-Type' => 'text/html; charset=UTF-8' ] ),
    );

    $second = runPageCacheMiddleware(
        Request::create( '/products', 'GET' ),
        new Response( '', 200 ),
    );

    expect( $second->headers->get( 'Content-Type' ) )->toContain( 'text/html' );
} );

it( 'does not cache excluded routes', function (): void {
    config( [ 'artisanpack.performance.page_cache.exclude_routes' => [ 'admin/*' ] ] );

    $result = runPageCacheMiddleware(
        Request::create( '/admin/dashboard', 'GET' ),
        new Response( '<html>admin</html>', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    expect( $result->headers->has( 'X-Page-Cache' ) )->toBeFalse();
} );
