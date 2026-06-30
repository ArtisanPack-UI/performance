<?php

/**
 * Metrics collection API controller.
 *
 * Accepts Core Web Vitals samples posted by the bundled
 * `web-vitals.js` bootstrap (or any compatible client) and persists
 * them to the `performance_raw_metrics` table for later aggregation.
 * Persistence is gated by the `monitoring.store_raw_metrics` flag, so
 * applications that only consume aggregated percentiles can leave the
 * endpoint enabled without growing the raw table.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Controllers\Api;

use ArtisanPackUI\Performance\Models\RawMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Metrics API controller class.
 *
 *
 * @since      1.0.0
 */
class MetricsApiController extends Controller
{
    /**
     * The metric names the endpoint accepts.
     *
     * The web-vitals library reports five Core Web Vitals plus a handful
     * of legacy/companion metrics. Anything outside this allowlist is
     * rejected so a misconfigured client cannot pollute the table with
     * unbounded metric names.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const ALLOWED_METRICS = [
        'LCP',
        'FID',
        'INP',
        'CLS',
        'TTFB',
        'FCP',
    ];

    /**
     * Persists a single metric sample.
     *
     * Validates the payload, applies sampling (so applications can throttle
     * a busy production endpoint without overhauling instrumentation), and
     * writes a row to `performance_raw_metrics` when storage is enabled.
     * Returns a small JSON body the client can use for retry logic.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The incoming JSON request.
     */
    public function store( Request $request ): JsonResponse
    {
        if ( false === (bool) config( 'artisanpack.performance.monitoring.enabled', false ) ) {
            return response()->json( [
                'success' => false,
                'reason'  => 'monitoring-disabled',
            ], 403 );
        }

        // Mirror the gate that suppresses `@perfMonitor` script output.
        // Without this check, HTML pages cached before the operator
        // disabled RUM keep posting beacons that the endpoint would
        // happily persist, defeating the opt-out.
        if ( false === (bool) config( 'artisanpack.performance.monitoring.collect_web_vitals', true ) ) {
            return response()->json( [
                'success' => false,
                'reason'  => 'web-vitals-disabled',
            ], 403 );
        }

        $noControlChars = static function ( $attribute, $value, $fail ): void {
            // The aggregator packs (route, metric, device, connection)
            // into a single string with the ASCII Unit Separator
            // (`\x1F`) as a delimiter; allowing control chars here
            // would let a client smuggle a separator through and
            // poison the aggregation buckets. Reject anything below
            // 0x20 except whitespace.
            if ( is_string( $value ) && preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
                $fail( "The {$attribute} field contains disallowed control characters." );
            }
        };

        $validated = $request->validate( [
            'name'       => [ 'required', 'string', 'max:32', $noControlChars ],
            'value'      => [ 'required', 'numeric' ],
            'delta'      => [ 'sometimes', 'nullable', 'numeric' ],
            'id'         => [ 'sometimes', 'nullable', 'string', 'max:64', $noControlChars ],
            'rating'     => [ 'sometimes', 'nullable', 'string', 'max:32', $noControlChars ],
            'page'       => [ 'sometimes', 'nullable', 'string', 'max:2048', $noControlChars ],
            'route'      => [ 'sometimes', 'nullable', 'string', 'max:191', $noControlChars ],
            'device'     => [ 'sometimes', 'nullable', 'string', 'max:32', $noControlChars ],
            'deviceType' => [ 'sometimes', 'nullable', 'string', 'max:32', $noControlChars ],
            'connection' => [ 'sometimes', 'nullable', 'string', 'max:32', $noControlChars ],
            'extra'      => [ 'sometimes', 'nullable', 'array' ],
        ] );

        $name = (string) ( $validated['name'] ?? '' );

        if ( ! in_array( $name, self::ALLOWED_METRICS, true ) ) {
            return response()->json( [
                'success' => false,
                'reason'  => 'unknown-metric',
            ], 422 );
        }

        if ( ! $this->passesSampleGate() ) {
            // Sampling is intentionally silent — clients should not retry a
            // sample dropped on purpose, so the API returns a 200 with a
            // success=false body the client can ignore.
            return response()->json( [
                'success' => false,
                'reason'  => 'sampled-out',
            ] );
        }

        if ( false === (bool) config( 'artisanpack.performance.monitoring.store_raw_metrics', false ) ) {
            // Raw storage is opt-in; when off the endpoint still validates
            // the payload so misconfigured clients surface a 422 immediately
            // instead of silently succeeding everywhere.
            return response()->json( [ 'success' => true ] );
        }

        try {
            RawMetric::create( $this->buildAttributes( $validated, $request ) );
        } catch ( Throwable $e ) {
            return response()->json( [
                'success' => false,
                'reason'  => 'storage-failed',
            ], 500 );
        }

        return response()->json( [ 'success' => true ] );
    }

    /**
     * Builds the model attribute payload from the validated request.
     *
     * Splits the URL into `url` (full path supplied by the client) and
     * `route` (the resolved Laravel route name, when the client passes one
     * — otherwise null) so the aggregator can group by either dimension.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $validated  Validated input.
     * @param  Request  $request  Incoming request used for client metadata.
     *
     * @return array<string, mixed>
     */
    protected function buildAttributes( array $validated, Request $request ): array
    {
        $page      = isset( $validated['page'] ) ? (string) $validated['page'] : null;
        $userAgent = $request->header( 'User-Agent' );

        // The bundled `web-vitals.js` posts `deviceType`; older
        // hand-rolled clients tend to use `device`. Accept both so a
        // package upgrade doesn't break clients that hard-coded the
        // legacy field name. `deviceType` wins when both arrive.
        $deviceType = $validated['deviceType']
            ?? $validated['device']
            ?? null;

        $extra = $validated['extra'] ?? null;

        return [
            'name'            => (string) $validated['name'],
            'value'           => (float) $validated['value'],
            'delta'           => isset( $validated['delta'] ) ? (float) $validated['delta'] : null,
            'rating'          => isset( $validated['rating'] ) ? (string) $validated['rating'] : null,
            'vital_id'        => isset( $validated['id'] ) ? (string) $validated['id'] : null,
            'url'             => $page,
            'route'           => isset( $validated['route'] ) ? (string) $validated['route'] : null,
            'device_type'     => null === $deviceType ? null : (string) $deviceType,
            'connection_type' => isset( $validated['connection'] ) ? (string) $validated['connection'] : null,
            'extra'           => is_array( $extra ) && ! empty( $extra ) ? $extra : null,
            'user_agent'      => is_string( $userAgent ) ? $userAgent : null,
            'recorded_at'     => Carbon::now(),
        ];
    }

    /**
     * Decides whether to accept the current sample based on the configured rate.
     *
     * The configured `sample_rate` is expressed as a 0-100 percentage; a
     * value of 100 (the default) accepts every sample, 0 rejects every
     * sample, and intermediate values use `random_int()` to make an
     * unbiased decision. The implementation prefers `random_int()` over
     * `mt_rand()` so test seeding tools that mock it intercept the same
     * function the application would otherwise use.
     *
     * @since 1.0.0
     */
    protected function passesSampleGate(): bool
    {
        $rate = (int) config( 'artisanpack.performance.monitoring.sample_rate', 100 );

        if ( $rate >= 100 ) {
            return true;
        }

        if ( $rate <= 0 ) {
            return false;
        }

        try {
            return random_int( 1, 100 ) <= $rate;
        } catch ( Throwable ) {
            // Fall back to the deterministic-on-failure path: accept the
            // sample. Better to over-collect than to silently drop on a
            // RNG failure that's likely transient.
            return true;
        }
    }
}
