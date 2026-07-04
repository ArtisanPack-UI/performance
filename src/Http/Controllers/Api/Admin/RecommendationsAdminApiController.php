<?php

/**
 * RecommendationsAdminApiController — recommendation list + actions.
 *
 * Backs the React/Vue RecommendationsPanel port. Reads the ranked
 * recommendation list from `RecommendationEngine`, tracks dismissals in
 * the session (same key as the Livewire panel so users see a consistent
 * "dismissed" state across both surfaces), and dispatches the same
 * one-click actions Livewire runs internally.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Controllers\Api\Admin;

use ArtisanPackUI\Performance\Cache\PageCacheManager;
use ArtisanPackUI\Performance\Monitoring\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Throwable;

/**
 * Recommendations admin API controller.
 *
 *
 * @since      1.0.0
 */
class RecommendationsAdminApiController extends AdminApiController
{
    public const DISMISSAL_KEY = 'artisanpack.performance.dismissed_recommendations';

    public const RANGE_KEYS = ['24h', '7d', '30d', '90d'];

    /**
     * GET /api/performance/admin/recommendations.
     *
     * @since 1.0.0
     */
    public function index( Request $request ): JsonResponse
    {
        $this->authorizeAdmin();

        $range     = $this->resolveRange( (string) $request->query( 'range', '7d' ) );
        $items     = app( RecommendationEngine::class )->build( $range );
        $dismissed = $this->readDismissed();

        $visible = array_values( array_filter(
            $items,
            static fn ( array $item ): bool => ! in_array( (string) ( $item['id'] ?? '' ), $dismissed, true ),
        ) );

        return response()->json( [
            'items'     => $visible,
            'dismissed' => $dismissed,
        ] );
    }

    /**
     * POST /api/performance/admin/recommendations/actions.
     *
     * @since 1.0.0
     */
    public function actions( Request $request ): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate( [
            'action' => 'required|in:apply,dismiss,reset',
            'id'     => 'sometimes|string|max:200',
            'range'  => 'sometimes|string|max:10',
        ] );

        $range   = $this->resolveRange( (string) ( $validated['range'] ?? '7d' ) );
        $items   = app( RecommendationEngine::class )->build( $range );
        $isError = false;

        switch ( $validated['action'] ) {
            case 'dismiss':
                $id = trim( (string) ( $validated['id'] ?? '' ) );

                if ( '' === $id ) {
                    $isError = true;
                    $message = __( 'A recommendation id is required.' );
                    break;
                }

                $this->addDismissal( $id );
                $message = __( 'Recommendation dismissed.' );
                break;

            case 'reset':
                Session::forget( self::DISMISSAL_KEY );
                $message = __( 'Restored dismissed recommendations.' );
                break;

            case 'apply':
                $id             = trim( (string) ( $validated['id'] ?? '' ) );
                $recommendation = null;

                foreach ( $items as $item ) {
                    if ( (string) ( $item['id'] ?? '' ) === $id ) {
                        $recommendation = $item;
                        break;
                    }
                }

                if ( null === $recommendation ) {
                    $isError = true;
                    $message = __( 'Recommendation is no longer available.' );
                    break;
                }

                $action = (string) ( $recommendation['action'] ?? '' );

                if ( '' === $action ) {
                    $isError = true;
                    $message = __( 'This recommendation has no one-click fix; follow the manual steps.' );
                    break;
                }

                try {
                    [$message, $isError] = $this->runAction( $action, $recommendation );
                } catch ( Throwable $e ) {
                    $isError = true;
                    $message = __( 'Action failed: :message', ['message' => $e->getMessage()] );
                }
                break;

            default:
                $isError = true;
                $message = __( 'Unknown action.' );
        }

        $dismissed = $this->readDismissed();

        $visible = array_values( array_filter(
            $items,
            static fn ( array $item ): bool => ! in_array( (string) ( $item['id'] ?? '' ), $dismissed, true ),
        ) );

        return response()->json( [
            'action'    => $validated['action'],
            'message'   => (string) $message,
            'is_error'  => $isError,
            'items'     => $visible,
            'dismissed' => $dismissed,
        ] );
    }

    /**
     * Runs one of the engine's declared one-click actions.
     *
     * @since 1.0.0
     *
     * @param  string  $action  Action name from the recommendation.
     * @param  array<string, mixed>  $recommendation  The full recommendation payload.
     *
     * @return array{0: string, 1: bool} Message + is-error pair.
     */
    protected function runAction( string $action, array $recommendation ): array
    {
        return match ( $action ) {
            'warm-cache'               => $this->runWarmCache(),
            'generate-index-migration' => $this->runGenerateIndexMigration( $recommendation ),
            'view-query-analyzer'      => [(string) __( 'Open the query analyzer to review slow queries.' ), false],
            default                    => [(string) __( 'Unknown action: :action', ['action' => $action] ), true],
        };
    }

    /**
     * @return array{0: string, 1: bool}
     */
    protected function runWarmCache(): array
    {
        $urls = (array) config( 'artisanpack.performance.cache_warming.urls', [] );
        $urls = array_values( array_filter( $urls, 'is_string' ) );

        if ( [] === $urls ) {
            return [(string) __( 'No cache_warming.urls configured.' ), true];
        }

        $results = app( PageCacheManager::class )->warmPageCache( $urls );
        $ok      = 0;

        foreach ( $results as $entry ) {
            if ( is_array( $entry ) && true === ( $entry['ok'] ?? false ) ) {
                $ok++;
            }
        }

        return [
            (string) __( 'Warmed :count of :total URLs.', ['count' => $ok, 'total' => count( $results )] ),
            false,
        ];
    }

    /**
     * @param  array<string, mixed>  $recommendation
     *
     * @return array{0: string, 1: bool}
     */
    protected function runGenerateIndexMigration( array $recommendation ): array
    {
        $payload = (array) ( $recommendation['action_payload'] ?? [] );
        $table   = (string) ( $payload['table'] ?? '' );
        $columns = array_values( (array) ( $payload['columns'] ?? [] ) );

        Event::dispatch(
            'performance:generate-index-migration',
            [
                'table'   => $table,
                'columns' => $columns,
            ],
        );

        return [
            (string) __( 'Requested index migration generation for :table.', ['table' => $table] ),
            false,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function readDismissed(): array
    {
        return array_values( array_filter(
            (array) Session::get( self::DISMISSAL_KEY, [] ),
            'is_string',
        ) );
    }

    protected function addDismissal( string $id ): void
    {
        $current = $this->readDismissed();

        if ( ! in_array( $id, $current, true ) ) {
            $current[] = $id;
        }

        Session::put( self::DISMISSAL_KEY, $current);
    }

    protected function resolveRange( string $range): string
    {
        return in_array( $range, self::RANGE_KEYS, true) ? $range : '7d';
    }
}
