<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Css\CriticalCssExtractor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

it( 'returns an empty string for empty input', function (): void {
    $extractor = new CriticalCssExtractor;

    expect( $extractor->extract( '' ) )->toBe( '' );
} );

it( 'keeps rules whose selectors match the critical heuristic', function (): void {
    $css = <<<'CSS'
body { margin: 0; }
.hero { background: red; }
.footer { color: gray; }
.heroic-stat { color: orange; }
CSS;

    $result = (new CriticalCssExtractor)->extract( $css );

    expect( $result )->toContain( 'body' )
        ->and( $result )->toContain( '.hero' )
        ->and( $result )->not->toContain( '.footer' )
        ->and( $result )->not->toContain( '.heroic-stat' );
} );

it( 'matches critical selectors at any token position', function (): void {
    $css = <<<'CSS'
.hero .title { font-size: 32px; }
.wrapper > .col { padding: 16px; }
CSS;

    $result = (new CriticalCssExtractor)->extract( $css );

    expect( $result )->toContain( '.hero .title' )
        ->and( $result )->toContain( '.wrapper > .col' );
} );

it( 'always preserves @font-face and @keyframes', function (): void {
    $css = <<<'CSS'
@font-face { font-family: 'Foo'; src: url(/f.woff2); }
@keyframes spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
.unused { color: red; }
CSS;

    $result = (new CriticalCssExtractor)->extract( $css );

    expect( $result )->toContain( '@font-face' )
        ->and( $result )->toContain( '@keyframes spin' )
        ->and( $result )->not->toContain( '.unused' );
} );

it( 'drops @media min-width queries above the critical viewport', function (): void {
    $css = <<<'CSS'
@media (min-width: 2000px) {
	body { background: green; }
}
@media (min-width: 600px) {
	.hero { padding: 32px; }
}
CSS;

    $result = (new CriticalCssExtractor)->extract( $css, 1300 );

    expect( $result )->toContain( '@media (min-width: 600px)' )
        ->and( $result )->not->toContain( '@media (min-width: 2000px)' )
        ->and( $result )->not->toContain( 'background: green' );
} );

it( 'strips block comments before parsing', function (): void {
    $css = '/* comment */ body { color: red; } /* trailing */';

    $result = (new CriticalCssExtractor)->extract( $css );

    expect( $result )->toContain( 'body' )
        ->and( $result )->not->toContain( 'comment' );
} );

it( 'accepts user-supplied critical selectors from config', function (): void {
    config( ['artisanpack.performance.css.critical.selectors' => ['.brand']] );

    $result = (new CriticalCssExtractor)->extract( '.brand { color: gold; } .ignore { color: gray; }' );

    expect( $result )->toContain( '.brand' )
        ->and( $result )->not->toContain( '.ignore' );
} );

it( 'registers raw CSS content per route and extracts on demand', function (): void {
    $extractor = new CriticalCssExtractor;

    $extractor->registerSource( 'home', 'body { color: red; } .footer { color: gray; }' );

    expect( $extractor->generate( 'home' ) )->toContain( 'body' )
        ->and( $extractor->generate( 'home' ) )->not->toContain( 'footer' );
} );

it( 'registers a CSS file path per route and reads the file at generate time', function (): void {
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'critical-source.css';
    file_put_contents( $file, 'header { padding: 8px; } .footer { color: gray; }' );

    $extractor = new CriticalCssExtractor;
    $extractor->registerSource( 'home', $file );

    $result = $extractor->generate( 'home' );

    expect( $result )->toContain( 'header' )
        ->and( $result )->not->toContain( '.footer' );

    @unlink( $file );
} );

it( 'throws when a registered path cannot be read', function (): void {
    $extractor = new CriticalCssExtractor;
    $extractor->registerSource( 'home', '/does/not/exist.css' );

    expect( fn () => $extractor->generate( 'home' ) )
        ->toThrow( RuntimeException::class, 'is not readable' );
} );

it( 'falls back to the default route when no sources are registered for the route', function (): void {
    $extractor = new CriticalCssExtractor;
    $extractor->registerSource( 'default', 'body { color: red; }' );

    expect( $extractor->generate( 'home' ) )->toContain( 'body' );
} );

it( 'caches generated CSS per route when caching is enabled', function (): void {
    config( ['artisanpack.performance.css.critical.cache' => true] );

    $cache     = new Repository( new ArrayStore );
    $extractor = new CriticalCssExtractor( $cache );

    $extractor->registerSource( 'home', 'body { color: red; }' );

    $first = $extractor->forRoute( 'home' );
    // Mutate the registered source — cached result should NOT change.
    $extractor->registerSource( 'home', '.hero { color: blue; }' );
    $second = $extractor->forRoute( 'home' );

    expect( $second )->toBe( $first );
} );

it( 'skips the cache when caching is disabled', function (): void {
    config( ['artisanpack.performance.css.critical.cache' => false] );

    $cache     = new Repository( new ArrayStore );
    $extractor = new CriticalCssExtractor( $cache );

    $extractor->registerSource( 'home', 'body { color: red; }' );
    $first = $extractor->forRoute( 'home' );

    $extractor->registerSource( 'home', '.hero { color: blue; }' );
    $second = $extractor->forRoute( 'home' );

    expect( $second )->not->toBe( $first );
} );

it( 'clears cached entries via clearCache()', function (): void {
    config( ['artisanpack.performance.css.critical.cache' => true] );

    $cache     = new Repository( new ArrayStore );
    $extractor = new CriticalCssExtractor( $cache );

    $extractor->registerSource( 'home', 'body { color: red; }' );
    $extractor->forRoute( 'home' );

    $extractor->clearCache( 'home' );
    $extractor->registerSource( 'home', '.hero { color: blue; }' );

    expect( $extractor->forRoute( 'home' ) )->toContain( '.hero' );
} );

it( 'inlines a <style data-critical> block when CSS is registered', function (): void {
    $extractor = new CriticalCssExtractor;
    $extractor->registerSource( 'home', 'body { margin: 0; }' );

    $inline = $extractor->inlineFor( 'home' );

    expect( $inline )->toStartWith( '<style data-critical="home">' )
        ->and( $inline )->toContain( 'body' )
        ->and( $inline )->toEndWith( '</style>' );
} );

it( 'returns an empty inline block when no critical CSS exists', function (): void {
    $extractor = new CriticalCssExtractor;

    expect( $extractor->inlineFor( 'missing' ) )->toBe( '' );
} );

it( 'preserves body-less at-rules like @import and @charset', function (): void {
    $css = <<<'CSS'
@charset "UTF-8";
@import url('reset.css');
body { margin: 0; }
.footer { color: gray; }
CSS;

    $result = (new CriticalCssExtractor)->extract( $css );

    expect( $result )->toContain( '@charset "UTF-8"' )
        ->and( $result )->toContain( "@import url('reset.css')" )
        ->and( $result )->toContain( 'body' )
        ->and( $result )->not->toContain( '.footer' );
} );

it( 'does not classify attribute substring selectors as critical via the universal selector', function (): void {
    // Regression: `*` as a critical pattern previously matched any selector
    // containing `*` (including `[data-name*="footer"]`), wrongly preserving
    // below-the-fold rules.
    $css = <<<'CSS'
[data-name*="footer"] { display: none; }
.footer { display: none; }
CSS;

    $result = (new CriticalCssExtractor)->extract( $css);

    expect( $result)->not->toContain( 'data-name')
        ->and( $result)->not->toContain( '.footer');
});

it( 'lists registered routes', function (): void {
    $extractor = new CriticalCssExtractor;
    $extractor->registerSource( 'home', 'body{}');
    $extractor->registerSource( 'contact', 'header{}');

    expect( $extractor->registeredRoutes())->toEqualCanonicalizing( ['home', 'contact']);
});
