<?php

/**
 * AiAgentApiController.
 *
 * Single dispatch endpoint pair for the performance package's AI agents.
 * React and Vue frontends POST here with a validated payload; the controller
 * resolves the registered agent, runs it, and returns the structured output.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Controllers\Api;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Performance\Ai\Agents\OptimizationSuggestionAgent;
use ArtisanPackUI\Performance\Ai\Agents\PerformanceInsightAgent;
use ArtisanPackUI\Performance\Http\Requests\Api\Ai\OptimizationSuggestionAiRequest;
use ArtisanPackUI\Performance\Http\Requests\Api\Ai\QueryInsightAiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Handles API requests for the performance package's AI agents.
 *
 * The controller holds a static feature → agent map instead of reflecting
 * through the registry so a caller cannot execute an arbitrary agent class
 * by naming it in a request body.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.1.0
 */
class AiAgentApiController extends Controller
{
    /**
     * Feature-key → agent-class map for this package.
     *
     * @since 1.1.0
     *
     * @var array<string, class-string>
     */
    protected const AGENTS = [
        'performance.query_insight'           => PerformanceInsightAgent::class,
        'performance.optimization_suggestion' => OptimizationSuggestionAgent::class,
    ];

    /**
     * Explain why a query is slow.
     *
     * @since 1.1.0
     */
    public function queryInsight( QueryInsightAiRequest $request ): JsonResponse
    {
        return $this->dispatchAgent( 'performance.query_insight', $request->validated() );
    }

    /**
     * Suggest portfolio-level optimizations from a metrics batch.
     *
     * @since 1.1.0
     */
    public function optimizationSuggestion( OptimizationSuggestionAiRequest $request ): JsonResponse
    {
        return $this->dispatchAgent( 'performance.optimization_suggestion', $request->validated() );
    }

    /**
     * Run the agent associated with `$featureKey` against `$input`.
     *
     * The registry-toggle short-circuit returns a 409 with the feature key so
     * frontends can render a "This feature is disabled" state without needing
     * to parse the exception message. Missing credentials return 412; validation
     * failures inside the agent return 422; anything else is a 500.
     *
     * @since 1.1.0
     *
     * @param  string                $featureKey  Fully-qualified feature key.
     * @param  array<string, mixed>  $input       Validated request payload.
     *
     * @return JsonResponse
     */
    protected function dispatchAgent( string $featureKey, array $input ): JsonResponse
    {
        if ( ! isset( self::AGENTS[ $featureKey ] ) ) {
            return response()->json( [
                'message' => __( 'Unknown AI feature.' ),
            ], 404 );
        }

        // Belt-and-braces authorization: the route middleware already
        // requires an authenticated caller, but the `performance.ai.use`
        // Gate is what quota / role policies hook into. Runs on every
        // dispatch even if a host removes auth middleware.
        if ( ! Gate::allows( 'performance.ai.use' ) ) {
            return response()->json( [
                'message'     => __( 'You are not permitted to use this AI feature.' ),
                'feature_key' => $featureKey,
            ], 403 );
        }

        $registry = app( FeatureRegistry::class );

        if ( null !== $registry->get( $featureKey ) && ! $registry->isToggleOn( $featureKey ) ) {
            return response()->json( [
                'message'     => __( 'This AI feature is disabled.' ),
                'feature_key' => $featureKey,
            ], 409 );
        }

        $agentClass = self::AGENTS[ $featureKey ];

        try {
            $output = $agentClass::for( $input )->run();
        } catch ( FeatureDisabledException $exception ) {
            return response()->json( [
                'message'     => $exception->getMessage(),
                'feature_key' => $featureKey,
            ], 409 );
        } catch ( MissingCredentialsException $exception ) {
            return response()->json( [
                'message'     => $exception->getMessage(),
                'feature_key' => $featureKey,
            ], 412 );
        } catch ( FeatureError $exception ) {
            return response()->json( [
                'message'     => $exception->getMessage(),
                'feature_key' => $featureKey,
            ], 422 );
        } catch ( Throwable $exception ) {
            // The generic 500 hides provider errors, DB failures, and
            // upstream laravel/ai bugs behind the same message, so ops
            // needs the trace in the log to tell them apart.
            report( $exception );

            return response()->json( [
                'message'     => __( 'AI agent failed to run.' ),
                'feature_key' => $featureKey,
            ], 500 );
        }

        return response()->json( [
            'data'        => $output,
            'feature_key' => $featureKey,
        ] );
    }
}
