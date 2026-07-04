<?php

declare( strict_types=1 );

it( 'ships the speculative-rules.js module with the package', function (): void {
    $path = realpath( __DIR__ . '/../../../resources/js/speculative-rules.js' );

    expect( $path )->not->toBeFalse()
        ->and( is_file( $path ) )->toBeTrue();
} );

it( 'exports SpeculativeLoader and a default instance', function (): void {
    $path = realpath( __DIR__ . '/../../../resources/js/speculative-rules.js' );
    $body = (string) file_get_contents( $path );

    expect( $body )->toContain( 'class SpeculativeLoader' )
        ->and( $body )->toContain( 'export { SpeculativeLoader }' )
        ->and( $body )->toContain( 'export default loader' );
} );

it( 'feature-detects the Speculation Rules API', function (): void {
    $path = realpath( __DIR__ . '/../../../resources/js/speculative-rules.js' );
    $body = (string) file_get_contents( $path );

    expect( $body )->toContain( "HTMLScriptElement.supports( 'speculationrules' )" );
} );

it( 'installs a click handler for embed facades', function (): void {
    $path = realpath( __DIR__ . '/../../../resources/js/speculative-rules.js' );
    $body = (string) file_get_contents( $path );

    expect( $body )->toContain( "closest( '.perf-embed-facade' )" )
        ->and( $body )->toContain( 'data-iframe-url' )
        ->and( $body )->toContain( 'data-mode' )
        ->and( $body )->toContain( 'loadBlockquoteEmbed' )
        ->and( $body )->toContain( 'data-embed-html' )
        ->and( $body )->toContain( 'data-widgets-script' );
} );

it( 'falls back to <link rel="prefetch"> when the API is unsupported', function (): void {
    $path = realpath( __DIR__ . '/../../../resources/js/speculative-rules.js' );
    $body = (string) file_get_contents( $path );

    expect( $body )->toContain( "link.rel  = 'prefetch'" )
        ->and( $body )->toContain( 'data-prefetch' )
        ->and( $body )->toContain( 'data-prerender' );
} );

it( 'exposes a runtime inject() entry point on window', function (): void {
    $path = realpath( __DIR__ . '/../../../resources/js/speculative-rules.js' );
    $body = (string) file_get_contents( $path );

    expect( $body )->toContain( 'window.PerformanceSpeculativeLoader' )
        ->and( $body )->toContain( 'inject(' );
} );
