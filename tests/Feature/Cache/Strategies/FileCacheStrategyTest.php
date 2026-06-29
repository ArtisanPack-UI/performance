<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\Strategies\FileCacheStrategy;
use Illuminate\Support\Facades\Cache;

beforeEach( function (): void {
    // The file driver is the realistic "no native tags" backend the
    // strategy's fallback index needs to cover. We re-point the array
    // store onto the `file` slot so the suite can exercise the
    // strategy without writing to disk — same code path, same lack of
    // native tag support.
    config( [ 'cache.stores.file' => [ 'driver' => 'array', 'serialize' => false ] ] );
    Cache::store( 'file' )->flush();
} );

it( 'reads back a value it wrote', function (): void {
    $strategy = new FileCacheStrategy;

    $strategy->put( 'foo', 'bar', 60 );

    expect( $strategy->get( 'foo' ) )->toBe( 'bar' );
} );

it( 'returns null for unknown keys', function (): void {
    expect( ( new FileCacheStrategy )->get( 'missing' ) )->toBeNull();
} );

it( 'forgets values on demand', function (): void {
    $strategy = new FileCacheStrategy;
    $strategy->put( 'foo', 'bar', 60 );

    expect( $strategy->forget( 'foo' ) )->toBeTrue();
    expect( $strategy->get( 'foo' ) )->toBeNull();
} );

it( 'flushes only tag-scoped keys when a tag list is set', function (): void {
    $strategy = new FileCacheStrategy;

    $strategy->put( 'untagged', 'keep', 60 );
    $strategy->tags( [ 'posts' ] )->put( 'tagged', 'drop', 60 );

    $strategy->tags( [ 'posts' ] )->flush();

    expect( $strategy->get( 'untagged' ) )->toBe( 'keep' );
    expect( $strategy->get( 'tagged' ) )->toBeNull();
} );

it( 'returns a fresh instance from tags() without mutating the original', function (): void {
    $strategy = new FileCacheStrategy;

    $scoped = $strategy->tags( [ 'a' ] );

    expect( $scoped )->not->toBe( $strategy );
    expect( $strategy->getScopedTags() )->toBe( [] );
    expect( $scoped->getScopedTags() )->toBe( [ 'a' ] );
} );

it( 'persists indefinitely when ttl is zero', function (): void {
    $strategy = new FileCacheStrategy;

    expect( $strategy->put( 'forever', 'value', 0 ) )->toBeTrue();
    expect( $strategy->get( 'forever' ) )->toBe( 'value' );
} );

it( 'flushes the whole store when no tags are scoped', function (): void {
    $strategy = new FileCacheStrategy;

    $strategy->put( 'a', '1', 60 );
    $strategy->put( 'b', '2', 60 );

    $strategy->flush();

    expect( $strategy->get( 'a' ) )->toBeNull();
    expect( $strategy->get( 'b' ) )->toBeNull();
} );
