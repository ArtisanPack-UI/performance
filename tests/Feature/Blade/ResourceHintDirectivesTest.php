<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'renders @preconnect as a link element', function (): void {
    $html = Blade::render( "@preconnect('https://fonts.googleapis.com')" );

    expect( $html )->toContain( '<link' )
        ->and( $html )->toContain( 'rel="preconnect"' )
        ->and( $html )->toContain( 'href="https://fonts.googleapis.com"' );
} );

it( 'accepts a crossorigin argument on @preconnect', function (): void {
    $html = Blade::render( "@preconnect('https://fonts.gstatic.com', 'anonymous')" );

    expect( $html )->toContain( 'crossorigin="anonymous"' );
} );

it( 'renders @dnsPrefetch as a link element', function (): void {
    $html = Blade::render( "@dnsPrefetch('https://analytics.example.com')" );

    expect( $html )->toContain( 'rel="dns-prefetch"' )
        ->and( $html )->toContain( 'href="https://analytics.example.com"' );
} );

it( 'renders @preload with as and type', function (): void {
    $html = Blade::render( "@preload('/fonts/inter.woff2', 'font', 'font/woff2', 'anonymous')" );

    expect( $html )->toContain( 'rel="preload"' )
        ->and( $html )->toContain( 'href="/fonts/inter.woff2"' )
        ->and( $html )->toContain( 'as="font"' )
        ->and( $html )->toContain( 'type="font/woff2"' )
        ->and( $html )->toContain( 'crossorigin="anonymous"' );
} );

it( 'renders @prefetch as a link element', function (): void {
    $html = Blade::render( "@prefetch('/js/next-page.js')" );

    expect( $html )->toContain( 'rel="prefetch"' )
        ->and( $html )->toContain( 'href="/js/next-page.js"' );
} );

it( 'fails soft on invalid hrefs rather than blowing up the template', function (): void {
    // The directive contract is to swallow `InvalidArgumentException` so an
    // empty href never breaks Blade — regression guard for the catch block
    // in ResourceHintDirectives.
    $html = Blade::render( "<head>@preconnect('')</head>");

    expect( $html)->toBe( '<head></head>');
});
