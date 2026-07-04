<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\View\Components\LazyImage;
use Illuminate\Support\Facades\Blade;

beforeEach( function (): void {
    clearImageFixtures();
} );

afterEach( function (): void {
    clearImageFixtures();
} );

it( 'renders an img element with native lazy loading and async decoding by default', function (): void {
    $html = Blade::render( '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" />' );

    expect( $html )->toContain( '<img' )
        ->and( $html )->toContain( 'src="/img/hero.jpg"' )
        ->and( $html )->toContain( 'loading="lazy"' )
        ->and( $html )->toContain( 'decoding="async"' )
        ->and( $html )->toContain( 'alt="Hero"' );
} );

it( 'switches to eager loading when lazy is false', function (): void {
    $html = Blade::render( '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" :lazy="false" />' );

    expect( $html )->toContain( 'loading="eager"' )
        ->and( $html )->not->toContain( 'loading="lazy"' );
} );

it( 'emits width and height attributes for CLS prevention', function (): void {
    $html = Blade::render( '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" :width="800" :height="400" />' );

    expect( $html )->toContain( 'width="800"' )
        ->and( $html )->toContain( 'height="400"' );
} );

it( 'applies a dominant color background when placeholder=dominant_color', function (): void {
    $html = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="dominant_color" dominant-color="#3b82f6" />',
    );

    expect( $html )->toContain( 'background-color: #3b82f6;' );
} );

it( 'rejects non-hex dominant-color values rather than smuggling CSS through the style attribute', function (): void {
    // Regression: the style attribute composed raw concatenation of $dominantColor,
    // so a hostile value could close out the declaration and inject additional
    // CSS (e.g. `background-image: url(//attacker.test/leak)`). Component must
    // validate the value against the hex pattern and drop it on mismatch.
    $payload = 'red; background-image: url(//attacker.test/leak)';

    $html = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="dominant_color" :dominant-color="$c" />',
        ['c' => $payload],
    );

    expect( $html )->not->toContain( 'background-image' )
        ->and( $html )->not->toContain( 'attacker.test' );
} );

it( 'accepts three-, four-, six-, and eight-digit hex shorthand colors', function (): void {
    $threeDigit = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="dominant_color" dominant-color="#f3a" />',
    );

    $fourDigit = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="dominant_color" dominant-color="#f3a8" />',
    );

    $withAlpha = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="dominant_color" dominant-color="#3b82f680" />',
    );

    expect( $threeDigit )->toContain( 'background-color: #f3a;' )
        ->and( $fourDigit )->toContain( 'background-color: #f3a8;' )
        ->and( $withAlpha )->toContain( 'background-color: #3b82f680;' );
} );

it( 'rejects fully transparent hex colors that would render an invisible placeholder', function (): void {
    // Regression: `#rrggbb00` and `#rgb0` are syntactically valid hex but
    // resolve to alpha=0 — the placeholder would be invisible and defeat
    // the purpose of the dominant_color strategy. The component must drop
    // them rather than emit a useless background-color declaration.
    $eightDigitTransparent = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="dominant_color" dominant-color="#3b82f600" />',
    );

    $fourDigitTransparent = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="dominant_color" dominant-color="#3b80" />',
    );

    expect( $eightDigitTransparent )->not->toContain( 'background-color:' )
        ->and( $fourDigitTransparent )->not->toContain( 'background-color:' );
} );

it( 'rejects an SVG blur data URI rather than emitting an exfiltration vector', function (): void {
    // Regression: `data:image/svg+xml` is loadable from <img src> but SVG
    // can fire outbound requests via <image href> / <use href> references —
    // a fingerprinting/exfil channel through what was supposed to be a
    // passive placeholder attribute. Whitelist raster types only.
    $html = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="blur" :blur-src="$b" />',
        ['b' => 'data:image/svg+xml;base64,PHN2Zy8+'],
    );

    expect( $html )->toContain( 'src="/img/hero.jpg"' )
        ->and( $html )->not->toContain( 'data:image/svg+xml' )
        ->and( $html )->not->toContain( 'data-src=' );
} );

it( 'accepts a raster blur data URI as the initial src', function (): void {
    // Sanity check on the whitelist — raster mime types must round-trip,
    // otherwise the regex tightening would break the happy path.
    $png  = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC';
    $html = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="blur" :blur-src="$b" />',
        ['b' => $png],
    );

    expect( $html )->toContain( 'src="' . $png . '"' )
        ->and( $html )->toContain( 'data-src="/img/hero.jpg"' );
} );

it( 'ignores a non-image blurSrc data URI rather than rendering it as the initial src', function (): void {
    // Browsers won't render data:text/html via <img>, but the contract says
    // blurSrc must be an image data URI; reject everything else.
    $html = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="blur" :blur-src="$b" />',
        ['b' => 'data:text/html,<script>alert(1)</script>'],
    );

    expect( $html )->toContain( 'src="/img/hero.jpg"' )
        ->and( $html )->not->toContain( 'data:text/html' )
        ->and( $html )->not->toContain( 'data-src=' );
} );

it( 'emits the blur data URI as the initial src when placeholder=blur', function (): void {
    $blur = 'data:image/jpeg;base64,/9j/abc==';
    $html = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" placeholder="blur" :blur-src="$b" />',
        ['b' => $blur],
    );

    expect( $html )->toContain( 'src="' . $blur . '"' )
        ->and( $html )->toContain( 'data-src="/img/hero.jpg"' );
} );

it( 'wraps the img in a skeleton container when placeholder=skeleton', function (): void {
    $html = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" :width="100" :height="50" placeholder="skeleton" />',
    );

    expect( $html )->toContain( 'class="perf-skeleton"' )
        ->and( $html )->toContain( 'aspect-ratio: 100 / 50;' );
} );

it( 'emits fetchpriority when a valid value is supplied', function (): void {
    $html = Blade::render( '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" fetchpriority="high" />' );

    expect( $html )->toContain( 'fetchpriority="high"' );
} );

it( 'ignores unknown fetchpriority values', function (): void {
    $html = Blade::render( '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" fetchpriority="bogus" />' );

    expect( $html )->not->toContain( 'fetchpriority' );
} );

it( 'forwards the configured threshold as a data attribute for JS fallback hooks', function (): void {
    $html = Blade::render( '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" threshold="200px" />' );

    expect( $html )->toContain( 'data-threshold="200px"' );
} );

it( 'appends caller-supplied classes to the img element', function (): void {
    $html = Blade::render( '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" class="rounded shadow" />' );

    expect( $html )->toContain( 'perf-lazy-image rounded shadow' );
} );

it( 'forwards srcset and sizes attributes when provided', function (): void {
    $html = Blade::render(
        '<x-perf-lazy-image src="/img/hero.jpg" alt="Hero" srcset="/img/h-200.jpg 200w" sizes="100vw" />',
    );

    expect( $html )->toContain( 'srcset="/img/h-200.jpg 200w"' )
        ->and( $html )->toContain( 'sizes="100vw"' );
} );

it( 'falls back to none when the placeholder strategy is unknown', function (): void {
    $component = new LazyImage( '/img/hero.jpg', 'Hero', placeholder: 'mystery' );

    expect( $component->resolvedPlaceholder )->toBe( 'none' )
        ->and( $component->shouldUseSkeleton() )->toBeFalse()
        ->and( $component->shouldUseBlurPlaceholder() )->toBeFalse();
});
