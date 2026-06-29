<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Database\QueryAnalyzer;
use ArtisanPackUI\Performance\Events\SlowQueryDetected;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach( function (): void {
    config( [ 'artisanpack.performance.database.slow_query_logging' => [
        'enabled'           => false,
        'threshold_ms'      => 100,
        'log_channel'       => '',
        'store_in_database' => false,
        'retention_days'    => 30,
    ] ] );
} );

it( 'normalizes string and numeric literals to placeholders', function (): void {
    $analyzer = new QueryAnalyzer;

    $normalized = $analyzer->normalize( "SELECT * FROM posts WHERE user_id = 5 AND status = 'published'" );

    expect( $normalized )->toBe( 'select * from posts where user_id = ? and status = ?' );
} );

it( 'collapses IN lists of any size to IN (?)', function (): void {
    $analyzer = new QueryAnalyzer;

    expect( $analyzer->normalize( 'SELECT * FROM posts WHERE id IN (1, 2, 3)' ) )
        ->toBe( 'select * from posts where id in (?)' );

    expect( $analyzer->normalize( 'SELECT * FROM posts WHERE id IN (4, 5)' ) )
        ->toBe( 'select * from posts where id in (?)' );
} );

it( 'collapses whitespace', function (): void {
    $analyzer = new QueryAnalyzer;

    $normalized = $analyzer->normalize( "SELECT\t*\nFROM    posts\n  WHERE  id  =  1" );

    expect( $normalized )->toBe( 'select * from posts where id = ?' );
} );

it( 'records query events into the in-memory log', function (): void {
    $analyzer = new QueryAnalyzer;
    $event    = new QueryExecuted( 'SELECT * FROM posts WHERE id = ?', [ 1 ], 12.5, fakeConnection() );

    $analyzer->record( $event );

    $log = $analyzer->getLoggedQueries();

    expect( $log )->toHaveCount( 1 );
    expect( $log[0]['time_ms'] )->toBe( 12.5 );
    expect( $log[0]['normalized'] )->toBe( 'select * from posts where id = ?' );
} );

it( 'tracks per-signature counts and timings', function (): void {
    $analyzer = new QueryAnalyzer;

    $analyzer->record( new QueryExecuted( 'SELECT * FROM posts WHERE id = ?', [ 1 ], 5.0, fakeConnection() ) );
    $analyzer->record( new QueryExecuted( 'SELECT * FROM posts WHERE id = ?', [ 2 ], 7.0, fakeConnection() ) );

    $counts  = $analyzer->getQueryCounts();
    $timings = $analyzer->getQueryTimings();

    expect( $counts['select * from posts where id = ?'] )->toBe( 2 );
    expect( $timings['select * from posts where id = ?'] )->toBe( 12.0 );
} );

it( 'returns signatures that exceed the threshold', function (): void {
    $analyzer = new QueryAnalyzer;

    for ( $i = 0; $i < 6; $i++ ) {
        $analyzer->record( new QueryExecuted( 'SELECT * FROM posts WHERE id = ?', [ $i ], 1.0, fakeConnection() ) );
    }

    $analyzer->record( new QueryExecuted( 'SELECT * FROM users', [], 1.0, fakeConnection() ) );

    expect( $analyzer->repeatedSignatures( 5 ) )->toHaveKey( 'select * from posts where id = ?' );
    expect( $analyzer->repeatedSignatures( 5 ) )->not->toHaveKey( 'select * from users' );
} );

it( 'dispatches SlowQueryDetected when threshold_ms is exceeded', function (): void {
    Event::fake();

    config( [ 'artisanpack.performance.database.slow_query_logging.enabled' => true ] );
    config( [ 'artisanpack.performance.database.slow_query_logging.threshold_ms' => 50 ] );

    $analyzer = new QueryAnalyzer;

    $analyzer->record( new QueryExecuted( 'SELECT * FROM huge_table', [], 250.0, fakeConnection() ) );

    Event::assertDispatched( SlowQueryDetected::class, function ( SlowQueryDetected $event ): bool {
        return 250.0 === $event->timeMs;
    } );
} );

it( 'does not dispatch when below the slow-query threshold', function (): void {
    Event::fake();

    config( [ 'artisanpack.performance.database.slow_query_logging.enabled' => true ] );
    config( [ 'artisanpack.performance.database.slow_query_logging.threshold_ms' => 500 ] );

    $analyzer = new QueryAnalyzer;
    $analyzer->record( new QueryExecuted( 'SELECT 1', [], 1.0, fakeConnection() ) );

    Event::assertNotDispatched( SlowQueryDetected::class );
} );

it( 'returns suggestions for select-star queries', function (): void {
    $analyzer = new QueryAnalyzer;

    $analysis = $analyzer->analyzeQuery( 'SELECT * FROM posts', [], 10.0 );

    expect( $analysis['suggestions'] )->toContain( 'Avoid SELECT * — list explicit columns to reduce row size and enable covering indexes.' );
} );

it( 'is idempotent on enableQueryLogging', function (): void {
    $analyzer = new QueryAnalyzer;

    $analyzer->enableQueryLogging();
    $analyzer->enableQueryLogging();

    expect( $analyzer->isListening() )->toBeTrue();
} );

it( 'resets internal state', function (): void {
    $analyzer = new QueryAnalyzer;
    $analyzer->record( new QueryExecuted( 'SELECT 1', [], 1.0, fakeConnection() ) );

    $analyzer->reset();

    expect( $analyzer->getLoggedQueries() )->toBe( [] );
    expect( $analyzer->getQueryCounts() )->toBe( [] );
} );

it( 'leaves Postgres double-quoted identifiers intact while collapsing single-quoted literals', function (): void {
    $analyzer = new QueryAnalyzer;

    // Pgsql-shaped query: double-quoted identifiers, single-quoted literal.
    $normalized = $analyzer->normalize( 'SELECT "title" FROM "posts" WHERE "id" = 5 AND "status" = \'live\'' );

    // The identifiers MUST survive — otherwise every pgsql query
    // collapses to the same signature and the analyzer becomes useless.
    expect( $normalized )->toBe( 'select "title" from "posts" where "id" = ? and "status" = ?' );

    // Two distinct pgsql queries must produce distinct signatures.
    $a = $analyzer->normalize( 'SELECT "title" FROM "posts" WHERE "id" = 1' );
    $b = $analyzer->normalize( 'SELECT "name" FROM "users" WHERE "id" = 1' );
    expect( $a )->not->toBe( $b );
} );

function fakeConnection(): Illuminate\Database\Connection
{
    return DB::connection();
}
