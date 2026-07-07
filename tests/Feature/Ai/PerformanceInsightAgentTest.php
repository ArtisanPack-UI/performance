<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Performance\Ai\Agents\PerformanceInsightAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns the shaped insight when the prompter responds', function (): void {
    $this->prompter->queue( [
        'summary'           => 'Full scan on articles because slug is not indexed.',
        'bottlenecks'       => [ 'No index on articles.slug', 'Filesort for ORDER BY published_at' ],
        'suggested_indexes' => [
            [
                'table'     => 'articles',
                'columns'   => [ 'slug', 'published_at' ],
                'rationale' => 'covers the WHERE and ORDER BY',
            ],
        ],
        'rewrites'          => [
            [
                'original'  => 'SELECT *',
                'suggested' => 'SELECT id, slug, published_at',
                'rationale' => 'avoid dragging BLOBs into the sort',
            ],
        ],
        'caveats'           => [ 'row counts unknown' ],
    ] );

    $result = PerformanceInsightAgent::for( [
        'query'      => 'SELECT * FROM articles WHERE slug = ? ORDER BY published_at DESC',
        'time_ms'    => 850.4,
        'connection' => 'mysql',
    ] )->run();

    expect( $result['summary'] )->toContain( 'Full scan' );
    expect( $result['bottlenecks'] )->toHaveCount( 2 );
    expect( $result['suggested_indexes'][0]['table'] )->toBe( 'articles' );
    expect( $result['suggested_indexes'][0]['columns'] )->toBe( [ 'slug', 'published_at' ] );
    expect( $result['rewrites'][0]['original'] )->toBe( 'SELECT *' );
    expect( $result['caveats'] )->toContain( 'row counts unknown' );
} );

it( 'drops malformed suggested_indexes rows', function (): void {
    $this->prompter->queue( [
        'summary'           => 'ok',
        'bottlenecks'       => [],
        'suggested_indexes' => [
            [ 'table' => '', 'columns' => [ 'x' ], 'rationale' => 'skipped: empty table' ],
            [ 'table' => 'foo', 'columns' => [], 'rationale' => 'skipped: empty columns' ],
            [ 'table' => 'orders', 'columns' => [ 'user_id' ], 'rationale' => 'kept' ],
            'not-an-array',
        ],
        'rewrites'          => [],
        'caveats'           => [],
    ] );

    $result = PerformanceInsightAgent::for( [ 'query' => 'SELECT 1' ] )->run();

    expect( $result['suggested_indexes'] )->toHaveCount( 1 );
    expect( $result['suggested_indexes'][0]['table'] )->toBe( 'orders' );
} );

it( 'drops rewrites missing original or suggested', function (): void {
    $this->prompter->queue( [
        'summary'           => 'ok',
        'bottlenecks'       => [],
        'suggested_indexes' => [],
        'rewrites'          => [
            [ 'original' => '', 'suggested' => 'x', 'rationale' => 'skipped' ],
            [ 'original' => 'x', 'suggested' => '', 'rationale' => 'skipped' ],
            [ 'original' => 'a', 'suggested' => 'b', 'rationale' => 'kept' ],
        ],
        'caveats'           => [],
    ] );

    $result = PerformanceInsightAgent::for( [ 'query' => 'SELECT 1' ] )->run();

    expect( $result['rewrites'] )->toHaveCount( 1 );
    expect( $result['rewrites'][0]['original'] )->toBe( 'a' );
} );

it( 'raises FeatureError when query is missing', function (): void {
    expect( fn () => PerformanceInsightAgent::for( [] )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'raises FeatureError when query is empty', function (): void {
    expect( fn () => PerformanceInsightAgent::for( [ 'query' => '   ' ] )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'forwards explain and schema into the prompter message', function (): void {
    $this->prompter->queue( [
        'summary'           => 'x',
        'bottlenecks'       => [],
        'suggested_indexes' => [],
        'rewrites'          => [],
        'caveats'           => [],
    ] );

    PerformanceInsightAgent::for( [
        'query'   => 'SELECT 1',
        'explain' => [ [ 'type' => 'ALL', 'rows' => 10000 ] ],
        'schema'  => [ 'users' => [ 'id' => 'bigint' ] ],
    ] )->run();

    $call    = $this->prompter->calls[0];
    $flat    = collect( $call['message'] )->pluck( 'text' )->implode( "\n" );

    expect( $flat )->toContain( 'EXPLAIN plan' );
    expect( $flat )->toContain( 'Schema' );
    expect( $flat )->toContain( 'SELECT 1' );
} );
