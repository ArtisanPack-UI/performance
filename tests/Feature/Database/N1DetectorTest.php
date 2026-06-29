<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Database\N1Detector;
use ArtisanPackUI\Performance\Database\QueryAnalyzer;
use ArtisanPackUI\Performance\Events\N1QueryDetected;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach( function (): void {
    config( [ 'artisanpack.performance.database.n1_detection' => [
        'enabled'     => true,
        'threshold'   => 5,
        'log_channel' => '',
        'notify'      => false,
    ] ] );
} );

it( 'dispatches N1QueryDetected after the configured threshold is reached', function (): void {
    Event::fake();

    $detector = new N1Detector( new QueryAnalyzer );

    for ( $i = 1; $i <= 5; $i++ ) {
        $detector->record( new QueryExecuted(
            'SELECT * FROM comments WHERE post_id = ?',
            [ $i ],
            1.0,
            DB::connection(),
        ) );
    }

    Event::assertDispatched( N1QueryDetected::class, function ( N1QueryDetected $event ): bool {
        return 'select * from comments where post_id = ?' === $event->queryNormalized
            && 5 === $event->count;
    } );
} );

it( 'fires only once per signature per request', function (): void {
    Event::fake();

    $detector = new N1Detector( new QueryAnalyzer );

    for ( $i = 1; $i <= 12; $i++ ) {
        $detector->record( new QueryExecuted(
            'SELECT * FROM comments WHERE post_id = ?',
            [ $i ],
            1.0,
            DB::connection(),
        ) );
    }

    Event::assertDispatchedTimes( N1QueryDetected::class, 1 );
} );

it( 'does not dispatch when the threshold has not been reached', function (): void {
    Event::fake();

    $detector = new N1Detector( new QueryAnalyzer );

    for ( $i = 1; $i <= 4; $i++ ) {
        $detector->record( new QueryExecuted( 'SELECT * FROM comments WHERE post_id = ?', [ $i ], 1.0, DB::connection() ) );
    }

    Event::assertNotDispatched( N1QueryDetected::class );
} );

it( 'suggests an eager-loaded relation derived from the table and FK', function (): void {
    $detector = new N1Detector( new QueryAnalyzer );

    $suggestion = $detector->suggestFix( 'select * from comments where post_id = ?' );

    expect( $suggestion['table'] )->toBe( 'comments' );
    expect( $suggestion['relation'] )->toBe( 'comments' );
    expect( $suggestion['suggestion'] )->toContain( "->with('comments')" );
} );

it( 'returns a generic suggestion when the SQL is not N+1 shaped', function (): void {
    $detector = new N1Detector( new QueryAnalyzer );

    $suggestion = $detector->suggestFix( 'select count(*) from posts' );

    expect( $suggestion['table'] )->toBeNull();
    expect( $suggestion['relation'] )->toBeNull();
    expect( $suggestion['suggestion'] )->toContain( 'review eager loading' );
} );

it( 'resets per-request counters', function (): void {
    $detector = new N1Detector( new QueryAnalyzer );

    $detector->record( new QueryExecuted( 'SELECT * FROM users WHERE id = ?', [ 1 ], 1.0, DB::connection() ) );
    $detector->reset();

    expect( $detector->getCounts() )->toBe( [] );
    expect( $detector->getReportedSignatures() )->toBe( [] );
} );

it( 'attaches the active route to dispatched events', function (): void {
    Event::fake();

    $detector = new N1Detector( new QueryAnalyzer );
    $detector->setCurrentRoute( 'posts.index' );

    for ( $i = 0; $i < 5; $i++ ) {
        $detector->record( new QueryExecuted( 'SELECT * FROM comments WHERE post_id = ?', [ $i ], 1.0, DB::connection() ) );
    }

    Event::assertDispatched( N1QueryDetected::class, function ( N1QueryDetected $event ): bool {
        return 'posts.index' === $event->route;
    } );
} );

it( 'respects threshold values configured at runtime', function (): void {
    Event::fake();

    config( [ 'artisanpack.performance.database.n1_detection.threshold' => 2 ] );

    $detector = new N1Detector( new QueryAnalyzer );

    $detector->record( new QueryExecuted( 'SELECT * FROM tags WHERE post_id = ?', [ 1 ], 1.0, DB::connection() ) );
    $detector->record( new QueryExecuted( 'SELECT * FROM tags WHERE post_id = ?', [ 2 ], 1.0, DB::connection() ) );

    Event::assertDispatchedTimes( N1QueryDetected::class, 1 );
} );

it( 'subscribes to DB::listen on enable when the flag is set', function (): void {
    $detector = new N1Detector( new QueryAnalyzer );

    $detector->enable();
    $detector->enable();

    expect( $detector->isEnabled() )->toBeTrue();
} );

it( 'is a no-op when the feature flag is disabled', function (): void {
    config( [ 'artisanpack.performance.database.n1_detection.enabled' => false ] );

    $detector = new N1Detector( new QueryAnalyzer );

    $detector->enable();

    expect( $detector->isEnabled() )->toBeFalse();
} );

it( 'reset() clears the active route name', function (): void {
    $detector = new N1Detector( new QueryAnalyzer );
    $detector->setCurrentRoute( 'admin.orders.show' );

    $detector->reset();

    // After reset the next dispatched event must NOT carry the prior
    // route name (this is the cross-request leak in long-running
    // runtimes the Octane reset hook exists to prevent).
    Event::fake();

    for ( $i = 0; $i < 5; $i++ ) {
        $detector->record( new QueryExecuted( 'SELECT * FROM tags WHERE post_id = ?', [ $i ], 1.0, DB::connection() ) );
    }

    Event::assertDispatched( N1QueryDetected::class, function ( N1QueryDetected $event ): bool {
        return '' === $event->route;
    } );
} );

it( 'suggests the singular relation for a BelongsTo N+1 (WHERE id = ?)', function (): void {
    $detector = new N1Detector( new QueryAnalyzer );

    // Comment->user style: SELECT * FROM users WHERE id = ?
    $suggestion = $detector->suggestFix( 'select * from users where id = ?' );

    expect( $suggestion['table'] )->toBe( 'users' );
    expect( $suggestion['relation'] )->toBe( 'user' );
    expect( $suggestion['suggestion'] )->toContain( "->with('user')" );
} );

it( 'suggests camelCase for multi-word tables in BelongsTo N+1', function (): void {
    $detector = new N1Detector( new QueryAnalyzer );

    // BelongsTo on a multi-word table — relation should be camelCase singular.
    $suggestion = $detector->suggestFix( 'select * from team_owners where id = ?' );

    expect( $suggestion['relation'] )->toBe( 'teamOwner' );
} );

it( 'suggests camelCase plural for HasMany N+1 against multi-word tables', function (): void {
    $detector = new N1Detector( new QueryAnalyzer );

    $suggestion = $detector->suggestFix( 'select * from order_items where order_id = ?' );

    expect( $suggestion['relation'] )->toBe( 'orderItems' );
} );
