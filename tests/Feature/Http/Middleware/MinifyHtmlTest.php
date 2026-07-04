<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Http\Middleware\MinifyHtml;
use ArtisanPackUI\Performance\Output\HtmlMinifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach( function (): void {
    config( [ 'artisanpack.performance.html_minification' => [
        'enabled'              => true,
        'remove_comments'      => true,
        'remove_whitespace'    => true,
        'preserve_line_breaks' => false,
        'exclude_routes'       => [ 'admin/*', 'api/*' ],
        'exclude_elements'     => [ 'pre', 'code', 'textarea', 'script', 'style' ],
    ] ] );
} );

function buildMinifyMiddleware(): MinifyHtml
{
    return new MinifyHtml( new HtmlMinifier );
}

function runMinify( Request $request, $response ): Response|StreamedResponse|BinaryFileResponse
{
    return buildMinifyMiddleware()->handle( $request, static fn () => $response );
}

it( 'minifies HTML responses', function (): void {
    $request = Request::create( '/' );

    $response = new Response(
        "<html>\n    <body>\n        <p>Hello   World</p>\n    </body>\n</html>",
        200,
        [ 'Content-Type' => 'text/html; charset=UTF-8' ],
    );

    $minified = runMinify( $request, $response );

    expect( $minified->getContent() )->toBe( '<html> <body> <p>Hello World</p> </body> </html>' );
} );

it( 'updates Content-Length when the original response declared one', function (): void {
    $request = Request::create( '/' );

    $body = "<html>\n   <body>\n      <p>Hi</p>\n   </body>\n</html>";

    $response = new Response( $body, 200, [
        'Content-Type'   => 'text/html',
        'Content-Length' => (string) strlen( $body ),
    ] );

    $minified = runMinify( $request, $response );

    expect( $minified->headers->get( 'Content-Length' ) )->toBe( (string) strlen( $minified->getContent() ) );
} );

it( 'leaves Content-Length unset when the original response omitted it', function (): void {
    $request  = Request::create( '/' );
    $response = new Response(
        "<html>\n   <body>\n      <p>Hi</p>\n   </body>\n</html>",
        200,
        [ 'Content-Type' => 'text/html' ],
    );

    // Symfony's Response constructor may auto-fill Content-Length; clear it
    // explicitly so we measure the "user never set it" pathway.
    $response->headers->remove( 'Content-Length' );

    $minified = runMinify( $request, $response );

    expect( $minified->headers->has( 'Content-Length' ) )->toBeFalse();
} );

it( 'skips when the feature flag is disabled', function (): void {
    config( [ 'artisanpack.performance.html_minification.enabled' => false ] );

    $request = Request::create( '/' );
    $body    = '<html>   <body>   <p>Hi</p>   </body>   </html>';

    $response = new Response( $body, 200, [ 'Content-Type' => 'text/html' ] );

    expect( runMinify( $request, $response )->getContent() )->toBe( $body );
} );

it( 'skips non-HTML responses', function (): void {
    $request = Request::create( '/api/users' );
    $body    = '{"hello":"world","amount":42}';

    $response = new Response( $body, 200, [ 'Content-Type' => 'application/json' ] );

    expect( runMinify( $request, $response )->getContent() )->toBe( $body );
} );

it( 'skips error responses', function (): void {
    $request = Request::create( '/' );

    $body = "<html>\n  <body>\n    <p>nope</p>\n  </body>\n</html>";

    $response = new Response( $body, 500, [ 'Content-Type' => 'text/html' ] );

    expect( runMinify( $request, $response )->getContent() )->toBe( $body );
} );

it( 'skips routes matching exclude_routes patterns', function (): void {
    $request = Request::create( '/admin/dashboard' );

    $body = "<html>\n   <body>\n      <p>preserved</p>\n   </body>\n</html>";

    $response = new Response( $body, 200, [ 'Content-Type' => 'text/html' ] );

    expect( runMinify( $request, $response )->getContent() )->toBe( $body );
} );

it( 'skips streamed responses', function (): void {
    $request = Request::create( '/' );

    $streamed = new StreamedResponse( static function (): void {
        echo '<html>   <body>   stream   </body>   </html>';
    }, 200, [ 'Content-Type' => 'text/html' ] );

    expect( runMinify( $request, $streamed ) )->toBe( $streamed );
} );

it( 'detects HTML by sniffing the body when Content-Type is missing', function (): void {
    $request = Request::create( '/' );

    $body = "<html>\n   <body>\n      <p>sniff me</p>\n   </body>\n</html>";

    $response = new Response( $body, 200 );
    $response->headers->remove( 'Content-Type' );

    $minified = runMinify( $request, $response );

    expect( $minified->getContent() )->not->toBe( $body );
    expect( $minified->getContent() )->toContain( '<p>sniff me</p>' );
} );

it( 'preserves whitespace inside excluded elements like pre and code', function (): void {
    $request = Request::create( '/' );

    $body = "<html>\n   <body>\n      <pre>   indented   code   </pre>\n   </body>\n</html>";

    $response = new Response( $body, 200, [ 'Content-Type' => 'text/html' ] );

    $minified = runMinify( $request, $response );

    expect( $minified->getContent() )->toContain( '<pre>   indented   code   </pre>' );
} );

it( 'leaves the body untouched when minification produces no change', function (): void {
    $request = Request::create( '/' );

    $body = '<html><body><p>already-tight</p></body></html>';

    $response = new Response( $body, 200, [ 'Content-Type' => 'text/html' ] );

    $minified = runMinify( $request, $response );

    expect( $minified->getContent() )->toBe( $body );
} );
