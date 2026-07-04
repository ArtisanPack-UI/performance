<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Speculative\SpeculativeRulesGenerator;

it( 'returns an empty rules document when no configuration is supplied', function (): void {
    $generator = new SpeculativeRulesGenerator;

    expect( $generator->generate( [] ) )->toBe( '{}' );
} );

it( 'generates a prefetch block with default selector when only eagerness is given', function (): void {
    $generator = new SpeculativeRulesGenerator;

    $json = $generator->generate( [
        'prefetch' => ['eagerness' => 'moderate'],
    ] );

    $decoded = json_decode( $json, true );

    expect( $decoded )->toHaveKey( 'prefetch' )
        ->and( $decoded['prefetch'] )->toHaveCount( 1 )
        ->and( $decoded['prefetch'][0]['eagerness'] )->toBe( 'moderate' )
        ->and( $decoded['prefetch'][0]['source'] )->toBe( 'document' )
        ->and( $decoded['prefetch'][0]['where']['selector_matches'] )->toContain( 'data-prefetch' )
        ->and( $decoded )->not->toHaveKey( 'prerender' );
} );

it( 'emits include patterns as an href_matches clause', function (): void {
    $generator = new SpeculativeRulesGenerator;

    $json = $generator->generate( [
        'prerender' => [
            'eagerness'        => 'conservative',
            'include_patterns' => ['/products/*', '/blog/*'],
        ],
    ] );

    $decoded = json_decode( $json, true );
    $where   = $decoded['prerender'][0]['where'];

    expect( $where )->toHaveKey( 'or' )
        ->and( $where['or'] )->toHaveCount( 2 )
        ->and( $where['or'][0]['href_matches'] )->toBe( '/products/*' )
        ->and( $where['or'][1]['href_matches'] )->toBe( '/blog/*' );
} );

it( 'wraps exclude patterns in a `not` operator', function (): void {
    $generator = new SpeculativeRulesGenerator;

    $json = $generator->generate( [
        'prefetch' => [
            'eagerness'        => 'moderate',
            'exclude_patterns' => ['/logout', '/admin/*'],
        ],
    ] );

    $decoded = json_decode( $json, true );
    $where   = $decoded['prefetch'][0]['where'];

    expect( $where )->toHaveKey( 'and' )
        ->and( $where['and'][0]['href_matches'] )->toBe( '/*' )
        ->and( $where['and'][1]['not'] )->toHaveKey( 'or' );

    $exclusions = array_map(
        static fn ( array $clause ): string => $clause['href_matches'],
        $where['and'][1]['not']['or'],
    );

    expect( $exclusions )->toBe( ['/logout', '/admin/*'] );
} );

it( 'emits explicit URLs ahead of pattern-based rules', function (): void {
    $generator = new SpeculativeRulesGenerator;

    $json = $generator->generate( [
        'prefetch' => [
            'urls'             => ['/checkout', '/about'],
            'include_patterns' => ['/blog/*'],
            'eagerness'        => 'moderate',
        ],
    ] );

    $decoded = json_decode( $json, true );

    expect( $decoded['prefetch'] )->toHaveCount( 2 )
        ->and( $decoded['prefetch'][0]['urls'] )->toBe( ['/checkout', '/about'] )
        ->and( $decoded['prefetch'][1]['where']['href_matches'] )->toBe( '/blog/*' );
} );

it( 'truncates explicit URL lists to the configured limit', function (): void {
    $generator = new SpeculativeRulesGenerator;

    $json = $generator->generate( [
        'prerender' => [
            'urls'  => ['/a', '/b', '/c', '/d'],
            'limit' => 2,
        ],
    ] );

    $decoded = json_decode( $json, true );

    expect( $decoded['prerender'][0]['urls'] )->toBe( ['/a', '/b'] );
} );

it( 'falls back to the default eagerness for unknown values', function (): void {
    $generator = new SpeculativeRulesGenerator;

    $json = $generator->generate( [
        'prefetch' => ['eagerness' => 'overdrive'],
    ] );

    $decoded = json_decode( $json, true );

    expect( $decoded['prefetch'][0]['eagerness'] )->toBe( SpeculativeRulesGenerator::DEFAULT_EAGERNESS );
} );

it( 'reads configuration from the package config when calling generateFromConfig', function (): void {
    config( ['artisanpack.performance.speculative_loading' => [
        'enabled'  => true,
        'prefetch' => [
            'eagerness'        => 'eager',
            'exclude_patterns' => ['/logout'],
        ],
        'prerender' => [
            'eagerness'        => 'conservative',
            'include_patterns' => ['/checkout'],
        ],
    ]] );

    $generator = new SpeculativeRulesGenerator;
    $json      = $generator->generateFromConfig();
    $decoded   = json_decode( $json, true );

    expect( $decoded )->toHaveKeys( ['prefetch', 'prerender'] )
        ->and( $decoded['prefetch'][0]['eagerness'] )->toBe( 'eager' )
        ->and( $decoded['prerender'][0]['where']['href_matches'] )->toBe( '/checkout' );
} );

it( 'uses an explicit selector when provided', function (): void {
    $generator = new SpeculativeRulesGenerator;

    $json = $generator->generate( [
        'prefetch' => [
            'eagerness' => 'moderate',
            'selector'  => 'a.fast-link',
        ],
    ] );

    $decoded = json_decode( $json, true );

    expect( $decoded['prefetch'][0]['where']['selector_matches'] )->toBe( 'a.fast-link' );
} );
