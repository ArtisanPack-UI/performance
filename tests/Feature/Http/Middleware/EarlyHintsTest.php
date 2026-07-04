<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Http\Middleware\EarlyHints;
use ArtisanPackUI\Performance\Output\ResourceHintInjector;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

beforeEach( function (): void {
    config( [ 'artisanpack.performance.early_hints' => [
        'enabled'      => true,
        'auto_detect'  => true,
        'manual_hints' => [],
    ] ] );
} );

function buildEarlyHints( ResourceHintInjector $injector ): EarlyHints
{
    $middleware = new EarlyHints( $injector );

    // Default emitter calls header() which warns under PHPUnit. Tests
    // that care about emission install their own recorder.
    $middleware->setEmitter( static function ( array $payload ): void {
        // no-op default
    } );

    return $middleware;
}

it( 'emits 103 with manual hints', function (): void {
    config( [ 'artisanpack.performance.early_hints.manual_hints' => [
        [ 'rel' => 'preload', 'href' => '/css/app.css', 'as' => 'style' ],
        [ 'rel' => 'preload', 'href' => '/js/app.js', 'as' => 'script' ],
    ] ] );

    $captured = [];

    $middleware = buildEarlyHints( new ResourceHintInjector );
    $middleware->setEmitter( static function ( array $payload ) use ( &$captured ): void {
        $captured = $payload;
    } );

    $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200 ),
    );

    expect( $captured )->not->toBeEmpty();
    expect( $captured[0] )->toBe( 'HTTP/1.1 103 Early Hints' );
    expect( $captured )->toContain( 'Link: </css/app.css>; rel=preload; as=style' );
    expect( $captured )->toContain( 'Link: </js/app.js>; rel=preload; as=script' );
} );

it( 'mirrors hints into the final response as Link headers', function (): void {
    config( [ 'artisanpack.performance.early_hints.manual_hints' => [
        [ 'rel' => 'preload', 'href' => '/css/app.css', 'as' => 'style' ],
    ] ] );

    $middleware = buildEarlyHints( new ResourceHintInjector );

    $response = $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    $link = $response->headers->get( 'Link' );

    expect( $link )->toContain( '</css/app.css>; rel=preload; as=style' );
} );

it( 'auto-detects preload and preconnect hints from the injector', function (): void {
    $injector = new ResourceHintInjector;
    $injector->preconnect( 'https://cdn.example.com' );
    $injector->preload( '/fonts/sans.woff2', 'font', 'font/woff2', 'anonymous' );

    $captured = [];

    $middleware = buildEarlyHints( $injector );
    $middleware->setEmitter( static function ( array $payload ) use ( &$captured ): void {
        $captured = $payload;
    } );

    $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200 ),
    );

    expect( $captured )->toContain( 'Link: <https://cdn.example.com>; rel=preconnect' );
    expect( $captured )->toContain( 'Link: </fonts/sans.woff2>; rel=preload; as=font; type="font/woff2"; crossorigin=anonymous' );
} );

it( 'skips auto-detection when auto_detect is disabled', function (): void {
    config( [ 'artisanpack.performance.early_hints.auto_detect' => false ] );

    $injector = new ResourceHintInjector;
    $injector->preconnect( 'https://cdn.example.com' );

    $captured = [];

    $middleware = buildEarlyHints( $injector );
    $middleware->setEmitter( static function ( array $payload ) use ( &$captured ): void {
        $captured = $payload;
    } );

    $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200 ),
    );

    expect( $captured )->toBeEmpty();
} );

it( 'does not emit 103 when no hints resolve', function (): void {
    $captured = [];

    $middleware = buildEarlyHints( new ResourceHintInjector );
    $middleware->setEmitter( static function ( array $payload ) use ( &$captured ): void {
        $captured = $payload;
    } );

    $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200 ),
    );

    expect( $captured )->toBeEmpty();
} );

it( 'no-ops entirely when the feature flag is disabled', function (): void {
    config( [ 'artisanpack.performance.early_hints.enabled' => false ] );
    config( [ 'artisanpack.performance.early_hints.manual_hints' => [
        [ 'rel' => 'preload', 'href' => '/css/app.css', 'as' => 'style' ],
    ] ] );

    $captured = [];

    $middleware = buildEarlyHints( new ResourceHintInjector );
    $middleware->setEmitter( static function ( array $payload ) use ( &$captured ): void {
        $captured = $payload;
    } );

    $response = $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200 ),
    );

    expect( $captured )->toBeEmpty();
    expect( $response->headers->has( 'Link' ) )->toBeFalse();
} );

it( 'deduplicates a hint that appears both manually and via auto-detect', function (): void {
    config( [ 'artisanpack.performance.early_hints.manual_hints' => [
        [ 'rel' => 'preload', 'href' => '/css/app.css', 'as' => 'style' ],
    ] ] );

    $injector = new ResourceHintInjector;
    $injector->preload( '/css/app.css', 'style' );

    $captured = [];

    $middleware = buildEarlyHints( $injector );
    $middleware->setEmitter( static function ( array $payload ) use ( &$captured ): void {
        $captured = $payload;
    } );

    $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200 ),
    );

    $linkLines = array_filter( $captured, static fn ( string $line ): bool => str_starts_with( $line, 'Link:' ) );

    expect( $linkLines )->toHaveCount( 1 );
} );

it( 'drops hints that would break the Link header grammar', function (): void {
    config( [ 'artisanpack.performance.early_hints.manual_hints' => [
        // CRLF in the href would smuggle a second response into the wire.
        [ 'rel' => 'preload', 'href' => "/x\r\nLocation: https://evil", 'as' => 'script' ],
    ] ] );

    $captured = [];

    $middleware = buildEarlyHints( new ResourceHintInjector );
    $middleware->setEmitter( static function ( array $payload ) use ( &$captured ): void {
        $captured = $payload;
    } );

    $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200 ),
    );

    expect( $captured )->toBeEmpty();
} );

it( 'default emitter is a no-op under CLI so PHPUnit / artisan serve / queue workers stay quiet', function (): void {
    // Use the production middleware (no setEmitter() override) so the
    // default emitter path runs. Under the CLI SAPI the emitter must
    // short-circuit before touching `header()` — otherwise PHPUnit
    // would emit a "headers already sent" warning here and any future
    // integration test that hits a route mounted on perf.early-hints
    // would inherit the same noise.
    expect( PHP_SAPI )->toBe( 'cli' );

    config( [ 'artisanpack.performance.early_hints.manual_hints' => [
        [ 'rel' => 'preload', 'href' => '/css/app.css', 'as' => 'style' ],
    ] ] );

    $middleware = new EarlyHints( new ResourceHintInjector );

    $response = $middleware->handle(
        Request::create( '/' ),
        static fn () => new Response( '<html></html>', 200, [ 'Content-Type' => 'text/html' ] ),
    );

    // The mirrored Link header still lands on the final response so
    // 103-unaware intermediaries see the same data — proves the
    // middleware completed without bailing entirely.
    expect( $response->headers->get( 'Link' ) )->toContain( '</css/app.css>; rel=preload; as=style' );
} );

it( 'preserves existing Link header values when mirroring', function (): void {
    config( [ 'artisanpack.performance.early_hints.manual_hints' => [
        [ 'rel' => 'preload', 'href' => '/css/app.css', 'as' => 'style' ],
    ] ] );

    $middleware = buildEarlyHints( new ResourceHintInjector );

    $response = $middleware->handle(
        Request::create( '/' ),
        static function () {
            return new Response(
                '<html></html>',
                200,
                [
                    'Content-Type' => 'text/html',
                    'Link'         => '<https://existing.example.com>; rel=dns-prefetch',
                ],
            );
        },
    );

    $link = $response->headers->get( 'Link' );

    expect( $link )->toContain( '<https://existing.example.com>; rel=dns-prefetch' );
    expect( $link )->toContain( '</css/app.css>; rel=preload; as=style' );
} );
