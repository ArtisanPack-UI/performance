<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Output\HtmlMinifier;

beforeEach( function (): void {
    config( [ 'artisanpack.performance.html_minification' => [
        'enabled'              => true,
        'remove_comments'      => true,
        'remove_whitespace'    => true,
        'preserve_line_breaks' => false,
        'exclude_elements'     => [ 'pre', 'code', 'textarea', 'script', 'style' ],
    ] ] );
} );

it( 'removes HTML comments', function (): void {
    $minifier = new HtmlMinifier;

    $minified = $minifier->minify( '<div><!-- todo: remove --><p>hi</p></div>' );

    expect( $minified )->not->toContain( 'todo:' );
    expect( $minified )->toContain( '<p>hi</p>' );
} );

it( 'preserves IE conditional comments', function (): void {
    $minifier = new HtmlMinifier;

    $html = '<head><!--[if IE 9]><link rel="stylesheet" href="ie9.css"><![endif]--></head>';

    expect( $minifier->minify( $html ) )->toContain( '[if IE 9]' );
} );

it( 'collapses runs of whitespace inside text content', function (): void {
    $minifier = new HtmlMinifier;

    // Inter-tag whitespace collapses to a single space rather than being
    // stripped — stripping would mangle meaningful whitespace between
    // inline elements (e.g. `<a>x</a> <a>y</a>` rendering as `xy`).
    $minified = $minifier->minify( "<div>\n    <p>Hello    World</p>\n</div>" );

    expect( $minified )->toBe( '<div> <p>Hello World</p> </div>' );
} );

it( 'preserves meaningful whitespace between inline elements', function (): void {
    $minifier = new HtmlMinifier;

    // The single space between two adjacent anchors is semantically
    // meaningful — stripping it would render `docsfaq` instead of
    // `docs faq`.
    $minified = $minifier->minify( '<p>See <a href="/x">docs</a> <a href="/y">faq</a></p>' );

    expect( $minified )->toBe( '<p>See <a href="/x">docs</a> <a href="/y">faq</a></p>' );
} );

it( 'preserves whitespace inside attribute values', function (): void {
    $minifier = new HtmlMinifier;

    // The naive `/\s+/` pass over the whole document would collapse
    // `alt="Hello   World"` to `alt="Hello World"`, silently changing
    // visible attribute content that the server emitted intentionally.
    $minified = $minifier->minify( '<input alt="Hello   World" value="line one&#10;line two">' );

    expect( $minified )->toContain( 'alt="Hello   World"' );
    expect( $minified )->toContain( 'value="line one&#10;line two"' );
} );

it( 'preserves comment-shaped substrings inside attribute values', function (): void {
    $minifier = new HtmlMinifier;

    // A `<!--` substring legitimately appearing inside an attribute
    // value must NOT be matched by the comment-strip pass.
    $minified = $minifier->minify( '<a title="<!-- not a comment -->">link</a>' );

    expect( $minified )->toContain( '<!-- not a comment -->' );
} );

it( 'handles nested same-element markup without orphaning closing tags', function (): void {
    $minifier = new HtmlMinifier;

    // A non-greedy regex over the document would pair the first <pre>
    // with the first </pre> and leave the second </pre> stranded.
    $minified = $minifier->minify( '<pre><pre>inner</pre></pre>' );

    expect( $minified )->toBe( '<pre><pre>inner</pre></pre>' );
} );

it( 'aborts cleanly when the input contains the placeholder sentinel byte', function (): void {
    $minifier = new HtmlMinifier;

    // \x02 (STX) is reserved as the placeholder sentinel. If user
    // content contains it, return the bytes untouched rather than
    // risk corruption.
    $html = "<div>safe\x02unsafe</div>";

    expect( $minifier->minify( $html ) )->toBe( $html );
} );

it( 'preserves whitespace inside excluded elements', function (): void {
    $minifier = new HtmlMinifier;

    $html = "<div>\n  <pre>foo\n    bar\n    baz</pre>\n</div>";

    $minified = $minifier->minify( $html );

    expect( $minified )->toContain( "foo\n    bar\n    baz" );
} );

it( 'preserves the contents of script and style blocks', function (): void {
    $minifier = new HtmlMinifier;

    $html = "<html><head><style>\n  .foo {\n    color: red;\n  }\n</style><script>\nvar a = 1;\nvar b = 2;\n</script></head></html>";

    $minified = $minifier->minify( $html );

    expect( $minified )->toContain( ".foo {\n    color: red;\n  }" );
    expect( $minified )->toContain( "var a = 1;\nvar b = 2;" );
} );

it( 'preserves textarea contents', function (): void {
    $minifier = new HtmlMinifier;

    $html = "<form>\n  <textarea>line one\n  line two</textarea>\n</form>";

    $minified = $minifier->minify( $html );

    expect( $minified )->toContain( "line one\n  line two" );
} );

it( 'preserves line breaks when preserve_line_breaks is enabled', function (): void {
    config( [ 'artisanpack.performance.html_minification.preserve_line_breaks' => true ] );

    $minifier = new HtmlMinifier;

    $minified = $minifier->minify( "Hello\n\nWorld" );

    expect( $minified )->toBe( "Hello\nWorld" );
} );

it( 'skips minification when disabled', function (): void {
    config( [ 'artisanpack.performance.html_minification.enabled' => false ] );

    $minifier = new HtmlMinifier;
    $html     = "<div>\n  <!-- keep this -->\n  <p>Hi</p>\n</div>";

    expect( $minifier->minify( $html ) )->toBe( $html );
} );

it( 'returns empty string for empty input', function (): void {
    expect( ( new HtmlMinifier )->minify( '' ) )->toBe( '' );
} );

it( 'keeps comments when remove_comments is disabled', function (): void {
    config( [ 'artisanpack.performance.html_minification.remove_comments' => false ] );

    $minifier = new HtmlMinifier;

    expect( $minifier->minify( '<div><!-- keep --></div>' ) )->toContain( '<!-- keep -->' );
} );

it( 'achieves a meaningful size reduction on a realistic snippet', function (): void {
    $original = <<<HTML
    <div class="container">
        <h1>Welcome</h1>

        <!-- nav -->
        <nav>
            <a href="/">Home</a>
            <a href="/about">About</a>
        </nav>

        <p>
            Lorem ipsum
            dolor sit amet.
        </p>
    </div>
    HTML;

    $minified = ( new HtmlMinifier )->minify( $original );

    expect( strlen( $minified ) )->toBeLessThan( strlen( $original ) );
    expect( $minified )->toContain( '<h1>Welcome</h1>' );
    expect( $minified )->not->toContain( '<!-- nav -->' );
} );
