<?php

/**
 * Cache manager Livewire component.
 *
 * Exposes the page and fragment cache controls — invalidate by key,
 * invalidate by tag, flush both stores, and trigger cache warming — as
 * a single dashboard surface. Destructive actions go through a small
 * confirmation step the view renders inline so the component does not
 * depend on a third-party modal library.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Livewire;

use ArtisanPackUI\Performance\Cache\CacheInvalidator;
use ArtisanPackUI\Performance\Cache\CacheStatistics;
use ArtisanPackUI\Performance\Cache\PageCacheManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * Cache manager component class.
 *
 *
 * @since      1.0.0
 */
class CacheManager extends Component
{
    /**
     * Label overrides supplied by the host application.
     *
     * The view checks each well-known key (`purge`, `warm`, `flush`,
     * `invalidate`) against this map so host applications can change
     * the verbiage without re-publishing the template.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public array $labels = [];

    /**
     * The action currently awaiting confirmation, or null when none is pending.
     *
     * Stored as a `type:value` string so the view can match on the
     * exact action being confirmed. Concrete forms:
     *
     * - `flush`                — whole-cache flush
     * - `entry:{index}`        — confirm invalidation of pageEntries[$index]
     * - `key:{value}`          — confirm invalidation of a user-typed pattern
     * - `fragment-tag:{index}` — confirm invalidation of fragmentTags[$index]
     *
     * @since 1.0.0
     */
    public ?string $pendingAction = null;

    /**
     * The current value of the "Invalidate by key" text input.
     *
     * Bound via `wire:model.live` so the user-typed pattern is
     * available server-side without trying to parse `$event` out of a
     * Livewire action expression (which is a Livewire-can't-do-it
     * footgun even though the syntax looks right).
     *
     * @since 1.0.0
     */
    public string $invalidateKeyInput = '';

    /**
     * The most recent status message produced by an action.
     *
     * @since 1.0.0
     */
    public ?string $statusMessage = null;

    /**
     * Whether the most recent status is an error.
     *
     * @since 1.0.0
     */
    public bool $statusIsError = false;

    /**
     * Mounts the component, applying any label overrides.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $labels  Label overrides.
     */
    public function mount( array $labels = [] ): void
    {
        $this->labels = array_filter( $labels, 'is_string' );
    }

    /**
     * Stages a destructive action for confirmation.
     *
     * The view re-renders with the matching action highlighted and a
     * confirm/cancel pair underneath. The action only runs when the
     * confirm button calls the matching `confirm…` method.
     *
     * @since 1.0.0
     *
     * @param  string  $action  Action descriptor (e.g. `flush`, `tag:foo`, `key:bar`).
     */
    public function requestConfirmation( string $action ): void
    {
        $this->pendingAction = $action;
    }

    /**
     * Cancels a pending confirmation without performing the action.
     *
     * @since 1.0.0
     */
    public function cancelConfirmation(): void
    {
        $this->pendingAction = null;
    }

    /**
     * Stages a key-invalidation confirmation for the current input value.
     *
     * Backs the `<form wire:submit="requestKeyInvalidation">` in the
     * view. Pulls the value from the `wire:model`-bound
     * `$invalidateKeyInput` property — server-side data, no JS
     * `$event` parsing — and stages the same `key:{value}` confirm
     * flow the per-row buttons use.
     *
     * @since 1.0.0
     */
    public function requestKeyInvalidation(): void
    {
        $trimmed = trim( $this->invalidateKeyInput );

        if ( '' === $trimmed ) {
            $this->setStatus( __( 'A cache key is required.' ), true );

            return;
        }

        $this->pendingAction = 'key:' . $trimmed;
    }

    /**
     * Invalidates the page entry at the given index in the current view.
     *
     * The view passes a numeric index instead of the raw path so user-
     * controlled characters (apostrophes, quotes, backslashes) cannot
     * break the wire:click expression syntax. The path is resolved
     * server-side from the same `pageEntries()` sample the view
     * rendered.
     *
     * @since 1.0.0
     *
     * @param  int  $index  Zero-based index into the rendered page-entry list.
     */
    public function invalidateEntry( int $index ): void
    {
        $entries = app( CacheStatistics::class )->pageEntries();

        if ( ! isset( $entries[ $index ] ) ) {
            $this->setStatus( __( 'Cache entry no longer exists.' ), true );
            $this->pendingAction = null;

            return;
        }

        $path = (string) ( $entries[ $index ]['path'] ?? '' );

        if ( '' === $path ) {
            $this->setStatus( __( 'Cache entry has no path to invalidate.' ), true );
            $this->pendingAction = null;

            return;
        }

        $this->invalidate( $path );
    }

    /**
     * Invalidates the fragment tag at the given index in the current view.
     *
     * @since 1.0.0
     *
     * @param  int  $index  Zero-based index into the rendered fragment-tag list.
     */
    public function invalidateFragmentTagByIndex( int $index ): void
    {
        $tags = app( CacheStatistics::class )->fragmentTags();

        if ( ! isset( $tags[ $index ] ) ) {
            $this->setStatus( __( 'Fragment tag no longer exists.' ), true );
            $this->pendingAction = null;

            return;
        }

        $tag = (string) ( $tags[ $index ]['tag'] ?? '' );

        if ( '' === $tag ) {
            $this->setStatus( __( 'Fragment tag is empty.' ), true );
            $this->pendingAction = null;

            return;
        }

        $this->invalidateByTag( $tag );
    }

    /**
     * Invalidates a specific page cache entry.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Cache key or path pattern.
     */
    public function invalidate( string $key ): void
    {
        if ( '' === trim( $key ) ) {
            $this->setStatus( __( 'A cache key is required.' ), true );

            return;
        }

        try {
            $count = app( CacheInvalidator::class )->invalidatePagePattern( $key );
        } catch ( Throwable $e ) {
            $this->setStatus( __( 'Failed to invalidate cache: :message', [ 'message' => $e->getMessage() ] ), true );

            return;
        }

        $this->pendingAction      = null;
        $this->invalidateKeyInput = '';
        $this->setStatus( __( ':count entries invalidated for :key.', [
            'count' => $count,
            'key'   => $key,
        ] ) );
    }

    /**
     * Invalidates every fragment cache entry registered under the given tag.
     *
     * @since 1.0.0
     *
     * @param  string  $tag  Tag name.
     */
    public function invalidateByTag( string $tag ): void
    {
        if ( '' === trim( $tag ) ) {
            $this->setStatus( __( 'A tag name is required.' ), true );

            return;
        }

        try {
            $count = app( CacheInvalidator::class )->invalidateFragmentTag( $tag );
        } catch ( Throwable $e ) {
            $this->setStatus( __( 'Failed to invalidate tag: :message', [ 'message' => $e->getMessage() ] ), true );

            return;
        }

        $this->pendingAction = null;
        $this->setStatus( __( ':count fragments invalidated for tag ":tag".', [
            'count' => $count,
            'tag'   => $tag,
        ] ) );
    }

    /**
     * Flushes both the page and fragment caches the package manages.
     *
     * @since 1.0.0
     */
    public function flushAll(): void
    {
        try {
            $summary = app( CacheInvalidator::class )->purgeAll();
        } catch ( Throwable $e ) {
            $this->setStatus( __( 'Failed to flush caches: :message', [ 'message' => $e->getMessage() ] ), true );

            return;
        }

        $this->pendingAction = null;
        $this->setStatus( __( ':page page and :fragments fragment entries flushed.', [
            'page'      => $summary['page'] ?? 0,
            'fragments' => $summary['fragments'] ?? 0,
        ] ) );
    }

    /**
     * Triggers cache warming for the URLs configured in `cache_warming.urls`.
     *
     * @since 1.0.0
     */
    public function warmCache(): void
    {
        $urls = (array) config( 'artisanpack.performance.cache_warming.urls', [] );
        $urls = array_values( array_filter( $urls, 'is_string' ) );

        if ( empty( $urls ) ) {
            $this->setStatus( __( 'No cache_warming.urls configured.' ), true );

            return;
        }

        try {
            $results = app( PageCacheManager::class )->warmPageCache( $urls );
        } catch ( Throwable $e ) {
            $this->setStatus( __( 'Failed to warm cache: :message', [ 'message' => $e->getMessage() ] ), true );

            return;
        }

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
     * Renders the cache manager template.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        $statistics = app( CacheStatistics::class );

        return view( 'performance::livewire.cache-manager', [
            'pageSummary'     => $statistics->pageSummary(),
            'fragmentSummary' => $statistics->fragmentSummary(),
            'pageEntries'     => $statistics->pageEntries(),
            'fragmentTags'    => $statistics->fragmentTags(),
            'resolvedLabels'  => $this->resolveLabels(),
        ] );
    }

    /**
     * Resolves the action labels — merging host overrides over defaults.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function resolveLabels(): array
    {
        $defaults = [
            'purge'      => (string) __( 'Flush All Caches' ),
            'warm'       => (string) __( 'Warm Cache' ),
            'flush'      => (string) __( 'Flush' ),
            'invalidate' => (string) __( 'Invalidate' ),
            'tag'        => (string) __( 'Invalidate by Tag' ),
            'confirm'    => (string) __( 'Confirm' ),
            'cancel'     => (string) __( 'Cancel' ),
        ];

        return array_merge( $defaults, $this->labels );
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
}
