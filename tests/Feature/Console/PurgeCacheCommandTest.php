<?php

declare( strict_types=1 );

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

it( 'purges the page cache by pattern', function (): void {
    $page = app( PageCacheManager::class );
    $page->cacheResponse(
        Request::create( '/products/42', 'GET' ),
        new Response( 'x', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    $this->artisan( 'perf:purge-cache', [ '--type' => 'page', '--pattern' => 'products/*' ] )
        ->expectsOutputToContain( 'Purged 1 page cache entries' )
        ->assertSuccessful();
} );

it( 'purges the fragment cache by tag', function (): void {
    $fragment = app( FragmentCache::class );
    $fragment->put( 'k', 'v', 60, [ 'tag-a' ] );

    $this->artisan( 'perf:purge-cache', [ '--type' => 'fragment', '--tag' => 'tag-a' ] )
        ->expectsOutputToContain( "tagged 'tag-a'" )
        ->assertSuccessful();
} );

it( 'requires a tag when purging fragment cache', function (): void {
    $this->artisan( 'perf:purge-cache', [ '--type' => 'fragment' ] )
        ->expectsOutputToContain( '--tag value is required' )
        ->assertFailed();
} );

it( 'rejects an unknown type', function (): void {
    $this->artisan( 'perf:purge-cache', [ '--type' => 'nonsense' ] )
        ->expectsOutputToContain( 'Unknown --type' )
        ->assertFailed();
} );

it( 'purges everything when --type=all', function (): void {
    $page = app( PageCacheManager::class );
    $page->cacheResponse(
        Request::create( '/products', 'GET' ),
        new Response( 'x', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    $this->artisan( 'perf:purge-cache', [ '--type' => 'all' ] )
        ->expectsOutputToContain( 'Purged 1 page entries' )
        ->assertSuccessful();
} );
