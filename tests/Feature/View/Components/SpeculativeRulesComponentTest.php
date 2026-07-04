<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Speculative\PrefetchManager;
use ArtisanPackUI\Performance\Speculative\PrerenderManager;
use ArtisanPackUI\Performance\Support\SpeculativeDirectives;
use Illuminate\Support\Facades\Blade;

beforeEach( function (): void {
    config( ['artisanpack.performance.speculative_loading' => [
        'enabled'  => true,
        'prefetch' => [
            'eagerness'        => 'moderate',
            'exclude_patterns' => ['/logout'],
        ],
        'prerender' => [
            'eagerness'        => 'conservative',
            'limit'            => 2,
            'include_patterns' => [],
        ],
    ]] );

    app()->forgetInstance( PrefetchManager::class );
    app()->forgetInstance( PrerenderManager::class );

    SpeculativeDirectives::flushCspNonce();
} );

afterEach( function (): void {
    SpeculativeDirectives::flushCspNonce();
} );

it( 'renders a speculation-rules script via @speculativeRules', function (): void {
    $html = Blade::render( '@speculativeRules' );

    expect( $html )->toContain( '<script type="speculationrules">' )
        ->and( $html )->toContain( '"prefetch"' )
        ->and( $html )->toContain( '"eagerness":"moderate"' );
} );

it( 'renders a speculation-rules script via x-perf-speculative-rules', function (): void {
    $html = Blade::render( '<x-perf-speculative-rules />' );

    expect( $html )->toContain( '<script type="speculationrules">' )
        ->and( $html )->toContain( '"prefetch"' );
} );

it( 'returns an empty string when the feature flag is disabled', function (): void {
    config( ['artisanpack.performance.speculative_loading.enabled' => false] );

    $html = Blade::render( '@speculativeRules' );

    expect( $html )->toBe( '' );
} );

it( 'treats string falsy env values as disabled', function ( mixed $value ): void {
    config( ['artisanpack.performance.speculative_loading.enabled' => $value] );

    $html = Blade::render( '@speculativeRules' );

    expect( $html )->toBe( '' );
} )->with( [
    'string false' => 'false',
    'string 0'     => '0',
    'string off'   => 'off',
    'integer 0'    => 0,
    'null'         => null,
] );

it( 'emits a nonce attribute when one is registered via useCspNonce', function (): void {
    SpeculativeDirectives::useCspNonce( 'abc123' );

    $html = Blade::render( '@speculativeRules' );

    expect( $html )->toContain( '<script type="speculationrules" nonce="abc123">' );
} );

it( 'resolves a closure-supplied CSP nonce at render time', function (): void {
    $calls = 0;

    SpeculativeDirectives::useCspNonce( function () use ( &$calls ): string {
        $calls++;

        return 'fresh-' . $calls;
    } );

    Blade::render( '@speculativeRules' );
    $second = Blade::render( '@speculativeRules' );

    expect( $second )->toContain( 'nonce="fresh-2"' )
        ->and( $calls )->toBe( 2 );
} );

it( 'hex-encodes script-tag bytes in the rendered JSON', function (): void {
    app( PrefetchManager::class )->register( '/x</script><script>alert(1)</script>', 'high' );

    $html = Blade::render( '@speculativeRules' );

    // The rendered output closes the speculationrules tag exactly once
    // — the URL's `</script>` is hex-encoded as `</script>`
    // (six literal ASCII bytes per angle bracket) so the HTML parser
    // cannot end the tag inside the JSON payload.
    expect( substr_count( $html, '</script>' ) )->toBe( 1 )
        ->and( $html )->not->toContain( '/x</script>' )
        ->and( $html )->toContain( '<' )
        ->and( $html )->toContain( '>' );
} );

it( 'merges PrefetchManager URLs into the generated rules', function (): void {
    app( PrefetchManager::class )->register( ['/about', '/contact'], 'high' );

    $html = Blade::render( '@speculativeRules' );

    expect( $html )->toContain( '"/about"' )
        ->and( $html )->toContain( '"/contact"' );
} );

it( 'merges PrerenderManager URLs and respects the configured limit', function (): void {
    config( ['artisanpack.performance.speculative_loading.prerender.limit' => 1] );

    app( PrerenderManager::class )->register( ['/checkout', '/cart'] );

    $html = Blade::render( '@speculativeRules' );

    expect( $html )->toContain( '"/checkout"' )
        ->and( $html )->not->toContain( '"/cart"' );
} );

it( 'overrides per-page configuration via inline component attributes', function (): void {
    $html = Blade::render(
        '<x-perf-speculative-rules :prefetch="$prefetch" />',
        ['prefetch' => ['eagerness' => 'eager']],
    );

    expect( $html )->toContain( '"eagerness":"eager"' );
} );

it( 'overrides via the directive argument syntax', function (): void {
    $html = Blade::render(
        "@speculativeRules(['prefetch' => ['eagerness' => 'immediate']])",
    );

    expect( $html )->toContain( '"eagerness":"immediate"' );
} );
