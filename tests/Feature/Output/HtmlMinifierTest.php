<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Output\HtmlMinifier;

beforeEach( function (): void {
    config( [ 'artisanpack.performance.html_minification' => [
        'enabled'              => true,
        'remove_comments'      => true,
        'remove_whitespace'    => true,
        'preserve_line_breaks' => false,
        'exclude_elements'     => [ 'pre', 'code', 'textarea', 'script' ],
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

it( 'collapses runs of whitespace', function (): void {
    $minifier = new HtmlMinifier;

    $minified = $minifier->minify( "<div>\n    <p>Hello    World</p>\n</div>" );

    expect( $minified )->toBe( '<div><p>Hello World</p></div>' );
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
