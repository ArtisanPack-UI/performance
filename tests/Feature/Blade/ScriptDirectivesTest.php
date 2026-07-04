<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\JavaScript\ConditionalStrategy;
use Illuminate\Support\Facades\Blade;

it( 'renders @deferScript as a defer script tag', function (): void {
    $html = Blade::render( "@deferScript('/js/analytics.js')" );

    expect( $html )->toBe( '<script src="/js/analytics.js" defer></script>' );
} );

it( 'renders @asyncScript as an async script tag', function (): void {
    $html = Blade::render( "@asyncScript('/js/widget.js')" );

    expect( $html )->toBe( '<script src="/js/widget.js" async></script>' );
} );

it( 'renders @moduleScript as a module script tag', function (): void {
    $html = Blade::render( "@moduleScript('/js/app.mjs')" );

    expect( $html )->toBe( '<script type="module" src="/js/app.mjs"></script>' );
} );

it( 'renders @conditionalScript as a parked script with data-load-on/data-target', function (): void {
    $html = Blade::render( "@conditionalScript('/js/heavy.js', 'visible', '#comments')" );

    expect( $html )->toContain( 'type="' . ConditionalStrategy::PARKED_TYPE . '"' )
        ->and( $html )->toContain( 'data-src="/js/heavy.js"' )
        ->and( $html )->toContain( 'data-load-on="visible"' )
        ->and( $html )->toContain( 'data-target="#comments"' )
        ->and( $html )->not->toContain( ' src="/js/heavy.js"' );
} );

it( 'parks the script without firing immediately even when no target is supplied', function (): void {
    // The non-executable MIME type is what guarantees the browser will not
    // fetch the parked script. Even without a target selector the type must
    // still be present so the runtime can pick the script up.
    $html = Blade::render( "@conditionalScript('/js/heavy.js', 'idle')" );

    expect( $html )->toContain( 'type="' . ConditionalStrategy::PARKED_TYPE . '"' )
        ->and( $html )->toContain( 'data-load-on="idle"' )
        ->and( $html )->not->toContain( 'data-target' );
} );

it( 'accepts an array of triggers on @conditionalScript', function (): void {
    $html = Blade::render( "@conditionalScript('/js/heavy.js', ['click', 'visible'], '#widget')" );

    expect( $html )->toContain( 'data-load-on="click visible"' );
} );

it( 'escapes hostile script URLs to prevent breakout', function (): void {
    // Regression: callers can stick anything into the directive expression.
    // The strategy must htmlspecialchars the URL before splatting it into
    // the attribute syntax — otherwise a `"` lets them break out and add
    // `onerror=` payloads.
    $html = Blade::render( "@deferScript('/js/x.js\" onerror=\"alert(1)')" );

    expect( $html )->not->toContain( '" onerror="alert(1)"' )
        ->and( $html )->toContain( '&quot;' );
});
