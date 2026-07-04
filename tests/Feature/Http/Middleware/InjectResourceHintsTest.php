<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Http\Middleware\InjectResourceHints;
use ArtisanPackUI\Performance\Output\ResourceHint;
use ArtisanPackUI\Performance\Output\ResourceHintInjector;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach( function (): void {
    config( ['artisanpack.performance.features.resource_hints' => true] );
    config( ['artisanpack.performance.resource_hints' => [
        'auto_generate'  => false,
        'preconnect'     => [],
        'dns_prefetch'   => [],
        'preload'        => [],
        'prefetch'       => [],
        'exclude_routes' => [],
    ]] );

    app()->forgetInstance( ResourceHintInjector::class );
} );

function performMiddleware( Request $request, SymfonyResponse $response ): SymfonyResponse
{
    $middleware = app( InjectResourceHints::class );

    return $middleware->handle( $request, static fn () => $response );
}

it( 'injects configured preconnect hints into the document head', function (): void {
    config( ['artisanpack.performance.resource_hints.preconnect' => [
        'https://fonts.googleapis.com',
    ]] );

    $response = performMiddleware(
        Request::create( '/', 'GET' ),
        new Response(
            '<!doctype html><html><head><title>x</title></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    );

    expect( $response->getContent() )
        ->toContain( 'rel="preconnect"' )
        ->toContain( 'href="https://fonts.googleapis.com"' );
} );

it( 'writes a Link header with each hint', function (): void {
    config( ['artisanpack.performance.resource_hints' => [
        'auto_generate'  => false,
        'preconnect'     => ['https://fonts.googleapis.com'],
        'dns_prefetch'   => [],
        'preload'        => [['href' => '/fonts/inter.woff2', 'as' => 'font', 'crossorigin' => 'anonymous']],
        'prefetch'       => [],
        'exclude_routes' => [],
    ]] );

    $response = performMiddleware(
        Request::create( '/', 'GET' ),
        new Response(
            '<!doctype html><html><head></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    $link = $response->headers->get( 'Link' );

    expect( $link )
        ->toContain( 'rel=preconnect' )
        ->toContain( '<https://fonts.googleapis.com>' )
        ->toContain( '</fonts/inter.woff2>' )
        ->toContain( 'rel=preload' );
} );

it( 'preserves an upstream Link header alongside injected hints', function (): void {
    config( ['artisanpack.performance.resource_hints.preconnect' => ['https://fonts.googleapis.com']] );

    $response = new Response(
        '<!doctype html><html><head></head><body></body></html>',
        200,
        ['Content-Type' => 'text/html', 'Link' => '<https://upstream.test>; rel=canonical'],
    );

    $result = performMiddleware( Request::create( '/', 'GET' ), $response );

    expect( $result->headers->get( 'Link' ) )
        ->toContain( 'rel=canonical' )
        ->toContain( 'rel=preconnect' );
} );

it( 'skips non-HTML responses', function (): void {
    config( ['artisanpack.performance.resource_hints.preconnect' => ['https://fonts.googleapis.com']] );

    $response = new Response( '{"ok":true}', 200, ['Content-Type' => 'application/json'] );

    $result = performMiddleware( Request::create( '/api/users', 'GET' ), $response );

    expect( $result->getContent() )->toBe( '{"ok":true}' )
        ->and( $result->headers->has( 'Link' ) )->toBeFalse();
} );

it( 'skips streamed responses', function (): void {
    config( ['artisanpack.performance.resource_hints.preconnect' => ['https://fonts.googleapis.com']] );

    $streamed = new StreamedResponse(
        static function (): void {
            echo '<!doctype html><html><head></head><body></body></html>';
        },
        200,
        ['Content-Type' => 'text/html'],
    );

    $result = performMiddleware( Request::create( '/stream', 'GET' ), $streamed );

    expect( $result )->toBe( $streamed )
        ->and( $result->headers->has( 'Link' ) )->toBeFalse();
} );

it( 'skips excluded routes', function (): void {
    config( ['artisanpack.performance.resource_hints' => [
        'auto_generate'  => false,
        'preconnect'     => ['https://fonts.googleapis.com'],
        'dns_prefetch'   => [],
        'preload'        => [],
        'prefetch'       => [],
        'exclude_routes' => ['admin/*'],
    ]] );

    $response = new Response(
        '<!doctype html><html><head></head><body></body></html>',
        200,
        ['Content-Type' => 'text/html'],
    );

    $result = performMiddleware( Request::create( '/admin/users', 'GET' ), $response );

    expect( $result->getContent() )->not->toContain( 'preconnect' )
        ->and( $result->headers->has( 'Link' ) )->toBeFalse();
} );

it( 'skips when the resource_hints feature flag is disabled', function (): void {
    config( ['artisanpack.performance.features.resource_hints' => false] );
    config( ['artisanpack.performance.resource_hints.preconnect' => ['https://fonts.googleapis.com']] );

    $response = new Response(
        '<!doctype html><html><head></head><body></body></html>',
        200,
        ['Content-Type' => 'text/html'],
    );

    $result = performMiddleware( Request::create( '/', 'GET' ), $response );

    expect( $result->getContent() )->not->toContain( 'preconnect' );
} );

it( 'auto-detects third-party origins when auto_generate is enabled', function (): void {
    config( ['artisanpack.performance.resource_hints.auto_generate' => true] );

    $response = performMiddleware(
        Request::create( 'http://example.test/', 'GET' ),
        new Response(
            '<!doctype html><html><head></head>'
                . '<body><img src="https://cdn.example.com/x.png"></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    expect( $response->getContent() )
        ->toContain( 'href="https://cdn.example.com"' )
        ->toContain( 'rel="preconnect"' );
} );

it( 'does not auto-detect the request host itself', function (): void {
    config( ['artisanpack.performance.resource_hints.auto_generate' => true] );

    $response = performMiddleware(
        Request::create( 'http://example.test/', 'GET' ),
        new Response(
            '<!doctype html><html><head></head>'
                . '<body><img src="http://example.test/local.png"></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    expect( $response->getContent() )->not->toContain( 'rel="preconnect"' );
} );

it( 'clears the auto-detected pool between requests', function (): void {
    // Regression: a stale auto-detected pool would leak preconnect hints
    // from one request's HTML into the next response's head.
    config( ['artisanpack.performance.resource_hints.auto_generate' => true] );

    $first = performMiddleware(
        Request::create( 'http://example.test/', 'GET' ),
        new Response(
            '<!doctype html><html><head></head>'
                . '<body><img src="https://cdn.example.com/x.png"></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    expect( $first->getContent() )->toContain( 'cdn.example.com' );

    $second = performMiddleware(
        Request::create( 'http://example.test/page2', 'GET' ),
        new Response(
            '<!doctype html><html><head></head><body>no images</body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    expect( $second->getContent() )->not->toContain( 'cdn.example.com' );
} );

it( 'clears manually-registered hints even when shouldInject() short-circuits (Octane safety on excluded routes)', function (): void {
    // Regression: the original cross-request leak fix only swapped clear()
    // into the happy path. The early-exit branches (feature flag off,
    // non-HTML response, excluded route, no resolved hints) kept the
    // previous clearAutoDetected() call, so a controller registering a
    // manual hint on an excluded route would still leak it into request
    // N+1's HTML head.
    config( ['artisanpack.performance.resource_hints.exclude_routes' => ['admin/*']] );

    app( ResourceHintInjector::class )->preconnect( 'https://request-1.test' );

    // Request 1: hits an excluded route, so shouldInject() returns false.
    performMiddleware(
        Request::create( 'http://example.test/admin/users', 'GET' ),
        new Response(
            '<!doctype html><html><head></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    // Request 2: hits an included route; the manual hint must NOT carry over.
    $second = performMiddleware(
        Request::create( 'http://example.test/page2', 'GET' ),
        new Response(
            '<!doctype html><html><head></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    expect( $second->getContent() )->not->toContain( 'request-1.test' )
        ->and( $second->headers->has( 'Link' ) )->toBeFalse();
} );

it( 'clears manually-registered hints between requests (Octane/RoadRunner safety)', function (): void {
    // Regression: long-running PHP workers (Octane/RoadRunner/Swoole) keep
    // the injector singleton alive across requests. If the middleware only
    // drained the auto-detected pool, manual hints registered by request N's
    // controller would leak into request N+1's <head>.
    app( ResourceHintInjector::class )->preconnect( 'https://request-1.test' );

    performMiddleware(
        Request::create( 'http://example.test/', 'GET' ),
        new Response(
            '<!doctype html><html><head></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    $second = performMiddleware(
        Request::create( 'http://example.test/page2', 'GET' ),
        new Response(
            '<!doctype html><html><head></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    expect( $second->getContent() )->not->toContain( 'request-1.test' )
        ->and( $second->headers->has( 'Link' ) )->toBeFalse();
} );

it( 'does not preconnect to anchor href domains (only resource attributes)', function (): void {
    // Regression: the auto-detect regex used to match both `src=` and
    // `href=` indiscriminately, so social/share <a> links were treated as
    // resource fetches and emitted spurious preconnect hints.
    config( ['artisanpack.performance.resource_hints.auto_generate' => true] );

    $response = performMiddleware(
        Request::create( 'http://example.test/', 'GET' ),
        new Response(
            '<!doctype html><html><head></head>'
                . '<body><a href="https://twitter.com/share">Tweet</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    // The <a href> stays in the body; what must NOT be present is a
    // preconnect <link> element pointing at the social domain.
    expect( $response->getContent() )->not->toContain( 'rel="preconnect" href="https://twitter.com"' );
} );

it( 'preconnects to <link href=> origins (legitimate resource fetches)', function (): void {
    config( ['artisanpack.performance.resource_hints.auto_generate' => true] );

    $response = performMiddleware(
        Request::create( 'http://example.test/', 'GET' ),
        new Response(
            '<!doctype html><html>'
                . '<head><link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto"></head>'
                . '<body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    expect( $response->getContent() )->toContain( 'fonts.googleapis.com' );
} );

it( 'treats the request host case-insensitively when filtering self-references', function (): void {
    config( ['artisanpack.performance.resource_hints.auto_generate' => true] );

    $response = performMiddleware(
        Request::create( 'http://example.test/', 'GET' ),
        new Response(
            '<!doctype html><html><head></head>'
                . '<body><img src="https://EXAMPLE.TEST/banner.png"></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    expect( $response->getContent() )->not->toContain( 'rel="preconnect"' );
} );

it( 'injects hints before the document </head> even when a JS string literal contains "</head>"', function (): void {
    // Regression: stripos used to return the FIRST literal occurrence, so
    // an inlined JS template string containing the substring `</head>`
    // would redirect the splice into the middle of the script. The
    // middleware must anchor on the actual document head close tag —
    // confining the search to the prefix before `<body` is sufficient.
    config( ['artisanpack.performance.resource_hints.preconnect' => [
        'https://fonts.googleapis.com',
    ]] );

    $body = '<!doctype html><html><head>'
        . '<script>const tmpl = "<div></head>...</div>";</script>'
        . '</head><body></body></html>';

    $response = performMiddleware(
        Request::create( '/', 'GET' ),
        new Response( $body, 200, ['Content-Type' => 'text/html'] ),
    );

    $content = $response->getContent();

    // The hint block must land between the real </head> close tag (right
    // before </head><body>) and not somewhere inside the JS string literal.
    $linkPos       = strpos( $content, 'rel="preconnect"' );
    $realHeadClose = strrpos( $content, '</head>' );
    $scriptStart   = strpos( $content, '<script>' );
    $scriptEnd     = strpos( $content, '</script>' );

    expect( $linkPos )->toBeInt()
        ->and( $linkPos > $scriptEnd )->toBeTrue()
        ->and( $linkPos < $realHeadClose )->toBeTrue()
        ->and( $linkPos < $scriptStart )->toBeFalse();
} );

it( 'drops Link header values that would break the header grammar', function (): void {
    // Regression: toLinkHeader() interpolates type/media/referrerpolicy
    // into quoted-string params without escaping. Hints with embedded `"`
    // CR/LF would break the header. The injector must skip such hints
    // from the header pathway (HTML rendering remains safe because
    // toLinkElement HTML-escapes every value).
    app( ResourceHintInjector::class )->preconnect( 'https://fonts.googleapis.com' );
    app( ResourceHintInjector::class )->addAutoDetected( new ResourceHint(
        rel: 'preload',
        href: '/x.bin',
        type: 'font/woff2"; rel=stylesheet; nopush',
    ) );

    $response = performMiddleware(
        Request::create( '/', 'GET' ),
        new Response(
            '<!doctype html><html><head></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    );

    $link = $response->headers->get( 'Link' );

    expect( $link )->toContain( 'fonts.googleapis.com' )
        ->and( $link )->not->toContain( 'nopush' );
} );
