<?php

/**
 * QueryInsightPanel Livewire component.
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
use ArtisanPackUI\Performance\Ai\Agents\PerformanceInsightAgent;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see PerformanceInsightAgent}.
 *
 * Renders a form for a query + optional EXPLAIN plan + schema hint, then
 * displays the returned diagnosis. Emits `performance-ai-insight-generated`
 * (payload: the full agent output) when a suggestion is produced so a
 * containing dashboard can archive or forward it.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.1.0
 */
class QueryInsightPanel extends Component
{
    public string $query = '';

    public string $explain = '';

    public string $schema = '';

    public string $connection = '';

    public ?float $timeMs = null;

    public bool $isLoading = false;

    public ?string $error = null;

    /**
     * Last successful agent output, or `null` before the first successful run.
     *
     * @since 1.1.0
     *
     * @var array<string, mixed>|null
     */
    public ?array $insight = null;

    /**
     * Mount the component with an optional pre-selected slow query.
     *
     * @since 1.1.0
     *
     * @param  string      $query       SQL text.
     * @param  string      $explain     Raw EXPLAIN plan text.
     * @param  string      $schema      Relevant schema snippet.
     * @param  string      $connection  DB connection driver.
     * @param  float|null  $timeMs      Observed duration in milliseconds.
     */
    public function mount(
        string $query = '',
        string $explain = '',
        string $schema = '',
        string $connection = '',
        ?float $timeMs = null,
    ): void {
        $this->query      = $query;
        $this->explain    = $explain;
        $this->schema     = $schema;
        $this->connection = $connection;
        $this->timeMs     = $timeMs;
    }

    /**
     * Load a slow query into the panel from a sibling component (typically
     * the {@see \ArtisanPackUI\Performance\Livewire\QueryAnalyzer}).
     *
     * @since 1.1.0
     *
     * @param  array{ query?: string, explain?: string, schema?: string, connection?: string, time_ms?: float|int }  $payload  New context.
     *
     * @return void
     */
    #[On( 'performance-ai-query-selected' )]
    public function querySelected( array $payload ): void
    {
        if ( isset( $payload['query'] ) ) {
            $this->query = (string) $payload['query'];
        }

        if ( isset( $payload['explain'] ) ) {
            $this->explain = (string) $payload['explain'];
        }

        if ( isset( $payload['schema'] ) ) {
            $this->schema = (string) $payload['schema'];
        }

        if ( isset( $payload['connection'] ) ) {
            $this->connection = (string) $payload['connection'];
        }

        if ( isset( $payload['time_ms'] ) && ( is_int( $payload['time_ms'] ) || is_float( $payload['time_ms'] ) ) ) {
            $this->timeMs = (float) $payload['time_ms'];
        }
    }

    /**
     * Run the agent and populate `$insight` or `$error`.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function analyze(): void
    {
        $this->error     = null;
        $this->insight   = null;
        $this->isLoading = true;

        try {
            $input = [
                'query'      => $this->query,
                'explain'    => '' === trim( $this->explain ) ? null : $this->explain,
                'schema'     => $this->parseSchema( $this->schema ),
                'time_ms'    => $this->timeMs,
                'connection' => '' === trim( $this->connection ) ? null : $this->connection,
            ];

            $this->insight = PerformanceInsightAgent::for( $input )->run();

            $this->dispatch( 'performance-ai-insight-generated', insight: $this->insight );
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
        $key      = 'performance.query_insight';

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
        return view( 'performance::livewire.ai.query-insight-panel' );
    }

    /**
     * Attempt to decode the raw schema textarea as JSON, falling back to
     * passing the raw string through when it is not valid JSON.
     *
     * @since 1.1.0
     *
     * @param  string  $raw  Raw schema text.
     *
     * @return array<mixed>|string|null
     */
    protected function parseSchema( string $raw ): array|string|null
    {
        $trimmed = trim( $raw );

        if ( '' === $trimmed ) {
            return null;
        }

        $decoded = json_decode( $trimmed, true );

        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        return $trimmed;
    }
}
