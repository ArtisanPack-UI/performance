<?php

/**
 * Resource hint injection middleware.
 *
 * Drains the request-scoped `ResourceHintInjector` and writes its
 * resolved hints into the outgoing response in two complementary forms:
 * a `<link>` block injected into the document `<head>` for browsers
 * that wait until they parse the HTML, and an RFC 8288 `Link` header
 * for browsers/proxies that act on header hints (HTTP/2 server push,
 * HTTP 103 Early Hints, CDN preloaders).
 *
 * The middleware short-circuits for non-HTML responses (the body would
 * be opaque) and for routes listed in
 * `artisanpack.performance.resource_hints.exclude_routes` (so admin
 * dashboards, JSON endpoints, and API namespaces can opt out without
 * the application juggling a separate middleware group).
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Middleware;

use ArtisanPackUI\Performance\Output\ResourceHint;
use ArtisanPackUI\Performance\Output\ResourceHintInjector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Resource hint injection middleware.
 *
 *
 * @since      1.0.0
 */
class InjectResourceHints
{
    /**
     * The injector instance shared across middleware invocations.
     *
     * @since 1.0.0
     */
    protected ResourceHintInjector $injector;

    /**
     * Creates a new middleware instance.
     *
     * @since 1.0.0
     *
     * @param  ResourceHintInjector  $injector  Container-resolved injector.
     */
    public function __construct( ResourceHintInjector $injector )
    {
        $this->injector = $injector;
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

        if ( ! $this->shouldInject( $request, $response ) ) {
            $this->injector->clear();

            return $response;
        }

        if ( (bool) config( 'artisanpack.performance.resource_hints.auto_generate', false ) ) {
            $this->autoDetect( $request, $response );
        }

        $hints = $this->injector->all();

        if ( empty( $hints ) ) {
            $this->injector->clear();

            return $response;
        }

        $this->writeLinkHeaders( $response, $hints );
        $this->injectIntoBody( $response, $hints );

        $this->injector->clear();

        return $response;
    }

    /**
     * Reports whether the response should be augmented with hints.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     * @param  Response  $response  Outgoing response.
     */
    protected function shouldInject( Request $request, Response $response ): bool
    {
        if ( ! (bool) config( 'artisanpack.performance.features.resource_hints', false ) ) {
            return false;
        }

        if ( $response instanceof StreamedResponse ) {
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
     * Reports whether the response carries an HTML body.
     *
     * Defaults to true when the `Content-Type` header is missing so
     * Laravel views (which don't always emit a Content-Type until the
     * symphony response is finalized) aren't silently skipped.
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
     * slash) using `Str::is()` semantics so callers can use wildcards
     * like `admin/*` and `api/*`. The exclusion list lives under
     * `resource_hints.exclude_routes`; it falls back to an empty list.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function matchesExcludedRoute( Request $request ): bool
    {
        $patterns = (array) config( 'artisanpack.performance.resource_hints.exclude_routes', [] );

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
     * Scrapes the response body for third-party origins worth hinting.
     *
     * Conservative on purpose — only same-protocol absolute URLs sitting
     * in `src=` or `<link ... href=>` attributes whose host differs from
     * the request host are captured. Anchor `<a href>` is intentionally
     * NOT matched — those are navigation targets, not resources the
     * current page fetches, and preconnecting to every linked social /
     * share domain would crowd out the real hints. Returns no hints
     * when the body isn't a string or when the request lacks a host
     * (CLI/test scenarios).
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request (used to derive the self-host).
     * @param  Response  $response  Outgoing response.
     */
    protected function autoDetect( Request $request, Response $response ): void
    {
        $body = $response->getContent();

        if ( ! is_string( $body ) || '' === $body ) {
            return;
        }

        $selfHost = $request->getHost();

        if ( '' === $selfHost ) {
            return;
        }

        $origins = [];

        // `src=` covers <script>, <img>, <iframe>, <video>, <audio>,
        // <source>, <embed>, <track>, <input type=image>.
        if ( false !== preg_match_all( '/\bsrc="(https?:\/\/[^"\s]+)"/i', $body, $matches ) ) {
            $origins = array_merge( $origins, $matches[1] );
        }

        // `<link ... href=>` is the canonical case for third-party CSS/font
        // preconnect candidates. Restrict to the <link> element so anchor
        // hrefs (navigation, not fetch) don't bleed into hints.
        if ( false !== preg_match_all( '/<link\b[^>]*\bhref="(https?:\/\/[^"\s]+)"/i', $body, $matches ) ) {
            $origins = array_merge( $origins, $matches[1] );
        }

        if ( empty( $origins ) ) {
            return;
        }

        $selfHostLower = strtolower( $selfHost );
        $seenOrigins   = [];

        foreach ( $origins as $url ) {
            $host = parse_url( $url, PHP_URL_HOST );

            if ( ! is_string( $host ) ) {
                continue;
            }

            $hostLower = strtolower( $host );

            if ( $hostLower === $selfHostLower || isset( $seenOrigins[ $hostLower ] ) ) {
                continue;
            }

            $seenOrigins[ $hostLower ] = true;

            $scheme = parse_url( $url, PHP_URL_SCHEME ) ?: 'https';
            $origin = $scheme . '://' . $host;

            try {
                $this->injector->addAutoDetected( new ResourceHint( rel: 'preconnect', href: $origin ) );
            } catch ( Throwable ) {
                // Silently skip malformed origins; they don't belong in the head.
            }
        }
    }

    /**
     * Writes a `Link` header for every hint that fits the header grammar.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     * @param  array<int, ResourceHint>  $hints  Resolved hints to write.
     */
    protected function writeLinkHeaders( Response $response, array $hints ): void
    {
        $values = [];

        foreach ( $hints as $hint ) {
            // Filter out hints whose attributes would break the RFC 8288
            // grammar (CR/LF for header injection, `"` for quoted-string
            // escape, etc.). The HTML pathway is unaffected because
            // `toLinkElement()` HTML-escapes every value before composition.
            if ( ! $hint->isSafeForLinkHeader() ) {
                continue;
            }

            $serialized = $hint->toLinkHeader();

            if ( '' === $serialized ) {
                continue;
            }

            $values[] = $serialized;
        }

        if ( empty( $values ) ) {
            return;
        }

        $existing = $response->headers->get( 'Link' );

        if ( null !== $existing && '' !== trim( $existing ) ) {
            array_unshift( $values, trim( $existing ) );
        }

        $response->headers->set( 'Link', implode( ', ', $values ) );
    }

    /**
     * Injects the resolved hint block into the document `<head>`.
     *
     * Skips quietly when the response body has no `<head>` element so
     * partial-HTML responses (snippets returned to JS callers, JSON-LD
     * payloads inadvertently set to `text/html`) pass through untouched.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     * @param  array<int, ResourceHint>  $hints  Resolved hints to inject.
     */
    protected function injectIntoBody( Response $response, array $hints ): void
    {
        $body = $response->getContent();

        if ( ! is_string( $body ) || '' === $body ) {
            return;
        }

        $position = $this->findHeadCloseTag( $body );

        if ( false === $position ) {
            return;
        }

        $block = '';

        foreach ( $hints as $hint ) {
            $block .= $hint->toLinkElement() . "\n";
        }

        if ( '' === $block ) {
            return;
        }

        $updated = substr( $body, 0, $position ) . $block . substr( $body, $position );

        $response->setContent( $updated );
    }

    /**
     * Locates the document `</head>` close tag, ignoring matches inside scripts.
     *
     * A literal `</head>` substring can appear inside inlined JS/JSON
     * before the real close tag (e.g. `const tmpl = '<div></head>...'`).
     * `stripos` returns the FIRST occurrence and would slice the hint
     * block into the middle of that string literal. Confining the
     * search to the prefix before the first `<body` element keeps the
     * injection anchored to the actual document head.
     *
     * @since 1.0.0
     *
     * @param  string  $body  Response body.
     */
    protected function findHeadCloseTag( string $body ): int|false
    {
        $bodyTag = stripos( $body, '<body' );
        $search  = false === $bodyTag ? $body : substr( $body, 0, $bodyTag );

        // strripos finds the RIGHTMOST occurrence — the actual </head>
        // close tag will always sit closer to <body than any literal
        // '</head>' embedded inside an inline <script>const tmpl = "..."`
        // string earlier in the document.
        return strripos( $search, '</head>');
    }
}
