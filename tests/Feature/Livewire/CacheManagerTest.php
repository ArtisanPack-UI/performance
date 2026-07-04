<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\FragmentCache;
use ArtisanPackUI\Performance\Cache\PageCacheManager;
use ArtisanPackUI\Performance\Livewire\CacheManager;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach( function (): void {
    config( [ 'cache.default' => 'array' ] );
    config( [
        'artisanpack.performance.page_cache' => [
            'enabled'        => true,
            'driver'         => 'array',
            'ttl'            => 3600,
            'exclude_routes' => [],
            'exclude_when'   => [],
            'vary_by'        => [],
        ],
        'artisanpack.performance.fragment_cache' => [
            'enabled'     => true,
            'driver'      => 'array',
            'default_ttl' => 3600,
        ],
        'artisanpack.performance.cache_warming' => [
            'enabled' => true,
            'urls'    => [],
        ],
    ] );

    Cache::store( 'array' )->flush();
} );

it( 'renders without errors', function (): void {
    Livewire::test( CacheManager::class )
        ->assertOk()
        ->assertSee( 'Page Cache' )
        ->assertSee( 'Fragment Cache' );
} );

it( 'stages a flush confirmation before executing it', function (): void {
    Livewire::test( CacheManager::class )
        ->call( 'requestConfirmation', 'flush' )
        ->assertSet( 'pendingAction', 'flush' );
} );

it( 'cancels a pending confirmation', function (): void {
    Livewire::test( CacheManager::class )
        ->call( 'requestConfirmation', 'flush' )
        ->call( 'cancelConfirmation' )
        ->assertSet( 'pendingAction', null );
} );

it( 'invalidates a fragment cache tag and reports the count', function (): void {
    $fragments = app( FragmentCache::class );
    $fragments->put( 'sidebar', 'rendered', 60, [ 'home' ] );

    Livewire::test( CacheManager::class )
        ->call( 'invalidateByTag', 'home' )
        ->assertSet( 'pendingAction', null )
        ->assertSet( 'statusIsError', false )
        ->assertSee( '1 fragments invalidated for tag "home"' );
} );

it( 'rejects an empty tag name', function (): void {
    Livewire::test( CacheManager::class )
        ->call( 'invalidateByTag', '' )
        ->assertSet( 'statusIsError', true );
} );

it( 'rejects an empty cache key', function (): void {
    Livewire::test( CacheManager::class )
        ->call( 'invalidate', '   ' )
        ->assertSet( 'statusIsError', true );
} );

it( 'flushes both caches and clears the pending action', function (): void {
    Cache::store( 'array' )->put( PageCacheManager::INDEX_KEY, [
        'perf:page:home' => 'home',
    ], 600 );

    Livewire::test( CacheManager::class )
        ->call( 'flushAll' )
        ->assertSet( 'pendingAction', null )
        ->assertSet( 'statusIsError', false );
} );

it( 'reports an error when warmCache has no configured URLs', function (): void {
    Livewire::test( CacheManager::class )
        ->call( 'warmCache' )
        ->assertSet( 'statusIsError', true )
        ->assertSee( 'No cache_warming.urls configured.' );
} );

it( 'merges host-supplied labels over defaults', function (): void {
    $component = Livewire::test( CacheManager::class, [
        'labels' => [
            'purge' => 'Clear Cache',
            'warm'  => 'Pre-load Cache',
        ],
    ] );

    $component->assertSee( 'Clear Cache' )
        ->assertSee( 'Pre-load Cache' );
} );

it( 'stages a key invalidation confirmation from the bound input value', function (): void {
    Livewire::test( CacheManager::class )
        ->set( 'invalidateKeyInput', 'products/*' )
        ->call( 'requestKeyInvalidation' )
        ->assertSet( 'pendingAction', 'key:products/*' );
} );

it( 'rejects a blank invalidate-by-key input with an error status', function (): void {
    Livewire::test( CacheManager::class )
        ->set( 'invalidateKeyInput', '   ' )
        ->call( 'requestKeyInvalidation' )
        ->assertSet( 'statusIsError', true )
        ->assertSet( 'pendingAction', null );
} );

it( 'invalidates a page entry by its index, not its raw path', function (): void {
    Cache::store( 'array' )->put( PageCacheManager::INDEX_KEY, [
        "perf:page:o'brien" => "o'brien",
        'perf:page:about'   => 'about',
    ], 600 );

    Livewire::test( CacheManager::class )
        ->call( 'invalidateEntry', 0 )
        ->assertSet( 'statusIsError', false )
        ->assertSee( '1 entries invalidated' );
} );

it( 'invalidates a fragment tag by its index, not its raw value', function (): void {
    $fragments = app( FragmentCache::class );
    $fragments->put( 'sidebar', 'rendered', 60, [ "user's-feed" ] );

    Livewire::test( CacheManager::class )
        ->call( 'invalidateFragmentTagByIndex', 0 )
        ->assertSet( 'statusIsError', false );
} );

it( 'returns an error when invalidating a non-existent entry index', function (): void {
    Livewire::test( CacheManager::class )
        ->call( 'invalidateEntry', 99 )
        ->assertSet( 'statusIsError', true );
} );

it( 'no longer exposes the FragmentCache service through a public method', function (): void {
    expect( method_exists( CacheManager::class, 'fragmentCache' ) )->toBeFalse();
} );
