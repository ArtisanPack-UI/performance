<?php

/**
 * OptimizationSuggestionPanel Livewire component.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Livewire\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Performance\Ai\Agents\OptimizationSuggestionAgent;
use ArtisanPackUI\Performance\Models\PerformanceMetric;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see OptimizationSuggestionAgent}.
 *
 * Pulls aggregate metrics for a rolling date window straight from the
 * `performance_metrics` table so the panel works out of the box without
 * a parent component threading the payload in. Emits
 * `performance-ai-optimization-generated` (payload: the full agent
 * output) so a containing dashboard can archive or forward it.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.1.0
 */
class OptimizationSuggestionPanel extends Component
{
    /**
     * Maximum rolling-window length the panel accepts.
     *
     * Livewire public properties are client-mutable, so an unbounded
     * `$windowDays` would let any authenticated caller ask for centuries
     * of aggregate rows and OOM the worker. The cap has to be far enough
     * out to be useful (quarter-over-quarter regression triage) but well
     * short of the row counts a real install accumulates.
     *
     * @since 1.1.0
     */
    protected const MAX_WINDOW_DAYS = 90;

    /**
     * Hard cap on aggregate rows fed to the agent per run.
     *
     * Beyond a few thousand rows the JSON prompt is both slow to build
     * and larger than most providers will accept — the cap keeps the
     * request bounded even when the aggregates table has grown very
     * large. Rows are ordered by `sample_count DESC` so the most
     * representative rows survive the cut.
     *
     * @since 1.1.0
     */
    protected const MAX_METRICS_ROWS = 2000;

    /**
     * Rolling window in days.
     *
     * @since 1.1.0
     *
     * @var int
     */
    #[Validate( 'integer|min:1|max:90' )]
    public int $windowDays = 7;

    /**
     * Optional business priority hint passed straight to the agent's
     * `context.business_priority` field.
     *
     * @since 1.1.0
     *
     * @var string
     */
    public string $businessPriority = '';

    /**
     * Optional recent-changes hint passed straight to the agent's
     * `context.recent_changes` field.
     *
     * @since 1.1.0
     *
     * @var string
     */
    public string $recentChanges = '';

    public bool $isLoading = false;

    public ?string $error = null;

    /**
     * Last successful agent output, or `null` before the first successful run.
     *
     * @since 1.1.0
     *
     * @var array<string, mixed>|null
     */
    public ?array $suggestion = null;

    /**
     * React to a parent dashboard nudging the panel with fresh context.
     *
     * @since 1.1.0
     *
     * @param  array{ window_days?: int, business_priority?: string, recent_changes?: string }  $payload  New context.
     *
     * @return void
     */
    #[On( 'performance-ai-context-updated' )]
    public function contextUpdated( array $payload ): void
    {
        if ( isset( $payload['window_days'] ) && is_int( $payload['window_days'] ) && $payload['window_days'] > 0 ) {
            $this->windowDays = min( $payload['window_days'], self::MAX_WINDOW_DAYS );
        }

        if ( isset( $payload['business_priority'] ) ) {
            $this->businessPriority = (string) $payload['business_priority'];
        }

        if ( isset( $payload['recent_changes'] ) ) {
            $this->recentChanges = (string) $payload['recent_changes'];
        }
    }

    /**
     * Run the agent against the last `$windowDays` days of metrics.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function suggest(): void
    {
        $this->error      = null;
        $this->suggestion = null;
        $this->isLoading  = true;

        // Second line of defence after the `#[Validate]` attribute — the
        // #[On] listener and any client-side mutation could still push a
        // value outside the accepted range before suggest() fires.
        $windowDays = max( 1, min( $this->windowDays, self::MAX_WINDOW_DAYS ) );

        try {
            $from = Carbon::now()->subDays( $windowDays - 1 )->startOfDay();
            $to   = Carbon::now()->endOfDay();

            $metrics = $this->collectMetrics( $from, $to );

            $context = array_filter( [
                'business_priority' => trim( $this->businessPriority ),
                'recent_changes'    => trim( $this->recentChanges ),
            ], static fn ( string $value ): bool => '' !== $value );

            $this->suggestion = OptimizationSuggestionAgent::for( [
                'range'   => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
                'metrics' => $metrics,
                'context' => $context,
            ] )->run();

            $this->dispatch( 'performance-ai-optimization-generated', suggestion: $this->suggestion );
        } catch ( FeatureDisabledException $exception ) {
            $this->error = __( 'This AI feature is disabled.' );
        } catch ( MissingCredentialsException $exception ) {
            $this->error = __( 'AI credentials are not configured.' );
        } catch ( FeatureError $exception ) {
            $this->error = $exception->getMessage();
        } catch ( Throwable $exception ) {
            $this->error = __( 'The AI agent could not complete this request.' );
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Determine whether this feature is enabled in the registry.
     *
     * @since 1.1.0
     *
     * @return bool
     */
    public function getIsEnabledProperty(): bool
    {
        $registry = app( FeatureRegistry::class );
        $key      = 'performance.optimization_suggestion';

        if ( null === $registry->get( $key ) ) {
            return false;
        }

        return $registry->isToggleOn( $key );
    }

    /**
     * Render the component view.
     *
     * @since 1.1.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'performance::livewire.ai.optimization-suggestion-panel' );
    }

    /**
     * Collect aggregate metrics for the current window.
     *
     * Kept as a hook so a subclass or a host app that stores metrics
     * elsewhere (Prometheus, Datadog, a data warehouse) can override
     * without reimplementing the surrounding component.
     *
     * @since 1.1.0
     *
     * @param  Carbon  $from  Range start (inclusive).
     * @param  Carbon  $to    Range end (inclusive).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function collectMetrics( Carbon $from, Carbon $to ): array
    {
        // toBase() skips Eloquent model hydration + `retrieved` events on
        // what can easily be tens of thousands of aggregate rows on a
        // busy install. The limit is a load-safety cap, not a semantic
        // one — hitting it means the prompt would have been too big for
        // the model anyway.
        return PerformanceMetric::query()
            ->whereBetween( 'date', [ $from->toDateString(), $to->toDateString() ] )
            ->orderByDesc( 'sample_count' )
            ->orderBy( 'date' )
            ->orderBy( 'route' )
            ->limit( self::MAX_METRICS_ROWS )
            ->toBase()
            ->get( [ 'date', 'route', 'metric', 'p50', 'p75', 'p90', 'p99', 'sample_count', 'device_type' ] )
            ->map( static fn ( object $row ): array => [
                'date'    => (string) $row->date,
                'route'   => $row->route,
                'metric'  => $row->metric,
                'p50'     => (float) $row->p50,
                'p75'     => (float) $row->p75,
                'p90'     => (float) $row->p90,
                'p99'     => (float) $row->p99,
                'samples' => (int) $row->sample_count,
                'device'  => $row->device_type,
            ] )
            ->all();
    }
}
