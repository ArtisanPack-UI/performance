<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\JavaScript\AsyncStrategy;
use ArtisanPackUI\Performance\JavaScript\DeferStrategy;
use ArtisanPackUI\Performance\JavaScript\InlineStrategy;
use ArtisanPackUI\Performance\JavaScript\ModuleStrategy;
use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;

it( 'DeferStrategy renders src with defer attribute', function (): void {
    $script = (new ScriptRegistration( '/js/app.js' ))->defer();

    $html = (new DeferStrategy)->render( $script );

    expect( $html )->toBe( '<script src="/js/app.js" defer></script>' );
} );

it( 'AsyncStrategy renders src with async attribute', function (): void {
    $script = (new ScriptRegistration( '/js/widget.js' ))->async();

    $html = (new AsyncStrategy)->render( $script );

    expect( $html )->toBe( '<script src="/js/widget.js" async></script>' );
} );

it( 'ModuleStrategy renders src with type=module', function (): void {
    $script = (new ScriptRegistration( '/js/app.mjs' ))->module();

    $html = (new ModuleStrategy)->render( $script );

    expect( $html )->toBe( '<script type="module" src="/js/app.mjs"></script>' );
} );

it( 'InlineStrategy renders inline content between script tags', function (): void {
    $script = (new ScriptRegistration( 'inline' ))->inline( "console.log('hi');" );

    $html = (new InlineStrategy)->render( $script );

    expect( $html )->toBe( "<script>console.log('hi');</script>" );
} );

it( 'InlineStrategy escapes embedded </script> sequences', function (): void {
    $script = (new ScriptRegistration( 'inline' ))->inline( 'var s = "</script>";' );

    $html = (new InlineStrategy)->render( $script );

    expect( $html )->toContain( '<\\/script>' )
        ->and( $html )->not->toContain( '"</script>";' );
} );

it( 'escapes hostile content in src and shared attributes', function (): void {
    $script = (new ScriptRegistration( '/js/x.js?q="><script>alert(1)</script>' ))
        ->defer()
        ->name( '"><script>name</script>' )
        ->target( '#a"' );

    $html = (new DeferStrategy)->render( $script );

    expect( $html )->not->toContain( '"><script>alert' )
        ->and( $html )->not->toContain( '"><script>name' )
        ->and( $html )->toContain( 'data-target="#a&quot;"' );
} );

it( 'emits data-load-on for conditional triggers', function (): void {
    $script = (new ScriptRegistration( '/js/widget.js' ))
        ->async()
        ->loadOn( 'visible', 'idle' )
        ->target( '#widget' );

    $html = (new AsyncStrategy)->render( $script );

    expect( $html )->toContain( 'data-load-on="visible idle"' )
        ->and( $html )->toContain( 'data-target="#widget"' );
} );

it( 'reports the canonical strategy name', function (): void {
    expect( (new DeferStrategy)->name() )->toBe( 'defer' )
        ->and( (new AsyncStrategy)->name() )->toBe( 'async' )
        ->and( (new ModuleStrategy)->name() )->toBe( 'module' )
        ->and( (new InlineStrategy)->name() )->toBe( 'inline' );
} );

it( 'allows additional attributes attached via the escape hatch', function (): void {
    $script = (new ScriptRegistration( '/js/x.js' ))
        ->defer()
        ->attribute( 'integrity', 'sha384-abc' )
        ->attribute( 'crossorigin', 'anonymous' );

    $html = (new DeferStrategy)->render( $script );

    expect( $html )->toContain( 'integrity="sha384-abc"' )
        ->and( $html )->toContain( 'crossorigin="anonymous"' );
} );

it( 'drops attribute names with disallowed characters', function (): void {
    $script = (new ScriptRegistration( '/js/x.js'))
        ->defer()
        ->attribute( '"><script', 'x');

    $html = (new DeferStrategy)->render( $script);

    expect( $html)->not->toContain( '"><script');
});
