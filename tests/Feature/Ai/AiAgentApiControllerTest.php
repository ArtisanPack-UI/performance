<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Gate;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns a shaped query insight response', function (): void {
    $this->prompter->queue( [
        'summary'           => 'Full scan.',
        'bottlenecks'       => [ 'Missing index' ],
        'suggested_indexes' => [
            [ 'table' => 'articles', 'columns' => [ 'slug' ], 'rationale' => 'covers WHERE' ],
        ],
        'rewrites'          => [],
        'caveats'           => [],
    ] );

    $response = $this->postJson( '/api/performance/ai/query-insight', [
        'query'   => 'SELECT * FROM articles WHERE slug = ?',
        'time_ms' => 210,
    ] );

    $response
        ->assertOk()
        ->assertJsonPath( 'feature_key', 'performance.query_insight' )
        ->assertJsonPath( 'data.summary', 'Full scan.' )
        ->assertJsonPath( 'data.suggested_indexes.0.table', 'articles' );
} );

it( 'validates the query field on query-insight', function (): void {
    $response = $this->postJson( '/api/performance/ai/query-insight', [] );

    $response->assertStatus( 422 );
} );

it( 'returns 403 when the performance.ai.use gate denies', function (): void {
    Gate::define( 'performance.ai.use', static fn ( $user = null ): bool => false );

    $response = $this->postJson( '/api/performance/ai/query-insight', [
        'query' => 'SELECT 1',
    ] );

    $response
        ->assertStatus( 403 )
        ->assertJsonPath( 'feature_key', 'performance.query_insight' );
} );

it( 'returns 409 when the query insight feature is disabled', function (): void {
    // The feature key literally contains a dot, so Config::set with dot-
    // notation would write to the wrong path. Set the whole `features`
    // array to keep the key intact.
    $features                              = $this->app['config']->get( 'artisanpack.ai.features', [] );
    $features['performance.query_insight'] = [ 'enabled' => false ];
    $this->app['config']->set( 'artisanpack.ai.features', $features );

    $response = $this->postJson( '/api/performance/ai/query-insight', [
        'query' => 'SELECT 1',
    ] );

    $response
        ->assertStatus( 409 )
        ->assertJsonPath( 'feature_key', 'performance.query_insight' );
} );

it( 'returns a shaped optimization suggestion response', function (): void {
    $this->prompter->queue( [
        'summary'     => 'Focus on articles.',
        'focus_areas' => [
            [
                'title'     => 'Article LCP',
                'routes'    => [ 'GET /articles/{slug}' ],
                'impact'    => 'high',
                'effort'    => 'medium',
                'rationale' => 'p75 regressed',
                'actions'   => [ 'Preload hero image' ],
            ],
        ],
        'quick_wins'  => [],
        'caveats'     => [],
    ] );

    $response = $this->postJson( '/api/performance/ai/optimization-suggestion', [
        'range'   => [ 'from' => '2026-07-01', 'to' => '2026-07-07' ],
        'metrics' => [
            [ 'metric' => 'lcp', 'route' => 'GET /articles/{slug}', 'p75' => 4600 ],
        ],
    ] );

    $response
        ->assertOk()
        ->assertJsonPath( 'feature_key', 'performance.optimization_suggestion' )
        ->assertJsonPath( 'data.focus_areas.0.impact', 'high' );
} );

it( 'validates optimization-suggestion payload', function (): void {
    $response = $this->postJson( '/api/performance/ai/optimization-suggestion', [
        'range' => [ 'from' => '2026-07-05', 'to' => '2026-07-01' ],
    ] );

    $response->assertStatus( 422 );
} );
