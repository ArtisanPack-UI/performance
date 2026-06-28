<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\FragmentCache;
use ArtisanPackUI\Performance\Events\CachePurged;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach( function (): void {
    config( [ 'cache.default' => 'array' ] );
    config( [ 'artisanpack.performance.fragment_cache' => [
        'enabled'     => true,
        'driver'      => 'array',
        'default_ttl' => 3600,
    ] ] );

    Cache::store( 'array' )->flush();
} );

it( 'remembers a computed value across calls', function (): void {
    $cache = new FragmentCache;
    $calls = 0;

    $first  = $cache->remember( 'sidebar', 60, function () use ( &$calls ): string {
        $calls++;

        return 'rendered';
    } );
    $second = $cache->remember( 'sidebar', 60, function () use ( &$calls ): string {
        $calls++;

        return 'rendered-2';
    } );

    expect( $first )->toBe( 'rendered' )
        ->and( $second )->toBe( 'rendered' )
        ->and( $calls )->toBe( 1 );
} );

it( 'invalidates entries by tag', function (): void {
    Event::fake();
    $cache = new FragmentCache;

    $cache->remember( 'product-1', 60, fn () => 'p1', [ 'products' ] );
    $cache->remember( 'product-2', 60, fn () => 'p2', [ 'products' ] );
    $cache->remember( 'homepage', 60, fn () => 'home', [ 'homepage' ] );

    $count = $cache->invalidateByTag( 'products' );

    expect( $count )->toBe( 2 )
        ->and( $cache->get( 'product-1' ) )->toBeNull()
        ->and( $cache->get( 'product-2' ) )->toBeNull()
        ->and( $cache->get( 'homepage' ) )->toBe( 'home' );

    Event::assertDispatched( CachePurged::class );
} );

it( 'falls back to default TTL when zero is supplied', function (): void {
    config( [ 'artisanpack.performance.fragment_cache.default_ttl' => 5 ] );

    $cache = new FragmentCache;
    $cache->put( 'key', 'value', 0 );

    expect( $cache->get( 'key' ) )->toBe( 'value' );
} );

it( 'forgets a single key and updates the tag index', function (): void {
    $cache = new FragmentCache;
    $cache->put( 'k', 'v', 60, [ 'group' ] );

    expect( $cache->keysForTag( 'group' ) )->toContain( $cache->qualifyKey( 'k' ) );

    $cache->forget( 'k' );

    expect( $cache->get( 'k' ) )->toBeNull()
        ->and( $cache->keysForTag( 'group' ) )->toBe( [] );
} );

it( 'returns 0 when invalidating an unknown tag', function (): void {
    $cache = new FragmentCache;

    expect( $cache->invalidateByTag( 'nope' ) )->toBe( 0 );
} );

it( 'handles nested @cache directives without clobbering outer scope', function (): void {
    // Without the per-render stack, the inner @endcache's variable cleanup
    // either fatal-errored the outer @endcache (inner-MISS) or wrote the
    // outer's HTML under the inner's key (inner-HIT). This regression test
    // exercises an inner-MISS → outer-MISS render and verifies both keys
    // hold the intended payload.
    $output = Blade::render(
        '@cache(\'outer-key\', 60, [\'outer\']) outer-start @cache(\'inner-key\', 60, [\'inner\']) inner-body @endcache outer-end @endcache',
    );

    $cache = app( FragmentCache::class );

    expect( $output )->toContain( 'outer-start' )
        ->and( $output )->toContain( 'inner-body' )
        ->and( $output )->toContain( 'outer-end' );

    // The inner cache holds JUST the inner content; the outer cache holds
    // the inner content nested inside its own bookends.
    $innerCached = Cache::store( 'array' )->get( $cache->qualifyKey( 'inner-key' ) );
    $outerCached = Cache::store( 'array' )->get( $cache->qualifyKey( 'outer-key' ) );

    expect( $innerCached )->toContain( 'inner-body' )
        ->and( $innerCached )->not->toContain( 'outer-start' )
        ->and( $outerCached )->toContain( 'outer-start' )
        ->and( $outerCached )->toContain( 'inner-body' )
        ->and( $outerCached )->toContain( 'outer-end' );

    expect( $cache->keysForTag( 'outer' ) )->toContain( $cache->qualifyKey( 'outer-key' ) )
        ->and( $cache->keysForTag( 'inner' ) )->toContain( $cache->qualifyKey( 'inner-key' ) );
} );

it( 'compiles @cache / @endcache through the FragmentCache', function (): void {
    $counter = 0;

    Blade::render( '@cache(\'fragment-test\', 60, [\'demo\']) <p>{{ $value }}</p> @endcache', [
        'value' => 'first',
    ] );

    $cache = app( FragmentCache::class );
    $key   = $cache->qualifyKey( 'fragment-test' );

    expect( Cache::store( 'array' )->get( $key ) )->toContain( 'first' );

    // Render again with a different value — the cached fragment should
    // win, proving the directive is reading through FragmentCache.
    $output = Blade::render( '@cache(\'fragment-test\', 60, [\'demo\']) <p>{{ $value }}</p> @endcache', [
        'value' => 'second',
    ] );

    expect( $output )->toContain( 'first' )
        ->and( $output )->not->toContain( 'second' );

    expect( $cache->keysForTag( 'demo' ) )->toContain( $key );
} );
