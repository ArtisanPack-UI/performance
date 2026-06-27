<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'renders the facade markup by default', function (): void {
    $html = Blade::render( '<x-perf-embed provider="youtube" id="dQw4w9WgXcQ" />' );

    expect( $html )->toContain( 'perf-embed-facade' )
        ->and( $html )->toContain( 'data-provider="youtube"' )
        ->and( $html )->toContain( 'data-id="dQw4w9WgXcQ"' )
        ->and( $html )->toContain( 'i.ytimg.com' )
        ->and( $html )->toContain( 'perf-embed-play' );
} );

it( 'renders the iframe eagerly when :lazy is false', function (): void {
    $html = Blade::render(
        '<x-perf-embed provider="vimeo" id="123456789" :lazy="false" />',
    );

    expect( $html )->toContain( '<iframe' )
        ->and( $html )->toContain( 'player.vimeo.com/video/123456789' )
        ->and( $html )->toContain( 'loading="eager"' )
        ->and( $html )->not->toContain( 'loading="lazy"' )
        ->and( $html )->not->toContain( 'perf-embed-facade' );
} );

it( 'emits data-title on the facade so the activated iframe carries the resolved title', function (): void {
    $html = Blade::render(
        '<x-perf-embed provider="youtube" id="dQw4w9WgXcQ" title="Marketing reel" />',
    );

    expect( $html )->toContain( 'data-title="Marketing reel"' );
} );

it( 'renders a blockquote-mode facade for twitter', function (): void {
    $html = Blade::render(
        '<x-perf-embed provider="twitter" id="1349129669258448897" />',
    );

    expect( $html )->toContain( 'data-mode="blockquote"' )
        ->and( $html )->toContain( 'data-widgets-script="https://platform.twitter.com/widgets.js"' )
        ->and( $html )->toContain( 'data-embed-html="' )
        ->and( $html )->not->toContain( 'publish.twitter.com/oembed' );
} );

it( 'inlines the blockquote and widgets script when :lazy is false for twitter', function (): void {
    $html = Blade::render(
        '<x-perf-embed provider="x" id="1349129669258448897" :lazy="false" />',
    );

    expect( $html )->toContain( 'twitter-tweet' )
        ->and( $html )->toContain( 'platform.twitter.com/widgets.js' )
        ->and( $html )->not->toContain( '<iframe' );
} );

it( 'omits the thumbnail wrapper when :show-facade is false but keeps the activator class', function (): void {
    $html = Blade::render(
        '<x-perf-embed provider="youtube" id="dQw4w9WgXcQ" :show-facade="false" />',
    );

    expect( $html )->not->toContain( 'perf-embed-thumbnail' )
        ->and( $html )->not->toContain( 'perf-embed-play' )
        ->and( $html )->toContain( 'perf-embed-facade' )
        ->and( $html )->toContain( 'data-iframe-url=' );
} );

it( 'renders an HTML comment when provided an invalid embed ID', function (): void {
    $html = Blade::render(
        '<x-perf-embed provider="youtube" id="invalid id" />',
    );

    expect( $html )->toContain( '<!-- perf-embed:' )
        ->and( $html )->not->toContain( 'perf-embed-facade' );
} );

it( 'uses a custom title for the play button aria label', function (): void {
    $html = Blade::render(
        '<x-perf-embed provider="youtube" id="dQw4w9WgXcQ" title="Marketing reel" />',
    );

    expect( $html )->toContain( 'aria-label="Marketing reel"' );
} );
