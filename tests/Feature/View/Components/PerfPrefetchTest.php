<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'renders one link element per URL', function (): void {
    $html = Blade::render(
        '<x-perf-prefetch :urls="$urls" />',
        ['urls' => ['/about', '/contact']],
    );

    expect( substr_count( $html, '<link rel="prefetch"' ) )->toBe( 2 )
        ->and( $html )->toContain( 'href="/about"' )
        ->and( $html )->toContain( 'href="/contact"' );
} );

it( 'accepts a single URL string', function (): void {
    $html = Blade::render( '<x-perf-prefetch urls="/about" />' );

    expect( $html )->toContain( '<link rel="prefetch" href="/about"' );
} );

it( 'deduplicates repeated URLs', function (): void {
    $html = Blade::render(
        '<x-perf-prefetch :urls="$urls" />',
        ['urls' => ['/about', '/about', '/contact']],
    );

    expect( substr_count( $html, '<link rel="prefetch"' ) )->toBe( 2 );
} );

it( 'emits the optional as attribute when supplied', function (): void {
    $html = Blade::render(
        '<x-perf-prefetch urls="/scripts/app.js" as="script" />',
    );

    expect( $html )->toContain( 'as="script"' );
} );

it( 'produces no output for an empty URL list', function (): void {
    $html = Blade::render(
        '<x-perf-prefetch :urls="$urls" />',
        ['urls' => []],
    );

    expect( trim( $html ) )->toBe( '' );
});
