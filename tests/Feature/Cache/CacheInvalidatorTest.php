<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\CacheInvalidator;
use ArtisanPackUI\Performance\Cache\FragmentCache;
use ArtisanPackUI\Performance\Cache\PageCacheManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

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
    config( [ 'artisanpack.performance.fragment_cache' => [
        'enabled'     => true,
        'driver'      => 'array',
        'default_ttl' => 3600,
    ] ] );

    Cache::store( 'array' )->flush();
} );

it( 'invalidates page cache entries via the invalidator', function (): void {
    $page        = new PageCacheManager;
    $fragment    = new FragmentCache;
    $invalidator = new CacheInvalidator( $page, $fragment );

    $page->cacheResponse(
        Request::create( '/products/42', 'GET' ),
        new Response( 'x', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    expect( $invalidator->invalidatePagePattern( 'products/*' ) )->toBe( 1 );
} );

it( 'invalidates fragment cache entries by tag', function (): void {
    $page        = new PageCacheManager;
    $fragment    = new FragmentCache;
    $invalidator = new CacheInvalidator( $page, $fragment );

    $fragment->put( 'k', 'v', 60, [ 'products' ] );

    expect( $invalidator->invalidateFragmentTag( 'products' ) )->toBe( 1 )
        ->and( $fragment->get( 'k' ) )->toBeNull();
} );

it( 'purges every page entry plus tagged fragments', function (): void {
    $page        = new PageCacheManager;
    $fragment    = new FragmentCache;
    $invalidator = new CacheInvalidator( $page, $fragment );

    $page->cacheResponse(
        Request::create( '/products', 'GET' ),
        new Response( 'x', 200, [ 'Content-Type' => 'text/html' ] ),
    );
    $fragment->put( 'sidebar', 'rendered', 60, [ 'sidebar' ] );

    $result = $invalidator->purgeAll();

    expect( $result['page'] )->toBe( 1 )
        ->and( $result['fragments'] )->toBe( 1 );
} );
