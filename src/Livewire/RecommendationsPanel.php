<?php

/**
 * Recommendations panel Livewire component.
 *
 * Renders the ranked recommendation list produced by the
 * {@see RecommendationEngine}. Supports dismissing individual
 * recommendations (session-scoped by default) and exposes a small
 * set of "one-click" action handlers for cases where the fix is
 * safe to apply from the dashboard.
 *
 * The panel is intentionally thin — the ranking, filtering, and data
 * shaping live in the engine so the panel can be rebuilt against a
 * different UI (Filament, Nova, custom) without duplicating logic.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Livewire;

use ArtisanPackUI\Performance\Monitoring\RecommendationEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Recommendations panel component class.
 *
 *
 * @since      1.0.0
 */
class RecommendationsPanel extends Component
{
    /**
     * Session key used to persist dismissals across requests.
     *
     * @since 1.0.0
     */
    public const DISMISSAL_KEY = 'artisanpack.performance.dismissed_recommendations';

    /**
     * The currently selected date range.
     *
     * @since 1.0.0
     */
    #[Url( as: 'range', history: true )]
    public string $dateRange = '7d';

    /**
     * Label overrides supplied by the host application.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public array $labels = [];

    /**
     * Recommendation ids the user has dismissed this session.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public array $dismissed = [];

    /**
     * The most recent status message produced by an action.
     *
     * @since 1.0.0
     */
    public ?string $statusMessage = null;

    /**
     * Whether the most recent status represents a failure.
     *
     * @since 1.0.0
     */
    public bool $statusIsError = false;

    /**
     * Mounts the component with optional label overrides.
     *
     * Dismissals are hydrated from the session so a user who dismissed
     * an item on the previous request doesn't see it flash back in on
     * the next render.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $labels  Optional label overrides.
     */
    public function mount( array $labels = [] ): void
    {
        $this->labels    = array_filter( $labels, 'is_string' );
        $this->dismissed = array_values( array_filter(
            (array) Session::get( self::DISMISSAL_KEY, [] ),
            'is_string',
        ) );
    }

    /**
     * Dismisses the recommendation with the given id.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Recommendation id (as produced by the engine).
     */
    public function dismiss( string $id ): void
    {
        if ( '' === $id ) {
            return;
        }

        if ( ! in_array( $id, $this->dismissed, true ) ) {
            $this->dismissed[] = $id;
        }

        Session::put( self::DISMISSAL_KEY, $this->dismissed );
    }

    /**
     * Un-dismisses every recommendation, clearing the session cache.
     *
     * @since 1.0.0
     */
    public function resetDismissals(): void
    {
        $this->dismissed = [];
        Session::forget( self::DISMISSAL_KEY );
    }

    /**
     * Applies the requested action for a recommendation.
     *
     * Only actions the engine explicitly declared are wired up here;
     * every other action shows a "manual only" message so the panel
     * never silently succeeds for a recommendation that doesn't
     * actually have automation.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Recommendation id.
     */
    public function applyAction( string $id ): void
    {
        $recommendation = $this->findById( $id );

        if ( null === $recommendation ) {
            $this->setStatus( __( 'Recommendation is no longer available.' ), true );

            return;
        }

        $action = $recommendation['action'] ?? null;

        if ( null === $action || '' === $action ) {
            $this->setStatus( __( 'This recommendation has no one-click fix; follow the manual steps.' ), true );

            return;
        }

        try {
            match ( $action ) {
                'warm-cache'                => $this->runWarmCache(),
                'generate-index-migration'  => $this->runGenerateIndexMigration( $recommendation ),
                'view-query-analyzer'       => $this->dispatchNavigation( 'queries' ),
                default                     => $this->setStatus( __( 'Unknown action: :action', [ 'action' => $action ] ), true ),
            };
        } catch ( Throwable $e ) {
            $this->setStatus( __( 'Action failed: :message', [ 'message' => $e->getMessage() ] ), true );
        }
    }

    /**
     * Renders the panel template.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        $items = app( RecommendationEngine::class )->build( $this->dateRange );

        $visible = array_values( array_filter( $items, fn ( array $item ): bool => ! in_array( $item['id'], $this->dismissed, true ) ) );

        return view( 'performance::livewire.recommendations-panel', [
            'items'          => $visible,
            'dismissedCount' => count( $items ) - count( $visible ),
            'resolvedLabels' => $this->resolveLabels(),
        ] );
    }

    /**
     * Locates the recommendation with the given id in the current build.
     *
     * A single `->build()` call is inexpensive relative to the
     * `applyAction()` interaction (the user has already clicked); the
     * cost is dominated by the underlying queries which are indexed.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Recommendation id.
     *
     * @return array<string, mixed>|null
     */
    protected function findById( string $id ): ?array
    {
        $items = app( RecommendationEngine::class )->build( $this->dateRange );

        foreach ( $items as $item ) {
            if ( ( $item['id'] ?? '' ) === $id ) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Runs the configured cache warming URLs through the page cache warmer.
     *
     * @since 1.0.0
     */
    protected function runWarmCache(): void
    {
        $urls = (array) config( 'artisanpack.performance.cache_warming.urls', [] );
        $urls = array_values( array_filter( $urls, 'is_string' ) );

        if ( [] === $urls ) {
            $this->setStatus( __( 'No cache_warming.urls configured.' ), true );

            return;
        }

        $results = app( \ArtisanPackUI\Performance\Cache\PageCacheManager::class )
            ->warmPageCache( $urls );

        $succeeded = 0;

        foreach ( $results as $entry ) {
            if ( is_array( $entry ) && true === ( $entry['ok'] ?? false ) ) {
                $succeeded++;
            }
        }

        $this->setStatus( __( 'Warmed :count of :total URLs.', [
            'count' => $succeeded,
            'total' => count( $results ),
        ] ) );
    }

    /**
     * Emits an event asking the host to scaffold an index migration.
     *
     * The engine already knows which table/columns are involved; we
     * simply hand that payload off to the application via a Livewire
     * event so the host can decide whether to open a scaffold, run
     * `perf:suggest-indexes`, or send the operator to a confirmation
     * screen.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $recommendation  The recommendation being acted on.
     */
    protected function runGenerateIndexMigration( array $recommendation ): void
    {
        $payload = (array) ( $recommendation['action_payload'] ?? [] );

        $this->dispatch(
            'performance:generate-index-migration',
            table: (string) ( $payload['table'] ?? '' ),
            columns: array_values( (array) ( $payload['columns'] ?? [] ) ),
        );

        $this->setStatus( __( 'Requested index migration generation for :table.', [
            'table' => (string) ( $payload['table'] ?? '' ),
        ] ) );
    }

    /**
     * Dispatches a browser event asking the parent to switch tabs.
     *
     * @since 1.0.0
     *
     * @param  string  $tab  Target tab key.
     */
    protected function dispatchNavigation( string $tab ): void
    {
        $this->dispatch( 'performance:navigate', tab: $tab );
        $this->setStatus( __( 'Opening :tab.', [ 'tab' => $tab ] ) );
    }

    /**
     * Sets the status banner for the next render.
     *
     * @since 1.0.0
     *
     * @param  string  $message  Status message.
     * @param  bool  $isError  Whether the message represents a failure.
     */
    protected function setStatus( string $message, bool $isError = false ): void
    {
        $this->statusMessage = $message;
        $this->statusIsError = $isError;
    }

    /**
     * Resolves label overrides, merging host-supplied values over defaults.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function resolveLabels(): array
    {
        $defaults = [
            'title'           => (string) __( 'Recommendations' ),
            'empty'           => (string) __( 'All tracked signals are in the good band.' ),
            'apply'           => (string) __( 'Apply fix' ),
            'dismiss'         => (string) __( 'Dismiss' ),
            'reset'           => (string) __( 'Restore dismissed' ),
            'priority_high'   => (string) __( 'High priority' ),
            'priority_medium' => (string) __( 'Medium priority' ),
            'priority_low'    => (string) __( 'Low priority' ),
            'manual_steps'    => (string) __( 'Manual steps' ),
        ];

        return array_merge( $defaults, $this->labels );
    }
}
