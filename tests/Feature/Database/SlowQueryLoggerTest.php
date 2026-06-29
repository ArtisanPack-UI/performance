<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Database\QueryAnalyzer;
use ArtisanPackUI\Performance\Database\SlowQueryLogger;
use ArtisanPackUI\Performance\Events\SlowQueryDetected;
use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config( [ 'artisanpack.performance.database.slow_query_logging' => [
        'enabled'           => true,
        'threshold_ms'      => 100,
        'log_channel'       => '',
        'store_in_database' => false,
        'retention_days'    => 30,
    ] ] );
} );

it( 'records queries that exceed the configured threshold', function (): void {
    $logger = new SlowQueryLogger( new QueryAnalyzer );

    $payload = $logger->record( new QueryExecuted(
        'SELECT * FROM posts WHERE id = ?',
        [ 1 ],
        250.0,
        DB::connection(),
    ) );

    expect( $payload )->not->toBeNull();
    expect( $payload['time_ms'] )->toBe( 250.0 );
    expect( $payload['query_normalized'] )->toBe( 'select * from posts where id = ?' );
    expect( $payload['bindings'] )->toBe( [ 1 ] );
} );

it( 'ignores queries below the configured threshold', function (): void {
    $logger = new SlowQueryLogger( new QueryAnalyzer );

    $payload = $logger->record( new QueryExecuted( 'SELECT 1', [], 5.0, DB::connection() ) );

    expect( $payload )->toBeNull();
} );

it( 'dispatches SlowQueryDetected with the original query and bindings', function (): void {
    Event::fake();

    $logger = new SlowQueryLogger( new QueryAnalyzer );

    $logger->record( new QueryExecuted(
        'SELECT * FROM posts WHERE user_id = ?',
        [ 42 ],
        500.0,
        DB::connection(),
    ) );

    Event::assertDispatched( SlowQueryDetected::class, function ( SlowQueryDetected $event ): bool {
        return 'SELECT * FROM posts WHERE user_id = ?' === $event->query
            && 500.0 === $event->timeMs
            && [ 42 ] === $event->bindings;
    } );
} );

it( 'captures a caller file and line that skip framework frames', function (): void {
    $logger = new SlowQueryLogger( new QueryAnalyzer );

    $payload = $logger->record( new QueryExecuted( 'SELECT 1', [], 250.0, DB::connection() ) );

    expect( $payload['file'] )->not->toBeNull();
    expect( $payload['line'] )->toBeGreaterThan( 0 );
} );

it( 'persists to the database when store_in_database is enabled', function (): void {
    config( [ 'artisanpack.performance.database.slow_query_logging.store_in_database' => true ] );

    $logger = new SlowQueryLogger( new QueryAnalyzer );

    $logger->record( new QueryExecuted( 'SELECT * FROM posts', [], 250.0, DB::connection() ) );

    expect( SlowQuery::count() )->toBe( 1 );

    /** @var SlowQuery $row */
    $row = SlowQuery::first();
    expect( $row->time_ms )->toBe( 250.0 );
    expect( $row->query )->toBe( 'SELECT * FROM posts' );
    expect( $row->query_normalized )->toBe( 'select * from posts' );
} );

it( 'does not persist to the database when store_in_database is disabled', function (): void {
    $logger = new SlowQueryLogger( new QueryAnalyzer );

    $logger->record( new QueryExecuted( 'SELECT * FROM posts', [], 250.0, DB::connection() ) );

    expect( SlowQuery::count() )->toBe( 0 );
} );

it( 'is idempotent on enable', function (): void {
    $logger = new SlowQueryLogger( new QueryAnalyzer );

    $logger->enable();
    $logger->enable();

    expect( $logger->isEnabled() )->toBeTrue();
} );

it( 'purges rows older than the retention window', function (): void {
    config( [ 'artisanpack.performance.database.slow_query_logging.retention_days' => 7 ] );

    SlowQuery::create( [
        'query'            => 'SELECT 1',
        'query_normalized' => 'select ?',
        'bindings'         => [],
        'time_ms'          => 250.0,
        'connection'       => 'testbench',
        'file'             => null,
        'line'             => null,
        'trace'            => [],
        'route'            => null,
    ] );

    // Backdate the row past the retention horizon.
    SlowQuery::query()->update( [ 'created_at' => now()->subDays( 30 ) ] );

    $logger = new SlowQueryLogger( new QueryAnalyzer );

    expect( $logger->purgeExpired() )->toBe( 1 );
    expect( SlowQuery::count() )->toBe( 0 );
} );

it( 'returns zero from purgeExpired when retention is non-positive', function (): void {
    config( [ 'artisanpack.performance.database.slow_query_logging.retention_days' => 0 ] );

    $logger = new SlowQueryLogger( new QueryAnalyzer );

    expect( $logger->purgeExpired() )->toBe( 0 );
} );
