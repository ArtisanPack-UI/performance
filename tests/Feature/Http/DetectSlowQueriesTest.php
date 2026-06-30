<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Database\N1Detector;
use ArtisanPackUI\Performance\Database\QueryAnalyzer;
use ArtisanPackUI\Performance\Database\SlowQueryLogger;
use ArtisanPackUI\Performance\Http\Middleware\DetectSlowQueries;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

beforeEach( function (): void {
    config( [ 'artisanpack.performance.features.query_optimization' => true ] );
    config( [ 'artisanpack.performance.database.n1_detection' => [
        'enabled'     => true,
        'threshold'   => 5,
        'log_channel' => '',
        'notify'      => false,
    ] ] );
    config( [ 'artisanpack.performance.database.slow_query_logging' => [
        'enabled'           => true,
        'threshold_ms'      => 100,
        'log_channel'       => '',
        'store_in_database' => false,
        'retention_days'    => 30,
    ] ] );
    config( [ 'artisanpack.performance.database.detection.exclude_routes' => [] ] );
    config( [ 'artisanpack.performance.resource_hints.exclude_routes' => [] ] );
} );

function makeMiddleware(): array
{
    $analyzer   = new QueryAnalyzer;
    $detector   = new N1Detector( $analyzer );
    $logger     = new SlowQueryLogger( $analyzer );
    $middleware = new DetectSlowQueries( $analyzer, $detector, $logger );

    return [ $middleware, $analyzer, $detector, $logger ];
}

it( 'enables all detectors when feature flags are on', function (): void {
    [ $middleware, $analyzer, $detector, $logger ] = makeMiddleware();

    $middleware->handle( Request::create( '/posts' ), fn () => response( 'ok' ) );

    expect( $analyzer->isListening() )->toBeTrue();
    expect( $detector->isEnabled() )->toBeTrue();
    expect( $logger->isEnabled() )->toBeTrue();
} );

it( 'is a no-op when every feature flag is off', function (): void {
    config( [ 'artisanpack.performance.features.query_optimization' => false ] );
    config( [ 'artisanpack.performance.database.n1_detection.enabled' => false ] );
    config( [ 'artisanpack.performance.database.slow_query_logging.enabled' => false ] );

    [ $middleware, $analyzer, $detector, $logger ] = makeMiddleware();

    $middleware->handle( Request::create( '/posts' ), fn () => response( 'ok' ) );

    expect( $analyzer->isListening() )->toBeFalse();
    expect( $detector->isEnabled() )->toBeFalse();
    expect( $logger->isEnabled() )->toBeFalse();
} );

it( 'only enables the analyzer when query_optimization is on', function (): void {
    config( [ 'artisanpack.performance.features.query_optimization' => true ] );
    config( [ 'artisanpack.performance.database.n1_detection.enabled' => false ] );
    config( [ 'artisanpack.performance.database.slow_query_logging.enabled' => false ] );

    [ $middleware, $analyzer, $detector, $logger ] = makeMiddleware();

    $middleware->handle( Request::create( '/posts' ), fn () => response( 'ok' ) );

    expect( $analyzer->isListening() )->toBeTrue();
    expect( $detector->isEnabled() )->toBeFalse();
    expect( $logger->isEnabled() )->toBeFalse();
} );

it( 'only enables the slow query logger when slow_query_logging is on', function (): void {
    config( [ 'artisanpack.performance.features.query_optimization' => false ] );
    config( [ 'artisanpack.performance.database.n1_detection.enabled' => false ] );
    config( [ 'artisanpack.performance.database.slow_query_logging.enabled' => true ] );

    [ $middleware, $analyzer, $detector, $logger ] = makeMiddleware();

    $middleware->handle( Request::create( '/posts' ), fn () => response( 'ok' ) );

    expect( $analyzer->isListening() )->toBeFalse();
    expect( $detector->isEnabled() )->toBeFalse();
    expect( $logger->isEnabled() )->toBeTrue();
} );

it( 'only enables the N+1 detector when n1_detection is on', function (): void {
    config( [ 'artisanpack.performance.features.query_optimization' => false ] );
    config( [ 'artisanpack.performance.database.n1_detection.enabled' => true ] );
    config( [ 'artisanpack.performance.database.slow_query_logging.enabled' => false ] );

    [ $middleware, $analyzer, $detector, $logger ] = makeMiddleware();

    $middleware->handle( Request::create( '/posts' ), fn () => response( 'ok' ) );

    expect( $analyzer->isListening() )->toBeFalse();
    expect( $detector->isEnabled() )->toBeTrue();
    expect( $logger->isEnabled() )->toBeFalse();
} );

it( 'skips routes matching the dedicated exclude list', function (): void {
    config( [ 'artisanpack.performance.database.detection.exclude_routes' => [ 'admin/*' ] ] );

    [ $middleware, $analyzer ] = makeMiddleware();

    $middleware->handle( Request::create( '/admin/dashboard' ), fn () => response( 'ok' ) );

    expect( $analyzer->isListening() )->toBeFalse();
} );

it( 'falls back to resource_hints.exclude_routes when dedicated list is empty', function (): void {
    config( [ 'artisanpack.performance.database.detection.exclude_routes' => [] ] );
    config( [ 'artisanpack.performance.resource_hints.exclude_routes' => [ 'api/*' ] ] );

    [ $middleware, $analyzer ] = makeMiddleware();

    $middleware->handle( Request::create( '/api/users' ), fn () => response( 'ok' ) );

    expect( $analyzer->isListening() )->toBeFalse();
} );

it( 'attaches the matched route name to the N+1 detector', function (): void {
    [ $middleware, , $detector ] = makeMiddleware();

    $route = new Route( [ 'GET' ], 'posts', [] );
    $route->name( 'posts.index' );

    $request = Request::create( '/posts' );
    $request->setRouteResolver( static fn () => $route );

    $middleware->handle( $request, fn () => response( 'ok' ) );

    Illuminate\Support\Facades\Event::fake();

    for ( $i = 0; $i < 5; $i++ ) {
        $detector->record( new Illuminate\Database\Events\QueryExecuted(
            'SELECT * FROM comments WHERE post_id = ?',
            [ $i ],
            1.0,
            Illuminate\Support\Facades\DB::connection(),
        ) );
    }

    Illuminate\Support\Facades\Event::assertDispatched(
        ArtisanPackUI\Performance\Events\N1QueryDetected::class,
        static fn ( ArtisanPackUI\Performance\Events\N1QueryDetected $event ): bool =>
            'posts.index' === $event->route,
    );
} );

it( 'falls back to the URI when no route name is set', function (): void {
    [ $middleware, , $detector ] = makeMiddleware();

    $route   = new Route( [ 'GET' ], 'posts/{post}', [] );
    $request = Request::create( '/posts/42' );
    $request->setRouteResolver( static fn () => $route );

    $middleware->handle( $request, fn () => response( 'ok' ) );

    Illuminate\Support\Facades\Event::fake();

    for ( $i = 0; $i < 5; $i++ ) {
        $detector->record( new Illuminate\Database\Events\QueryExecuted(
            'SELECT * FROM tags WHERE post_id = ?',
            [ $i ],
            1.0,
            Illuminate\Support\Facades\DB::connection(),
        ) );
    }

    Illuminate\Support\Facades\Event::assertDispatched(
        ArtisanPackUI\Performance\Events\N1QueryDetected::class,
        static fn ( ArtisanPackUI\Performance\Events\N1QueryDetected $event ): bool =>
            'posts/{post}' === $event->route,
    );
} );

it( 'returns the downstream response unchanged', function (): void {
    [ $middleware ] = makeMiddleware();

    $response = $middleware->handle(
        Request::create( '/posts' ),
        fn () => response( 'ok', 201 ),
    );

    expect( $response->getStatusCode() )->toBe( 201 );
    expect( $response->getContent() )->toBe( 'ok' );
} );

it( 'works when registered as a route middleware', function (): void {
    RouteFacade::middleware( DetectSlowQueries::class )
        ->get( '/perf-test-route', fn () => 'detection ok' );

    test()->get( '/perf-test-route' )
        ->assertOk()
        ->assertSeeText( 'detection ok' );
} );
