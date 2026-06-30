<?php

/**
 * HTML minification middleware.
 *
 * Wraps the response pipeline so HTML responses are minified before
 * they leave the application. The middleware short-circuits on
 * conditions where minification would either be wrong (non-HTML
 * payloads, streamed responses, error responses) or unwanted (routes
 * matched by `html_minification.exclude_routes`, the global feature
 * flag turned off) — keeping the cost of an opted-out request to a
 * single feature-flag read.
 *
 * Content-Length is rewritten when the original response set one so
 * downstream proxies and `HEAD` request handlers see a length that
 * matches the body actually being sent. Chunked / unset
 * Content-Length is left alone.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Middleware;

use ArtisanPackUI\Performance\Output\HtmlMinifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HTML minification middleware.
 *
 *
 * @since      1.0.0
 */
class MinifyHtml
{
    /**
     * The minifier shared across middleware invocations.
     *
     * @since 1.0.0
     */
    protected HtmlMinifier $minifier;

    /**
     * Creates a new middleware instance.
     *
     * @since 1.0.0
     *
     * @param  HtmlMinifier  $minifier  Container-resolved minifier.
     */
    public function __construct( HtmlMinifier $minifier )
    {
        $this->minifier = $minifier;
    }

    /**
     * Handles the request.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The incoming request.
     * @param  Closure  $next  Next middleware in the pipeline.
     */
    public function handle( Request $request, Closure $next ): Response
    {
        $response = $next( $request );

        if ( ! $this->shouldMinify( $request, $response ) ) {
            return $response;
        }

        $body = $response->getContent();

        if ( ! is_string( $body ) || '' === $body ) {
            return $response;
        }

        $minified = $this->minifier->minify( $body );

        if ( $minified === $body ) {
            return $response;
        }

        $response->setContent( $minified );

        $this->rewriteContentLength( $response, $minified );

        return $response;
    }

    /**
     * Reports whether the response is eligible for minification.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     * @param  Response  $response  Outgoing response.
     */
    protected function shouldMinify( Request $request, Response $response ): bool
    {
        if ( ! (bool) config( 'artisanpack.performance.html_minification.enabled', false ) ) {
            return false;
        }

        if ( $response instanceof StreamedResponse || $response instanceof BinaryFileResponse ) {
            return false;
        }

        if ( ! $this->isSuccessful( $response ) ) {
            return false;
        }

        if ( ! $this->isHtmlResponse( $response ) ) {
            return false;
        }

        if ( $this->matchesExcludedRoute( $request ) ) {
            return false;
        }

        return true;
    }

    /**
     * Reports whether the response is in the 2xx success range.
     *
     * Minifying error responses risks corrupting framework-generated
     * debug pages — Whoops, Symfony's exception renderer, and Laravel's
     * Telescope renderer all rely on inter-tag whitespace for layout.
     * Letting them through unmodified keeps the developer experience
     * intact even when minification is on.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     */
    protected function isSuccessful( Response $response ): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }

    /**
     * Reports whether the response carries an HTML body.
     *
     * Mirrors `InjectResourceHints::isHtmlResponse()` so the two
     * middlewares agree on what counts as HTML. When the Content-Type
     * header is missing the body is sniffed for an `<html` opener —
     * Laravel views frequently leave the header unset until the
     * Symfony response is finalized.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     */
    protected function isHtmlResponse( Response $response ): bool
    {
        $contentType = $response->headers->get( 'Content-Type' );

        if ( null === $contentType ) {
            $body = $response->getContent();

            return is_string( $body ) && false !== stripos( $body, '<html' );
        }

        return false !== stripos( $contentType, 'text/html' );
    }

    /**
     * Reports whether the request matches a configured exclusion pattern.
     *
     * Patterns are matched against the request path (without leading
     * slash) using shell-style wildcards (`admin/*`, `api/*`) so
     * applications can opt out of minification for entire route
     * families without adding extra middleware groups.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function matchesExcludedRoute( Request $request ): bool
    {
        $patterns = (array) config( 'artisanpack.performance.html_minification.exclude_routes', [] );

        if ( empty( $patterns ) ) {
            return false;
        }

        $path = ltrim( $request->path(), '/' );

        foreach ( $patterns as $pattern ) {
            if ( ! is_string( $pattern ) || '' === $pattern ) {
                continue;
            }

            if ( $this->matchesPattern( $path, ltrim( $pattern, '/' ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Performs a shell-style wildcard match (mirrors `Str::is()` semantics).
     *
     * @since 1.0.0
     *
     * @param  string  $value  Value to test.
     * @param  string  $pattern  Pattern (supports `*`).
     */
    protected function matchesPattern( string $value, string $pattern ): bool
    {
        if ( $value === $pattern ) {
            return true;
        }

        $regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#u';

        return 1 === preg_match( $regex, $value );
    }

    /**
     * Updates Content-Length when the original response declared one.
     *
     * Skips when the original response left Content-Length blank
     * (chunked / streamed pathway) so we don't fabricate a header the
     * upstream pipeline deliberately omitted. `strlen()` is the
     * correct primitive — `mb_strlen()` would report character count,
     * which HTTP intermediaries do not care about.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     * @param  string  $body  The post-minification body.
     */
    protected function rewriteContentLength( Response $response, string $body ): void
    {
        if ( ! $response->headers->has( 'Content-Length' ) ) {
            return;
        }

        $response->headers->set( 'Content-Length', (string) strlen( $body ) );
    }
}
