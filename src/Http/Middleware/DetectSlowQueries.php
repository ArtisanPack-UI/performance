<?php

/**
 * Detect slow queries middleware.
 *
 * Enables the QueryAnalyzer, N1Detector, and SlowQueryLogger at the
 * start of each request so detection runs across the full controller
 * + middleware pipeline. The middleware is the canonical wiring point
 * for applications that want per-request detection without manually
 * calling `enable()` on every service inside a service provider's
 * boot method.
 *
 * Routes listed in `database.detection.exclude_routes` (falling back
 * to `resource_hints.exclude_routes` to keep configuration ergonomic)
 * skip detection setup entirely so admin endpoints that run heavy
 * reporting queries don't fire N+1 / slow-query alerts in developer
 * environments.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Middleware;

use ArtisanPackUI\Performance\Database\N1Detector;
use ArtisanPackUI\Performance\Database\QueryAnalyzer;
use ArtisanPackUI\Performance\Database\SlowQueryLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detect slow queries middleware.
 *
 *
 * @since      1.0.0
 */
class DetectSlowQueries
{
    /**
     * The QueryAnalyzer used to capture executed SQL.
     *
     * @since 1.0.0
     */
    protected QueryAnalyzer $analyzer;

    /**
     * The N+1 detector used to surface repeated signatures.
     *
     * @since 1.0.0
     */
    protected N1Detector $n1Detector;

    /**
     * The slow query logger used to persist long-running queries.
     *
     * @since 1.0.0
     */
    protected SlowQueryLogger $slowQueryLogger;

    /**
     * Creates a new middleware instance.
     *
     * @since 1.0.0
     *
     * @param  QueryAnalyzer  $analyzer  Container-resolved analyzer.
     * @param  N1Detector  $n1Detector  Container-resolved N+1 detector.
     * @param  SlowQueryLogger  $slowQueryLogger  Container-resolved logger.
     */
    public function __construct(
        QueryAnalyzer $analyzer,
        N1Detector $n1Detector,
        SlowQueryLogger $slowQueryLogger,
    ) {
        $this->analyzer        = $analyzer;
        $this->n1Detector      = $n1Detector;
        $this->slowQueryLogger = $slowQueryLogger;
    }

    /**
     * Handles the request.
     *
     * Detection is enabled BEFORE the next handler runs so the queries
     * issued by controllers are observed from the very first one. The
     * route name is stamped onto the N+1 detector at the same time so
     * dispatched events carry route context — the route binding has
     * been resolved by the time the route-stack middleware runs.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The incoming request.
     * @param  Closure  $next  Next middleware in the pipeline.
     */
    public function handle( Request $request, Closure $next ): Response
    {
        if ( ! $this->shouldDetect( $request ) ) {
            return $next( $request );
        }

        $this->analyzer->enableQueryLogging();
        $this->n1Detector->enable();
        $this->slowQueryLogger->enable();
        $this->n1Detector->setCurrentRoute( $this->resolveRouteLabel( $request ) );

        return $next( $request );
    }

    /**
     * Reports whether the request should trigger detection setup.
     *
     * Detection is opt-in by feature flag — `query_optimization` covers
     * the analyzer + N+1 detector, while `slow_query_logging.enabled`
     * gates the slow-query logger. Either being on is enough to fire
     * up the middleware; a request matching the exclude list short-
     * circuits both regardless.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function shouldDetect( Request $request ): bool
    {
        $queryOptOn = (bool) config( 'artisanpack.performance.features.query_optimization', false );
        $slowOptOn  = (bool) config( 'artisanpack.performance.database.slow_query_logging.enabled', false );

        if ( ! $queryOptOn && ! $slowOptOn ) {
            return false;
        }

        return ! $this->matchesExcludedRoute( $request );
    }

    /**
     * Matches the request path against configured exclusion patterns.
     *
     * Looks first at `database.detection.exclude_routes`; falls back to
     * `resource_hints.exclude_routes` when the dedicated key is empty
     * so applications that already exclude admin/api namespaces don't
     * have to repeat themselves.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function matchesExcludedRoute( Request $request ): bool
    {
        $patterns = (array) config( 'artisanpack.performance.database.detection.exclude_routes', [] );

        if ( [] === $patterns ) {
            $patterns = (array) config( 'artisanpack.performance.resource_hints.exclude_routes', [] );
        }

        if ( [] === $patterns ) {
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
     * Shell-style wildcard match.
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
     * Resolves a label for the request's matched route.
     *
     * Prefers a named route, falling back to the URI template, then the
     * raw request path so the dispatched event payload always carries
     * SOMETHING actionable for triage.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  Incoming request.
     */
    protected function resolveRouteLabel( Request $request ): string
    {
        $route = $request->route();

        if ( null === $route ) {
            return $request->path();
        }

        $name = method_exists( $route, 'getName' ) ? $route->getName() : null;

        if ( null !== $name && '' !== $name ) {
            return (string) $name;
        }

        if ( method_exists( $route, 'uri' ) ) {
            $uri = $route->uri();

            if ( '' !== $uri ) {
                return $uri;
            }
        }

        return $request->path();
    }
}
