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
                $result  = $invalidator->purgeAll();
                $message = sprintf(
                    'Purged %d page cache entr(y|ies) and %d fragment tag(s).',
                    (int) ( $result['page'] ?? 0 ),
                    (int) ( $result['fragments'] ?? 0 ),
                );
                break;

            case 'warm':
                $manager = app( PageCacheManager::class );
                $urls    = (array) config( 'artisanpack.performance.cache.cache_warming.urls', [] );
                $urls    = array_values( array_filter( $urls, 'is_string' ) );

                if ( [] === $urls ) {
                    $isError = true;
                    $message = 'No warm-cache URLs are configured.';
                    break;
                }

                $results = $manager->warmPageCache( $urls );
                $ok      = 0;

                foreach ( $results as $entry ) {
                    if ( true === ( $entry['ok'] ?? false ) ) {
                        $ok++;
                    }
                }

                $message = sprintf( 'Warmed %d of %d URL(s).', $ok, count( $urls ) );
                break;

            case 'invalidate-key':
                $key = trim( (string) ( $validated['key'] ?? '' ) );

                if ( '' === $key ) {
                    $isError = true;
                    $message = 'A cache key or pattern is required.';
                    break;
                }

                $count   = $invalidator->invalidatePagePattern( $key );
                $message = sprintf( 'Invalidated %d matching entr(y|ies).', $count );
                break;

            case 'invalidate-tag':
                $tag = trim( (string) ( $validated['tag'] ?? '' ) );

                if ( '' === $tag ) {
                    $isError = true;
                    $message = 'A fragment tag is required.';
                    break;
                }

                $count   = $invalidator->invalidateFragmentTag( $tag );
                $message = sprintf( 'Invalidated %d fragment(s) tagged "%s".', $count, $tag );
                break;

            default:
                $isError = true;
                $message = 'Unknown cache action.';
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
