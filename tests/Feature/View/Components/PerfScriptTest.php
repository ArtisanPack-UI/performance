<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\JavaScript\ConditionalStrategy;
use Illuminate\Support\Facades\Blade;

it( 'renders <x-perf-script> with the defer strategy by default', function (): void {
    config( ['artisanpack.performance.javascript.default_strategy' => 'defer'] );

    $html = Blade::render( '<x-perf-script src="/js/app.js" />' );

    expect( $html )->toContain( '<script src="/js/app.js" defer></script>' );
} );

it( 'switches strategy via the strategy attribute', function (): void {
    $html = Blade::render( '<x-perf-script src="/js/app.js" strategy="async" />' );

    expect( $html )->toContain( '<script src="/js/app.js" async></script>' );
} );

it( 'renders a module script via strategy="module"', function (): void {
    $html = Blade::render( '<x-perf-script src="/js/app.mjs" strategy="module" />' );

    expect( $html )->toContain( '<script type="module" src="/js/app.mjs"></script>' );
} );

it( 'switches to conditional when load-on is supplied without an explicit strategy', function (): void {
    $html = Blade::render( '<x-perf-script src="/js/widget.js" load-on="visible" target="#widget" />' );

    expect( $html )->toContain( 'type="' . ConditionalStrategy::PARKED_TYPE . '"' )
        ->and( $html )->toContain( 'data-src="/js/widget.js"' )
        ->and( $html )->toContain( 'data-load-on="visible"' )
        ->and( $html )->toContain( 'data-target="#widget"' );
} );

it( 'respects an explicit strategy even when load-on is supplied', function (): void {
    // Caller knows what they're doing — load-on may be advisory, picked up
    // by Alpine intersections etc. — so an explicit strategy should win.
    $html = Blade::render( '<x-perf-script src="/js/widget.js" strategy="defer" load-on="visible" />' );

    expect( $html )->toContain( '<script src="/js/widget.js" defer' )
        ->and( $html )->toContain( 'data-load-on="visible"' );
} );

it( 'passes through arbitrary attributes via the attribute bag', function (): void {
    $html = Blade::render( '<x-perf-script src="/js/app.js" integrity="sha384-abc" crossorigin="anonymous" />' );

    expect( $html )->toContain( 'integrity="sha384-abc"' )
        ->and( $html )->toContain( 'crossorigin="anonymous"' );
} );

it( 'emits data-script-name when name is supplied', function (): void {
    $html = Blade::render( '<x-perf-script src="/js/app.js" name="analytics" />' );

    expect( $html )->toContain( 'data-script-name="analytics"' );
} );

it( 'renders <x-perf-conditional-script> with conditional strategy and default load-on', function (): void {
    $html = Blade::render( '<x-perf-conditional-script src="/js/comments.js" target="#comments-section" />' );

    expect( $html )->toContain( 'type="' . ConditionalStrategy::PARKED_TYPE . '"' )
        ->and( $html )->toContain( 'data-src="/js/comments.js"' )
        ->and( $html )->toContain( 'data-load-on="visible"' )
        ->and( $html )->toContain( 'data-target="#comments-section"' );
} );

it( 'forces the conditional strategy on <x-perf-conditional-script> even when overridden', function (): void {
    // Regression: setting strategy="defer" on the conditional component
    // should NOT downgrade to defer — the component exists specifically to
    // park the script. The parent class would let the strategy override
    // through; the child must reassert conditional.
    $html = Blade::render( '<x-perf-conditional-script src="/js/x.js" strategy="defer" load-on="idle" />' );

    expect( $html )->toContain( 'type="' . ConditionalStrategy::PARKED_TYPE . '"' )
        ->and( $html )->not->toContain( ' src="/js/x.js" defer' );
} );

it( 'accepts a list of triggers on load-on', function (): void {
    $html = Blade::render(
        '<x-perf-conditional-script src="/js/widget.js" :load-on="[\'click\', \'mouseover\']" target="#widget-container" />',
    );

    expect( $html )->toContain( 'data-load-on="click mouseover"' );
} );

it( 'does not re-evaluate Blade expressions smuggled through src', function (): void {
    // Regression: returning a Closure from render() routes through
    // Component::extractBladeViewFromString(), which writes the returned
    // string to disk and re-compiles it as Blade. htmlspecialchars does
    // NOT escape `{` `}` `@`, so attacker-controlled `src` containing
    // `{{ ... }}` would be re-evaluated as PHP. The static template
    // pathway must echo the value verbatim instead.
    $html = Blade::render(
        '<x-perf-script :src="$src" />',
        ['src' => '/js/{{ phpinfo() }}.js'],
    );

    expect( $html )->toContain( '/js/{{ phpinfo() }}.js' )
        ->and( $html )->not->toContain( 'PHP Version' )
        ->and( $html )->not->toContain( 'Configuration File (php.ini)' );
} );

it( 'does not park a script when load-on resolves to an empty trigger list', function (): void {
    // Regression: load-on="" (or whitespace-only) used to flip the
    // strategy to conditional but emit no data-load-on, leaving the
    // runtime with no trigger to act on. The component must only
    // auto-switch to conditional when at least one trigger survives.
    $html = Blade::render( '<x-perf-script src="/js/app.js" load-on="" />' );

    expect( $html )->toContain( '<script src="/js/app.js" defer' )
        ->and( $html )->not->toContain( 'application/x-perf-script' );
} );
