<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Cache\PageCacheManager;
use ArtisanPackUI\Performance\Events\CacheWarmed;
use ArtisanPackUI\Performance\Jobs\WarmPageCacheJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it( 'dispatches onto the configured warming connection and queue', function (): void {
    Queue::fake();

    config( [
        'artisanpack.performance.cache_warming.connection' => 'redis',
        'artisanpack.performance.cache_warming.queue'      => 'warming',
    ] );

    WarmPageCacheJob::dispatch( [ '/products' ] );

    Queue::assertPushed( WarmPageCacheJob::class, function ( WarmPageCacheJob $job ): bool {
        return 'redis' === $job->connection
            && 'warming' === $job->queue
            && 1 === $job->tries
            && [ '/products' ] === $job->urls;
    } );
} );

it( 'leaves the connection unset when none is configured', function (): void {
    config( [ 'artisanpack.performance.cache_warming.connection' => null ] );

    $job = new WarmPageCacheJob( [ '/products' ] );

    expect( $job->connection )->toBeNull()
        ->and( $job->queue )->toBe( 'default' );
} );

it( 'warms every URL through the page cache manager', function (): void {
    Event::fake();
    config( [
        'app.url'                                        => 'https://example.test',
        'artisanpack.performance.cache_warming.delay_ms' => 0,
    ] );

    Http::fake( [
        'https://example.test/*' => Http::response( 'ok', 200 ),
    ] );

    ( new WarmPageCacheJob( [ '/products', '/about' ] ) )->handle( new PageCacheManager );

    Http::assertSentCount( 2 );
    Event::assertDispatched( CacheWarmed::class, fn ( CacheWarmed $event ): bool => 2 === $event->count );
} );

it( 'does not stack duplicate passes for the same URL set', function (): void {
    Queue::fake();
    config( [ 'queue.default' => 'database' ] );

    $manager = new PageCacheManager;
    $manager->queueWarmPageCache( [ '/products', '/about' ] );
    $manager->queueWarmPageCache( [ '/products', '/about' ] );
    $manager->queueWarmPageCache( [ '/contact' ] );

    Queue::assertPushed( WarmPageCacheJob::class, 2 );
} );
