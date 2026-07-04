<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\CachesQueriesPostStub;

beforeEach( function (): void {
    config( [ 'cache.default' => 'array' ] );
    // Force every strategy onto the array driver under test so we can
    // observe writes via the same store the trait reads from. The file
    // strategy's `storeName()` returns 'file' — we re-point that store
    // here so flush() / put() / get() route through `array`.
    config( [ 'cache.stores.file' => [ 'driver' => 'array', 'serialize' => false ] ] );
    config( [ 'artisanpack.performance.database.query_cache.driver' => 'file' ] );
    config( [ 'artisanpack.performance.page_cache.driver' => 'file' ] );

    Schema::create( 'caches_queries_posts', function ( $table ): void {
        $table->increments( 'id' );
        $table->string( 'title' );
        $table->boolean( 'published' )->default( false );
    } );

    DB::table( 'caches_queries_posts' )->insert( [
        [ 'title' => 'first', 'published' => true ],
        [ 'title' => 'second', 'published' => false ],
        [ 'title' => 'third', 'published' => true ],
    ] );

    Cache::store( 'file' )->flush();
} );

afterEach( function (): void {
    Schema::dropIfExists( 'caches_queries_posts' );
} );

it( 'serves cached query results on subsequent calls', function (): void {
    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    $first  = CachesQueriesPostStub::query()->cacheFor( 60 )->get();
    $second = CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    expect( $first )->toHaveCount( 3 );
    expect( $second )->toHaveCount( 3 );
    expect( $count )->toBe( 1 );
} );

it( 'returns fresh results when the ttl is non-positive', function (): void {
    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    CachesQueriesPostStub::query()->cacheFor( 0 )->get();
    CachesQueriesPostStub::query()->cacheFor( 0 )->get();

    expect( $count )->toBe( 2 );
} );

it( 'invalidates the cache automatically when a model is saved', function (): void {
    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    $post        = new CachesQueriesPostStub;
    $post->title = 'fourth';
    $post->save();

    $count = 0;

    $refreshed = CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    expect( $refreshed )->toHaveCount( 4 );
    expect( $count )->toBeGreaterThan( 0 );
} );

it( 'invalidates the cache when a model is deleted', function (): void {
    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    $first = CachesQueriesPostStub::query()->first();
    $first->delete();

    $count = 0;

    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    expect( $count )->toBeGreaterThan( 0 );
} );

it( 'uses the custom cache key when one is supplied', function (): void {
    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    CachesQueriesPostStub::query()->cacheFor( 60, 'homepage' )->get();
    CachesQueriesPostStub::query()->cacheFor( 60, 'homepage' )->get();

    expect( $count )->toBe( 1 );
} );

it( 'segregates get and count under different cache keys', function (): void {
    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    $count = CachesQueriesPostStub::query()->cacheFor( 60 )->count();

    expect( $count )->toBe( 3 );
} );

it( 'applies extra tags via cacheTags()', function (): void {
    $builder = CachesQueriesPostStub::query()->cacheFor( 60 )->cacheTags( [ 'homepage' ] );

    expect( $builder->getCacheExtraTags() )->toBe( [ 'homepage' ] );
} );

it( 'does not cache when cacheFor() was never called', function (): void {
    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    CachesQueriesPostStub::query()->get();
    CachesQueriesPostStub::query()->get();

    expect( $count )->toBe( 2 );
} );

it( 'defers invalidation until after the outer transaction commits', function (): void {
    // Prime the cache.
    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    DB::transaction( function (): void {
        $post        = new CachesQueriesPostStub;
        $post->title = 'inside-transaction';
        $post->save();

        // Inside the transaction the flush MUST NOT have happened yet.
        // A concurrent reader cache-HITs against the still-primed entry,
        // which is the desired behavior — without it, a racing reader
        // could refill the cache with pre-commit state and leave it
        // permanently stale once the COMMIT lands.
        $cachedDuringTx = CachesQueriesPostStub::query()->cacheFor( 60 )->get();

        expect( $cachedDuringTx )->toHaveCount( 3 );
    } );

    // After commit, the deferred flush fires and the next read misses.
    $start = $count;
    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    expect( $count )->toBeGreaterThan( $start );
} );

it( 'does not flush when the outer transaction rolls back', function (): void {
    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    try {
        DB::transaction( function (): void {
            $post        = new CachesQueriesPostStub;
            $post->title = 'will-rollback';
            $post->save();

            throw new RuntimeException( 'roll back' );
        } );
    } catch ( RuntimeException ) {
        // Expected.
    }

    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    // The flush was registered as an after-commit callback that never
    // fires because the transaction rolled back. The cache stays
    // primed, so this read hits the cache and emits zero queries.
    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    expect( $count )->toBe( 0 );
} );

it( 'segregates the cache key by resolved page number on paginate()', function (): void {
    // Without the fix, both ?page=1 and ?page=2 collapse to the same
    // cache key and the second URL silently serves page-1 rows.
    Illuminate\Pagination\Paginator::currentPageResolver( fn (): int => 1 );

    $page1 = CachesQueriesPostStub::query()->cacheFor( 60 )->paginate( 2 );

    Illuminate\Pagination\Paginator::currentPageResolver( fn (): int => 2 );

    $page2 = CachesQueriesPostStub::query()->cacheFor( 60 )->paginate( 2 );

    expect( $page1->items()[0]->id )->not->toBe( $page2->items()[0]->id );
} );

it( 'segregates the cache key by eager-load constraint closure identity', function (): void {
    // Two different closures wrapping the same relation name must
    // produce different cache keys — otherwise a constraint change
    // silently HITs the wrong result set.
    $reflect = function ( $builder ): string {
        $method = new ReflectionMethod( $builder, 'buildCacheKey' );
        $method->setAccessible( true );

        return $method->invoke( $builder, 'get', [ [ '*' ] ] );
    };

    $bare = CachesQueriesPostStub::query()->cacheFor( 60 );

    $withA = CachesQueriesPostStub::query()->cacheFor( 60 )->with( [ 'self' => fn ( $q ) => $q ] );
    $withB = CachesQueriesPostStub::query()->cacheFor( 60 )->with( [ 'self' => fn ( $q ) => $q->limit( 1 ) ] );

    $keyBare  = $reflect( $bare );
    $keyWithA = $reflect( $withA );
    $keyWithB = $reflect( $withB );

    expect( $keyWithA )->not->toBe( $keyBare );
    expect( $keyWithA )->not->toBe( $keyWithB );
} );

it( 'mixes SQL into the cache key even when a custom key is supplied', function (): void {
    // Without the fix, both queries collapse to the same key and the
    // second WHERE clause is silently ignored on cache HIT.
    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    $publishedOnly = CachesQueriesPostStub::query()
        ->where( 'published', true )
        ->cacheFor( 60, 'homepage' )
        ->get();

    $authorOnly = CachesQueriesPostStub::query()
        ->where( 'title', 'first' )
        ->cacheFor( 60, 'homepage' )
        ->get();

    expect( $count )->toBe( 2 );
    expect( $publishedOnly->count() )->toBe( 2 );
    expect( $authorOnly->count() )->toBe( 1 );
} );

it( 'rejects a tampered cache payload and re-runs the query', function (): void {
    // The signing check should catch any entry whose HMAC no longer
    // verifies. We model a tampered payload — either the cache backend
    // was compromised or an entry survived from a previous key
    // generation — by rotating `config('app.key')` between the write and
    // the read. The stored signature was computed against the pre-rotation
    // key; the verifier recomputes with the post-rotation key, they don't
    // match, the entry is forgotten, and the query re-executes.
    $count = 0;
    DB::listen( function () use ( &$count ): void { $count++; } );

    CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    expect( $count )->toBe( 1 );

    // Second call should HIT (same key) — sanity check that the fixture
    // is actually populated before we rotate the key.
    CachesQueriesPostStub::query()->cacheFor( 60 )->get();
    expect( $count )->toBe( 1 );

    // Rotate the app key. Any previously signed payload now fails
    // verification against the new key.
    config( [ 'app.key' => 'base64:' . base64_encode( random_bytes( 32 ) ) ] );

    $refreshed = CachesQueriesPostStub::query()->cacheFor( 60 )->get();

    expect( $refreshed )->toHaveCount( 3 );
    expect( $count )->toBe( 2 );
} );

it( 'refuses to sign when config("app.key") is missing', function (): void {
    config( [ 'app.key' => '' ] );

    expect( fn () => CachesQueriesPostStub::query()->cacheFor( 60 )->get() )
        ->toThrow( RuntimeException::class, 'config("app.key")' );
} );

it( 'binds signatures to the cache key so they cannot be relocated', function (): void {
    // Attacker-with-cache-write scenario: relocate a legitimately signed
    // entry from key A to key B. verifyCachedPayload recomputes the HMAC
    // with key B and mismatches. We drive the check directly against the
    // sign/verify pair since instrumenting the cache backend around the
    // builder's internal key derivation would require touching the same
    // private state the previous reflection-based test relied on.
    $builder = CachesQueriesPostStub::query();

    // Reach the protected sign/verify pair via a one-shot anonymous
    // subclass. This is a targeted test seam — no framework internals.
    $probe = new class ( $builder->getQuery(), $builder->getModel() ) extends
        ArtisanPackUI\Performance\Database\CachingEloquentBuilder {
        public function sign( string $serialized, string $key ): string
        {
            return $this->signCachedPayload( $serialized, $key );
        }

        public function verify( string $payload, string $key ): ?string
        {
            return $this->verifyCachedPayload( $payload, $key );
        }
    };

    $payload = serialize( [ 'legit' => 'value' ] );
    $signed  = $probe->sign( $payload, 'perf:query:origin' );

    expect( $probe->verify( $signed, 'perf:query:origin' ) )->toBe( $payload );
    expect( $probe->verify( $signed, 'perf:query:relocated' ) )->toBeNull();
} );

