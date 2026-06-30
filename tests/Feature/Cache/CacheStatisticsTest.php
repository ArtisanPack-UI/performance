<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\CacheStatistics;
use ArtisanPackUI\Performance\Cache\FragmentCache;
use ArtisanPackUI\Performance\Cache\PageCacheManager;
use Illuminate\Support\Facades\Cache;

beforeEach( function (): void {
    config( [ 'cache.default' => 'array' ] );
    config( [
        'artisanpack.performance.page_cache' => [
            'enabled'         => true,
            'driver'          => 'array',
            'ttl'             => 3600,
            'exclude_routes'  => [],
            'exclude_when'    => [],
            'vary_by'         => [],
        ],
        'artisanpack.performance.fragment_cache' => [
            'enabled'     => true,
            'driver'      => 'array',
            'default_ttl' => 3600,
        ],
    ] );

    Cache::store( 'array' )->flush();
} );

it( 'returns zero counts when nothing has been cached yet', function (): void {
    $stats = app( CacheStatistics::class );

    expect( $stats->pageSummary() )->toMatchArray( [
        'entries'    => 0,
        'size_bytes' => null,
        'hit_rate'   => null,
    ] );

    expect( $stats->fragmentSummary() )->toMatchArray( [
        'entries'    => 0,
        'tags'       => 0,
        'size_bytes' => null,
        'hit_rate'   => null,
    ] );
} );

it( 'counts entries from the page cache pattern index', function (): void {
    Cache::store( 'array' )->put( PageCacheManager::INDEX_KEY, [
        'perf:page:home'  => 'home',
        'perf:page:about' => 'about',
    ], 600 );

    $stats = app( CacheStatistics::class );

    expect( $stats->pageSummary()['entries'] )->toBe( 2 );

    $entries = $stats->pageEntries();

    expect( $entries )->toHaveCount( 2 )
        ->and( $entries[0] )->toMatchArray( [
            'key'  => 'perf:page:home',
            'path' => 'home',
        ] );
} );

it( 'counts distinct fragment keys across tags', function (): void {
    $fragments = app( FragmentCache::class );

    $fragments->put( 'sidebar', 'rendered-sidebar', 60, [ 'home', 'shared' ] );
    $fragments->put( 'footer', 'rendered-footer', 60, [ 'shared' ] );

    $stats = app( CacheStatistics::class );

    expect( $stats->fragmentSummary()['entries'] )->toBe( 2 )
        ->and( $stats->fragmentSummary()['tags'] )->toBe( 2 );

    $tags = collect( $stats->fragmentTags() )->keyBy( 'tag' );

    expect( $tags->get( 'home' )['entry_count'] )->toBe( 1 )
        ->and( $tags->get( 'shared' )['entry_count'] )->toBe( 2 );
} );

it( 'limits the number of page entries returned', function (): void {
    $index = [];

    for ( $i = 0; $i < 75; $i++ ) {
        $index[ "perf:page:item-{$i}" ] = "items/{$i}";
    }

    Cache::store( 'array' )->put( PageCacheManager::INDEX_KEY, $index, 600 );

    $stats = app( CacheStatistics::class );

    expect( $stats->pageEntries( 10 ) )->toHaveCount( 10 );
} );
