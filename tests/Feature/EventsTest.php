<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Events\CachePurged;
use ArtisanPackUI\Performance\Events\CacheWarmed;
use ArtisanPackUI\Performance\Events\ImageOptimized;
use ArtisanPackUI\Performance\Events\N1QueryDetected;
use ArtisanPackUI\Performance\Events\PerformanceThresholdExceeded;
use ArtisanPackUI\Performance\Events\SlowQueryDetected;
use Illuminate\Support\Facades\Event;

it( 'dispatches ImageOptimized with the optimization payload', function (): void {
	Event::fake();

	ImageOptimized::dispatch( '/var/uploads/hero.jpg', [ 'webp', 'avif' ], [ 640, 1280 ] );

	Event::assertDispatched( ImageOptimized::class, function ( ImageOptimized $event ) {
		return '/var/uploads/hero.jpg' === $event->path
			&& [ 'webp', 'avif' ] === $event->formats
			&& [ 640, 1280 ] === $event->sizes;
	} );
} );

it( 'dispatches CacheWarmed with warmed urls and count', function (): void {
	Event::fake();

	CacheWarmed::dispatch( [ '/home', '/about' ], 2 );

	Event::assertDispatched( CacheWarmed::class, function ( CacheWarmed $event ) {
		return [ '/home', '/about' ] === $event->urls && 2 === $event->count;
	} );
} );

it( 'dispatches CachePurged with purged keys and optional reason', function (): void {
	Event::fake();

	CachePurged::dispatch( [ 'page:/home' ], 'content updated' );

	Event::assertDispatched( CachePurged::class, function ( CachePurged $event ) {
		return [ 'page:/home' ] === $event->keys && 'content updated' === $event->reason;
	} );
} );

it( 'dispatches SlowQueryDetected with timing and bindings', function (): void {
	Event::fake();

	SlowQueryDetected::dispatch( 'select * from users', 250.5, [], [ 1 ] );

	Event::assertDispatched( SlowQueryDetected::class, function ( SlowQueryDetected $event ) {
		return 'select * from users' === $event->query
			&& 250.5 === $event->timeMs
			&& [ 1 ] === $event->bindings;
	} );
} );

it( 'dispatches N1QueryDetected with normalized query and count', function (): void {
	Event::fake();

	N1QueryDetected::dispatch( 'select * from posts where user_id = ?', 12, 'users.index' );

	Event::assertDispatched( N1QueryDetected::class, function ( N1QueryDetected $event ) {
		return 'select * from posts where user_id = ?' === $event->queryNormalized
			&& 12 === $event->count
			&& 'users.index' === $event->route;
	} );
} );

it( 'dispatches PerformanceThresholdExceeded with metric value vs threshold', function (): void {
	Event::fake();

	PerformanceThresholdExceeded::dispatch( 'LCP', 4500.0, 4000.0 );

	Event::assertDispatched( PerformanceThresholdExceeded::class, function ( PerformanceThresholdExceeded $event ) {
		return 'LCP' === $event->metric
			&& 4500.0 === $event->value
			&& 4000.0 === $event->threshold;
	} );
} );
