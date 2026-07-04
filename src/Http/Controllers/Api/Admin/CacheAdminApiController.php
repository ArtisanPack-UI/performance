<?php

/**
 * CacheAdminApiController — page + fragment cache read + mutation endpoints.
 *
 * Backs the React/Vue CacheManager port. Mirrors the actions on the
 * Livewire `CacheManager` component: list, invalidate by key/pattern,
 * invalidate by tag, flush, warm.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Controllers\Api\Admin;

use ArtisanPackUI\Performance\Cache\CacheInvalidator;
use ArtisanPackUI\Performance\Cache\CacheStatistics;
use ArtisanPackUI\Performance\Cache\PageCacheManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cache admin API controller.
 *
 *
 * @since      1.0.0
 */
class CacheAdminApiController extends AdminApiController
{
    /**
     * GET /api/performance/admin/cache — read-only snapshot.
     *
     * @since 1.0.0
     */
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json( $this->snapshot() );
    }

    /**
     * POST /api/performance/admin/cache/actions — mutate the cache.
     *
     * @since 1.0.0
     */
    public function actions( Request $request ): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate( [
            'action' => 'required|in:flush,warm,invalidate-key,invalidate-tag',
            'key'    => 'sometimes|string|max:500',
            'tag'    => 'sometimes|string|max:500',
        ] );

        $invalidator = app( CacheInvalidator::class );
        $isError     = false;

        switch ( $validated['action'] ) {
            case 'flush':
                $result   = $invalidator->purgeAll();
                $pageOut  = (int) ( $result['page'] ?? 0 );
                $fragOut  = (int) ( $result['fragments'] ?? 0 );
                $pageText = (string) trans_choice(
                    ':count page cache entry|:count page cache entries',
                    $pageOut,
                    [ 'count' => $pageOut ],
                );
                $fragText = (string) trans_choice(
                    ':count fragment tag|:count fragment tags',
                    $fragOut,
                    [ 'count' => $fragOut ],
                );
                $message  = (string) __( 'Purged :page and :fragments.', [ 'page' => $pageText, 'fragments' => $fragText ] );
                break;

            case 'warm':
                $manager = app( PageCacheManager::class );
                $urls    = (array) config( 'artisanpack.performance.cache_warming.urls', [] );
                $urls    = array_values( array_filter( $urls, 'is_string' ) );

                if ( [] === $urls ) {
                    $isError = true;
                    $message = (string) __( 'No warm-cache URLs are configured.' );
                    break;
                }

                $results = $manager->warmPageCache( $urls );
                $ok      = 0;

                foreach ( $results as $entry ) {
                    if ( is_array( $entry ) && true === ( $entry['ok'] ?? false ) ) {
                        $ok++;
                    }
                }

                $message = (string) trans_choice(
                    'Warmed :count of :total URL.|Warmed :count of :total URLs.',
                    count( $results ),
                    [ 'count' => $ok, 'total' => count( $results ) ],
                );
                break;

            case 'invalidate-key':
                $key = trim( (string) ( $validated['key'] ?? '' ) );

                if ( '' === $key ) {
                    $isError = true;
                    $message = (string) __( 'A cache key or pattern is required.' );
                    break;
                }

                $count   = $invalidator->invalidatePagePattern( $key );
                $message = (string) trans_choice(
                    'Invalidated :count matching entry.|Invalidated :count matching entries.',
                    $count,
                    [ 'count' => $count ],
                );
                break;

            case 'invalidate-tag':
                $tag = trim( (string) ( $validated['tag'] ?? '' ) );

                if ( '' === $tag ) {
                    $isError = true;
                    $message = (string) __( 'A fragment tag is required.' );
                    break;
                }

                $count   = $invalidator->invalidateFragmentTag( $tag );
                $message = (string) trans_choice(
                    'Invalidated :count fragment tagged ":tag".|Invalidated :count fragments tagged ":tag".',
                    $count,
                    [ 'count' => $count, 'tag' => $tag ],
                );
                break;

            default:
                $isError = true;
                $message = (string) __( 'Unknown cache action.' );
        }

        return response()->json( array_merge(
            [
                'action'   => $validated['action'],
                'message'  => $message,
                'is_error' => $isError,
            ],
            $this->snapshot(),
        ) );
    }

    /**
     * Build the cache snapshot payload.
     *
     * @since 1.0.0
     *
     * @return array{summary: array<string, mixed>, page_entries: array<int, mixed>, fragment_tags: array<int, mixed>}
     */
    protected function snapshot(): array
    {
        $statistics = app( CacheStatistics::class );

        return [
            'summary' => [
                'page'     => $statistics->pageSummary(),
                'fragment' => $statistics->fragmentSummary(),
            ],
            'page_entries'  => $statistics->pageEntries(),
            'fragment_tags' => $statistics->fragmentTags(),
        ];
    }
}
