<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Performance\Ai\Agents\OptimizationSuggestionAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns the shaped suggestion when the prompter responds', function (): void {
    $this->prompter->queue( [
        'summary'     => 'Article routes regressed after Vite switch.',
        'focus_areas' => [
            [
                'title'     => 'Article LCP',
                'routes'    => [ 'GET /articles/{slug}' ],
                'impact'    => 'high',
                'effort'    => 'medium',
                'rationale' => 'p75 LCP jumped from 2.1s to 4.6s',
                'actions'   => [ 'Preload the hero image', 'Move the ad-slot script to defer' ],
            ],
        ],
        'quick_wins'  => [ 'Enable Brotli on the CDN' ],
        'caveats'     => [ 'no device breakdown in the metrics' ],
    ] );

    $result = OptimizationSuggestionAgent::for( [
        'range'   => [ 'from' => '2026-07-01', 'to' => '2026-07-07' ],
        'metrics' => [
            [ 'metric' => 'lcp', 'route' => 'GET /articles/{slug}', 'p75' => 4600, 'samples' => 300 ],
        ],
    ] )->run();

    expect( $result['summary'] )->toContain( 'Article' );
    expect( $result['focus_areas'] )->toHaveCount( 1 );
    expect( $result['focus_areas'][0]['impact'] )->toBe( 'high' );
    expect( $result['focus_areas'][0]['effort'] )->toBe( 'medium' );
    expect( $result['focus_areas'][0]['actions'] )->toHaveCount( 2 );
    expect( $result['quick_wins'] )->toContain( 'Enable Brotli on the CDN' );
} );

it( 'short-circuits when metrics list is empty', function (): void {
    $result = OptimizationSuggestionAgent::for( [
        'range'   => [ 'from' => '2026-07-01', 'to' => '2026-07-07' ],
        'metrics' => [],
    ] )->run();

    expect( $result['summary'] )->toContain( 'No metrics' );
    expect( $result['focus_areas'] )->toBe( [] );
    expect( $this->prompter->calls )->toBe( [] );
} );

it( 'clamps unknown impact/effort levels to medium', function (): void {
    $this->prompter->queue( [
        'summary'     => 'ok',
        'focus_areas' => [
            [
                'title'     => 'DB pressure',
                'routes'    => [ 'GET /home' ],
                'impact'    => 'catastrophic',
                'effort'    => 42,
                'rationale' => 'r',
                'actions'   => [],
            ],
        ],
        'quick_wins'  => [],
        'caveats'     => [],
    ] );

    $result = OptimizationSuggestionAgent::for( [
        'range'   => [ 'from' => '2026-07-01', 'to' => '2026-07-07' ],
        'metrics' => [ [ 'metric' => 'lcp', 'route' => 'GET /home', 'p75' => 3000 ] ],
    ] )->run();

    expect( $result['focus_areas'][0]['impact'] )->toBe( 'medium' );
    expect( $result['focus_areas'][0]['effort'] )->toBe( 'medium' );
} );

it( 'drops focus areas without a title', function (): void {
    $this->prompter->queue( [
        'summary'     => 'ok',
        'focus_areas' => [
            [ 'title' => '', 'routes' => [], 'impact' => 'high', 'effort' => 'low', 'rationale' => '', 'actions' => [] ],
            [ 'title' => 'Keep me', 'routes' => [], 'impact' => 'low', 'effort' => 'low', 'rationale' => '', 'actions' => [] ],
        ],
        'quick_wins'  => [],
        'caveats'     => [],
    ] );

    $result = OptimizationSuggestionAgent::for( [
        'range'   => [ 'from' => '2026-07-01', 'to' => '2026-07-07' ],
        'metrics' => [ [ 'metric' => 'lcp', 'route' => 'GET /home', 'p75' => 3000 ] ],
    ] )->run();

    expect( $result['focus_areas'] )->toHaveCount( 1 );
    expect( $result['focus_areas'][0]['title'] )->toBe( 'Keep me' );
} );

it( 'raises FeatureError when range is missing', function (): void {
    expect( fn () => OptimizationSuggestionAgent::for( [
        'metrics' => [ [ 'metric' => 'lcp' ] ],
    ] )->run() )->toThrow( FeatureError::class );
} );

it( 'raises FeatureError when metrics is missing', function (): void {
    expect( fn () => OptimizationSuggestionAgent::for( [
        'range' => [ 'from' => '2026-07-01', 'to' => '2026-07-07' ],
    ] )->run() )->toThrow( FeatureError::class );
} );

it( 'forwards context into the prompter message', function (): void {
    $this->prompter->queue( [
        'summary'     => 'x',
        'focus_areas' => [],
        'quick_wins'  => [],
        'caveats'     => [],
    ] );

    OptimizationSuggestionAgent::for( [
        'range'   => [ 'from' => '2026-07-01', 'to' => '2026-07-07' ],
        'metrics' => [ [ 'metric' => 'lcp', 'route' => 'GET /home', 'p75' => 3000 ] ],
        'context' => [ 'business_priority' => 'checkout > blog' ],
    ] )->run();

    $call = $this->prompter->calls[0];
    $flat = collect( $call['message'] )->pluck( 'text' )->implode( "\n" );

    expect( $flat )->toContain( 'checkout > blog' );
    expect( $flat )->toContain( '2026-07-01' );
} );
