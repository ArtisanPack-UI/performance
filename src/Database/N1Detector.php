<?php

/**
 * N+1 query detector.
 *
 * Subscribes to query execution events, normalizes each statement
 * through `QueryAnalyzer`, and dispatches `N1QueryDetected` the
 * moment a single signature exceeds the configured threshold within
 * a request. The detector also derives a "model + relation"
 * suggestion from the captured SQL so the event payload can power
 * actionable warnings ("Add `->with('comments')` to Post").
 *
 * The detector intentionally fires the event ONCE per signature per
 * request — repeat firings for the same signature would spam the
 * configured listeners (log channels, notification recipients) on a
 * page that runs 100 of the same query.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Database;

use ArtisanPackUI\Performance\Events\N1QueryDetected;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * N+1 query detector class.
 *
 *
 * @since      1.0.0
 */
class N1Detector
{
    /**
     * The QueryAnalyzer used to normalize captured SQL.
     *
     * @since 1.0.0
     */
    protected QueryAnalyzer $analyzer;

    /**
     * Whether the detector is currently subscribed to `DB::listen()`.
     *
     * @since 1.0.0
     */
    protected bool $listening = false;

    /**
     * Per-request counter keyed by normalized SQL signature.
     *
     * @since 1.0.0
     *
     * @var array<string, int>
     */
    protected array $counts = [];

    /**
     * Signatures that have already triggered an event this request.
     *
     * @since 1.0.0
     *
     * @var array<string, true>
     */
    protected array $reported = [];

    /**
     * Active route name captured from the running request (or an empty string).
     *
     * @since 1.0.0
     */
    protected string $currentRoute = '';

    /**
     * Creates a new detector instance.
     *
     * @since 1.0.0
     *
     * @param  QueryAnalyzer  $analyzer  The analyzer used for normalization.
     */
    public function __construct( QueryAnalyzer $analyzer )
    {
        $this->analyzer = $analyzer;
    }

    /**
     * Subscribes the detector to `DB::listen()`.
     *
     * Idempotent. Reads `database.n1_detection.enabled` once to decide
     * whether to subscribe; subsequent runtime flips take effect via
     * the listener closure, which re-reads the flag on every event so
     * toggling off stops the work even though DB::listen has no
     * unsubscribe API in stable Laravel.
     *
     * @since 1.0.0
     */
    public function enable(): void
    {
        if ( $this->listening ) {
            return;
        }

        if ( ! (bool) config( 'artisanpack.performance.database.n1_detection.enabled', false ) ) {
            return;
        }

        $this->listening = true;

        DB::listen( function ( QueryExecuted $event ): void {
            if ( ! (bool) config( 'artisanpack.performance.database.n1_detection.enabled', false ) ) {
                return;
            }

            $this->record( $event );
        } );
    }

    /**
     * Reports whether the detector is currently subscribed.
     *
     * @since 1.0.0
     */
    public function isEnabled(): bool
    {
        return $this->listening;
    }

    /**
     * Records a single query execution.
     *
     * The method is public so tests and other package code (for
     * example, the analyzer feeding cached query results) can feed
     * synthesized events without round-tripping through `DB::listen()`.
     *
     * @since 1.0.0
     *
     * @param  QueryExecuted  $event  The Laravel query event.
     */
    public function record( QueryExecuted $event ): void
    {
        $signature = $this->analyzer->normalize( $event->sql );

        if ( '' === $signature ) {
            return;
        }

        $this->counts[ $signature ] = ( $this->counts[ $signature ] ?? 0 ) + 1;

        $threshold = (int) config( 'artisanpack.performance.database.n1_detection.threshold', 5 );

        if ( $this->counts[ $signature ] < max( 1, $threshold ) ) {
            return;
        }

        if ( isset( $this->reported[ $signature ] ) ) {
            return;
        }

        $this->reported[ $signature ] = true;

        $this->reportDetection( $signature, $this->counts[ $signature ] );
    }

    /**
     * Returns the per-signature execution counts captured so far.
     *
     * @since 1.0.0
     *
     * @return array<string, int>
     */
    public function getCounts(): array
    {
        return $this->counts;
    }

    /**
     * Returns the set of signatures that have triggered detection this request.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function getReportedSignatures(): array
    {
        return array_keys( $this->reported );
    }

    /**
     * Resets the per-request counters and reported-set.
     *
     * Called by the Octane request-received hook and by tests
     * between scenarios to keep state from leaking across requests.
     *
     * The active route name is cleared too — without that, an N+1
     * dispatched on request B (e.g. an anonymous 404 whose middleware
     * never calls `setCurrentRoute`) would carry request A's route
     * label.
     *
     * @since 1.0.0
     */
    public function reset(): void
    {
        $this->counts       = [];
        $this->reported     = [];
        $this->currentRoute = '';
    }

    /**
     * Allows the caller to record the active route name.
     *
     * The middleware that wires the detector calls this with
     * `request()->route()?->getName()` so the dispatched event
     * carries the route context — useful when developers triage
     * detection reports after the fact.
     *
     * @since 1.0.0
     *
     * @param  string  $route  Route name.
     */
    public function setCurrentRoute( string $route ): void
    {
        $this->currentRoute = $route;
    }

    /**
     * Returns a "Model::with('relation')" suggestion for the given signature.
     *
     * Pattern-matches the normalized SQL for the two canonical N+1
     * shapes:
     * - HasMany / HasOne: `SELECT * FROM <table> WHERE <fk>_id = ?` —
     *   the loop reads child rows per parent. The relation lives on
     *   the PARENT and the child-table name (camelCased) is a good
     *   default suggestion.
     * - BelongsTo / MorphTo: `SELECT * FROM <table> WHERE id = ?` —
     *   the loop reads parent rows per child. The relation lives on
     *   the CHILD and is the singular form of the parent-table name
     *   (camelCased).
     *
     * When the pattern doesn't match we return null relation so the
     * caller can fall back to a generic suggestion in the event
     * payload.
     *
     * @since 1.0.0
     *
     * @param  string  $signature  Normalized SQL signature.
     *
     * @return array{table: string|null, relation: string|null, suggestion: string}
     */
    public function suggestFix( string $signature ): array
    {
        $table    = null;
        $relation = null;

        if ( 1 === preg_match( '/from\s+`?(?P<table>[a-z_][a-z0-9_]*)`?\s+where\s+`?(?P<column>[a-z_][a-z0-9_]*)`?\s*=/i', $signature, $matches ) ) {
            $table    = $matches['table'];
            $column   = $matches['column'];
            $relation = $this->inferRelation( $table, $column );
        }

        $suggestion = null !== $relation
            ? sprintf( 'Eager-load the relation with ->with(\'%s\') on the parent query.', $relation )
            : 'A single query signature repeated above the configured threshold — review eager loading on the parent query.';

        return [
            'table'      => $table,
            'relation'   => $relation,
            'suggestion' => $suggestion,
        ];
    }

    /**
     * Dispatches the detection event and writes to the log channel.
     *
     * @since 1.0.0
     *
     * @param  string  $signature  Normalized SQL signature.
     * @param  int  $count  Number of times the signature ran in this request.
     */
    protected function reportDetection( string $signature, int $count ): void
    {
        $suggestion = $this->suggestFix( $signature );

        Event::dispatch( new N1QueryDetected(
            queryNormalized: $signature,
            count: $count,
            route: $this->currentRoute,
        ) );

        $this->writeLog( $signature, $count, $suggestion );
    }

    /**
     * Writes the detection to the configured log channel.
     *
     * @since 1.0.0
     *
     * @param  string  $signature  Normalized SQL signature.
     * @param  int  $count  Number of times the signature ran in this request.
     * @param  array{table: string|null, relation: string|null, suggestion: string}  $suggestion  The suggestion payload.
     */
    protected function writeLog( string $signature, int $count, array $suggestion ): void
    {
        $channel = (string) config( 'artisanpack.performance.database.n1_detection.log_channel', '' );

        if ( '' === $channel ) {
            return;
        }

        try {
            Log::channel( $channel )->warning( 'N+1 query detected', [
                'signature' => $signature,
                'count'     => $count,
                'table'     => $suggestion['table'],
                'relation'  => $suggestion['relation'],
                'suggest'   => $suggestion['suggestion'],
                'route'     => $this->currentRoute,
            ] );
        } catch ( Throwable ) {
            // The configured log channel may not exist in the consuming
            // app (typical in tests / packages exercising the detector
            // in isolation). Swallow rather than crash the request the
            // detector is observing — the dispatched event remains the
            // primary signal.
        }
    }

    /**
     * Infers an Eloquent relation name from a table + WHERE column.
     *
     * BelongsTo / MorphTo case: when the WHERE column is `id`, the
     * loop is fetching parents one-at-a-time by primary key. The
     * relation lives on the CHILD model and is conventionally the
     * SINGULAR camelCase form of the parent table — `Comment->user`
     * for `SELECT * FROM users WHERE id = ?`.
     *
     * HasMany / HasOne case: when the WHERE column ends in `_id`, the
     * loop is fetching children by foreign key. The relation lives on
     * the PARENT and is the camelCased CHILD table — `Post->comments`
     * for `SELECT * FROM comments WHERE post_id = ?`.
     *
     * Falls back to the camelCased table for anything else so the
     * suggestion is at least actionable for non-standard schemas.
     *
     * @since 1.0.0
     *
     * @param  string  $table  The table the loop is querying.
     * @param  string  $column  The WHERE column (FK or `id`).
     */
    protected function inferRelation( string $table, string $column ): string
    {
        if ( 'id' === strtolower( $column ) ) {
            return Str::camel( Str::singular( $table ) );
        }

        return Str::camel( $table );
    }
}
