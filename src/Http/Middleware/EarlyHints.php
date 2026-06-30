<?php

/**
 * HTTP 103 Early Hints middleware.
 *
 * Emits an interim `103 Early Hints` response carrying `Link` preload
 * headers so a 103-aware client (Chrome 103+, Firefox 120+, Safari
 * Tech Preview) can begin fetching critical assets while the main
 * response is still being computed. The interim response is sent
 * BEFORE `$next($request)` runs so the latency win is real — sending
 * 103 after the controller finishes saves nothing.
 *
 * PHP itself has no first-class interim-response API. The middleware
 * uses `header()` with a raw `HTTP/1.1 103 Early Hints` status line
 * plus repeated `Link:` headers, then either calls a SAPI-specific
 * flush primitive (`fastcgi_finish_request` for FPM, `flush()` for
 * the rest) or relies on the web server to translate the queued
 * headers (NGINX 1.13+ with `early_hints on;`, Apache 2.4.42+ with
 * `H2EarlyHints on`). Either way the call is best-effort: any SAPI
 * that won't honor 103 silently no-ops without breaking the request.
 *
 * The middleware ALSO mirrors the resolved hints into the final
 * response's `Link` header so 103-unaware intermediaries (older
 * caches, varnish without the feature, simple reverse proxies) still
 * see the preload metadata in a form they can use.
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
use Throwable;

/**
 * Early Hints middleware.
 *
 *
 * @since      1.0.0
 */
class EarlyHints
{
    /**
     * Hint registry shared with the resource-hint pipeline.
     *
     * @since 1.0.0
     */
    protected ResourceHintInjector $injector;

    /**
     * Optional SAPI emitter override.
     *
     * When null the middleware uses {@see defaultEmitter()} which
     * calls `header()` + `flush()`. Tests override this to a
     * recording closure so they don't depend on the SAPI being
     * available under PHPUnit.
     *
     * @since 1.0.0
     *
     * @var (callable(array<int, string>): void)|null
     */
    protected $emitter;

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
        if ( ! $this->isEnabled() ) {
            return $next( $request );
        }

        $hints = $this->resolveHints();

        if ( ! empty( $hints ) ) {
            $this->emitEarlyHints( $hints );
        }

        $response = $next( $request );

        if ( ! empty( $hints ) ) {
            $this->mirrorIntoResponse( $response, $hints );
        }

        return $response;
    }

    /**
     * Overrides the SAPI emitter — used by tests to capture payloads.
     *
     * Passing null restores the default `header()` + `flush()`
     * emitter. The override is process-wide for the middleware
     * instance, so tests that swap it should restore it in a `tearDown`
     * to avoid bleed-through.
     *
     * @since 1.0.0
     *
     * @param  (callable(array<int, string>): void)|null  $emitter  Replacement emitter or null to reset.
     */
    public function setEmitter( ?callable $emitter ): void
    {
        $this->emitter = $emitter;
    }

    /**
     * Reports whether the middleware should run at all this request.
     *
     * @since 1.0.0
     */
    protected function isEnabled(): bool
    {
        return (bool) config( 'artisanpack.performance.early_hints.enabled', false );
    }

    /**
     * Resolves the hints to send, merging manual config with auto-detected.
     *
     * Config-supplied `manual_hints` (a list of associative arrays
     * shaped like `ResourceHint::fromConfigEntry()` accepts) come
     * first. When `auto_detect` is enabled the registered
     * `ResourceHintInjector` is drained for any already-known hints
     * (preloads/preconnects set by directives or upstream middleware).
     * Returned hints are deduplicated by `(rel, href, as)` so a hint
     * registered both manually and by a directive doesn't double-send.
     *
     * @since 1.0.0
     *
     * @return array<int, ResourceHint>
     */
    protected function resolveHints(): array
    {
        $hints = [];
        $seen  = [];

        $append = static function ( ResourceHint $hint ) use ( &$hints, &$seen ): void {
            $key = $hint->dedupKey();

            if ( isset( $seen[ $key ] ) ) {
                return;
            }

            $seen[ $key ] = true;
            $hints[]      = $hint;
        };

        foreach ( $this->manualHints() as $hint ) {
            $append( $hint );
        }

        if ( (bool) config( 'artisanpack.performance.early_hints.auto_detect', true ) ) {
            foreach ( $this->autoDetectedHints() as $hint ) {
                $append( $hint );
            }
        }

        return $hints;
    }

    /**
     * Returns the hints explicitly listed in `early_hints.manual_hints`.
     *
     * Each entry may be a string (treated as an href with `rel=preload`)
     * or an associative array matching the shape
     * `ResourceHint::fromConfigEntry()` understands. Entries that fail
     * coercion are silently dropped — a bad entry shouldn't poison the
     * whole list.
     *
     * @since 1.0.0
     *
     * @return array<int, ResourceHint>
     */
    protected function manualHints(): array
    {
        $entries = (array) config( 'artisanpack.performance.early_hints.manual_hints', [] );
        $hints   = [];

        foreach ( $entries as $entry ) {
            if ( ! is_string( $entry ) && ! is_array( $entry ) ) {
                continue;
            }

            $rel = is_array( $entry ) ? ( $entry['rel'] ?? 'preload' ) : 'preload';

            $hint = ResourceHint::fromConfigEntry( (string) $rel, $entry );

            if ( null !== $hint ) {
                $hints[] = $hint;
            }
        }

        return $hints;
    }

    /**
     * Returns preload/preconnect hints already registered with the injector.
     *
     * Early Hints only carries meaning for resource fetches the
     * browser would otherwise discover later — preload, preconnect,
     * dns-prefetch. Prefetch hints are skipped here because they're
     * navigation-time, not load-time, optimizations.
     *
     * @since 1.0.0
     *
     * @return array<int, ResourceHint>
     */
    protected function autoDetectedHints(): array
    {
        return $this->injector->all( [ 'preload', 'preconnect', 'dns-prefetch' ] );
    }

    /**
     * Hands the resolved hints off to the SAPI emitter.
     *
     * Filters hints that would break the RFC 8288 grammar (CR/LF
     * injection, embedded quotes) so a malformed config value can't
     * smuggle a second response into the wire. When every hint is
     * unsafe the emitter receives an empty payload and the call
     * no-ops.
     *
     * @since 1.0.0
     *
     * @param  array<int, ResourceHint>  $hints  Resolved hints.
     */
    protected function emitEarlyHints( array $hints ): void
    {
        $payload = [ 'HTTP/1.1 103 Early Hints' ];

        foreach ( $hints as $hint ) {
            if ( ! $hint->isSafeForLinkHeader() ) {
                continue;
            }

            $serialized = $hint->toLinkHeader();

            if ( '' === $serialized ) {
                continue;
            }

            $payload[] = 'Link: ' . $serialized;
        }

        if ( 1 === count( $payload ) ) {
            // Only the status line — nothing useful to send. Skip the
            // emitter call so we don't queue a hint-less 103.
            return;
        }

        $emitter = $this->emitter ?? $this->defaultEmitter();

        try {
            $emitter( $payload );
        } catch ( Throwable ) {
            // Any SAPI that refuses our 103 attempt is a no-op for us.
            // The mirrored response headers (see mirrorIntoResponse)
            // still give downstream intermediaries the same data.
        }
    }

    /**
     * Returns the default SAPI emitter (`header()` + flush).
     *
     * Skips silently when running under the CLI SAPI (PHPUnit,
     * `artisan serve`, queue workers — no HTTP layer to push the 103
     * onto) and when headers have already been sent (the moment the
     * framework starts writing the body it's too late to slip a 103
     * ahead of it). On supported SAPIs the default relies on the
     * surrounding web server to translate the queued headers into a
     * real 103 response (NGINX `early_hints on;`, Apache
     * `H2EarlyHints on`).
     *
     * @since 1.0.0
     *
     * @return callable(array<int, string>): void
     */
    protected function defaultEmitter(): callable
    {
        return static function ( array $payload ): void {
            if ( 'cli' === PHP_SAPI ) {
                return;
            }

            if ( headers_sent() ) {
                return;
            }

            $first = true;

            foreach ( $payload as $line ) {
                if ( $first ) {
                    header( $line );
                    $first = false;

                    continue;
                }

                // `false` so multiple `Link:` headers accumulate
                // rather than overwriting one another.
                header( $line, false );
            }

            if ( function_exists( 'fastcgi_finish_request' ) ) {
                // FPM-specific: forces the partial response onto the
                // wire without ending the script. Without this the
                // 103 sits in PHP's output buffer until the main
                // response is composed, defeating the point.
                return;
            }

            if ( function_exists( 'flush' ) ) {
                @flush();
            }
        };
    }

    /**
     * Copies the same hints into the final response as `Link` headers.
     *
     * Belt-and-suspenders for 103-unaware intermediaries: even if the
     * interim response never reaches the client, the preload metadata
     * still ships in the 200 response where any cache or
     * server-side push translator can pick it up. Existing `Link`
     * headers on the response are preserved so we don't clobber
     * hints set by upstream middleware (e.g. `InjectResourceHints`).
     *
     * @since 1.0.0
     *
     * @param  Response  $response  Outgoing response.
     * @param  array<int, ResourceHint>  $hints  Resolved hints.
     */
    protected function mirrorIntoResponse( Response $response, array $hints ): void
    {
        $values = [];

        foreach ( $hints as $hint ) {
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
}
