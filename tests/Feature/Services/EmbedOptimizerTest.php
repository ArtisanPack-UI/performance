<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Services\EmbedOptimizer;

it( 'reports supported providers including the x → twitter alias', function (): void {
    $optimizer = new EmbedOptimizer;

    expect( $optimizer->supports( 'youtube' ) )->toBeTrue()
        ->and( $optimizer->supports( 'vimeo' ) )->toBeTrue()
        ->and( $optimizer->supports( 'twitter' ) )->toBeTrue()
        ->and( $optimizer->supports( 'X' ) )->toBeTrue()
        ->and( $optimizer->supports( 'tiktok' ) )->toBeFalse();
} );

it( 'returns the canonical provider name for aliases', function (): void {
    $optimizer = new EmbedOptimizer;

    expect( $optimizer->canonicalProvider( 'X' ) )->toBe( 'twitter' )
        ->and( $optimizer->canonicalProvider( 'YT' ) )->toBe( 'youtube' );
} );

it( 'builds a youtube facade descriptor with thumbnail and iframe URLs', function (): void {
    $optimizer = new EmbedOptimizer;

    $facade = $optimizer->facade( 'youtube', 'dQw4w9WgXcQ' );

    expect( $facade['provider'] )->toBe( 'youtube' )
        ->and( $facade['id'] )->toBe( 'dQw4w9WgXcQ' )
        ->and( $facade['thumbnail'] )->toContain( 'i.ytimg.com' )
        ->and( $facade['iframe_url'] )->toContain( 'youtube-nocookie.com/embed/dQw4w9WgXcQ' )
        ->and( $facade['title'] )->toBe( 'YouTube video' );
} );

it( 'builds a vimeo facade descriptor', function (): void {
    $optimizer = new EmbedOptimizer;

    $facade = $optimizer->facade( 'vimeo', '123456789' );

    expect( $facade['iframe_url'] )->toContain( 'player.vimeo.com/video/123456789' )
        ->and( $facade['thumbnail'] )->toContain( 'vumbnail.com/123456789' );
} );

it( 'builds a twitter facade descriptor via the x alias', function (): void {
    $optimizer = new EmbedOptimizer;

    $facade = $optimizer->facade( 'x', '1234567890' );

    expect( $facade['provider'] )->toBe( 'twitter' )
        ->and( $facade['mode'] )->toBe( 'blockquote' )
        ->and( $facade['iframe_url'] )->toBe( '' )
        ->and( $facade['embed_html'] )->toContain( 'twitter-tweet' )
        ->and( $facade['embed_html'] )->toContain( 'twitter.com/i/status/1234567890' )
        ->and( $facade['widgets_script'] )->toBe( 'https://platform.twitter.com/widgets.js' )
        ->and( $facade['thumbnail'] )->toBe( '' );
} );

it( 'returns iframe mode for youtube and vimeo with empty blockquote fields', function (): void {
    $optimizer = new EmbedOptimizer;

    $youtube = $optimizer->facade( 'youtube', 'dQw4w9WgXcQ' );
    $vimeo   = $optimizer->facade( 'vimeo', '123456789' );

    expect( $youtube['mode'] )->toBe( 'iframe' )
        ->and( $youtube['embed_html'] )->toBe( '' )
        ->and( $youtube['widgets_script'] )->toBe( '' )
        ->and( $vimeo['mode'] )->toBe( 'iframe' )
        ->and( $vimeo['embed_html'] )->toBe( '' )
        ->and( $vimeo['widgets_script'] )->toBe( '' );
} );

it( 'appends custom params to the iframe URL', function (): void {
    $optimizer = new EmbedOptimizer;

    $facade = $optimizer->facade( 'youtube', 'dQw4w9WgXcQ', [
        'params' => [
            'autoplay' => 1,
            'rel'      => 0,
        ],
    ] );

    expect( $facade['iframe_url'] )->toContain( 'autoplay=1' )
        ->and( $facade['iframe_url'] )->toContain( 'rel=0' );
} );

it( 'allows overriding the thumbnail URL', function (): void {
    $optimizer = new EmbedOptimizer;

    $facade = $optimizer->facade( 'youtube', 'dQw4w9WgXcQ', [
        'thumbnail' => 'https://cdn.example.com/custom.jpg',
    ] );

    expect( $facade['thumbnail'] )->toBe( 'https://cdn.example.com/custom.jpg' );
} );

it( 'rejects unsupported providers', function (): void {
    $optimizer = new EmbedOptimizer;

    $optimizer->facade( 'tiktok', '123' );
} )->throws( InvalidArgumentException::class );

it( 'rejects malformed YouTube IDs', function (): void {
    $optimizer = new EmbedOptimizer;

    $optimizer->facade( 'youtube', 'not a valid id' );
} )->throws( InvalidArgumentException::class );

it( 'rejects non-numeric Vimeo IDs', function (): void {
    $optimizer = new EmbedOptimizer;

    $optimizer->facade( 'vimeo', 'abc123' );
} )->throws( InvalidArgumentException::class);
