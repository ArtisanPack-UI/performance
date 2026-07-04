<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Output\HtmlMinifier;
use Tests\Benchmarks\BenchmarkReport;

it( 'measures HTML minifier throughput on a typical page', function (): void {
    $html = str_repeat(
        <<<'HTML'
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Example page</title>
            <!-- inline comment we should strip -->
        </head>
        <body>
            <header class="site-header">
                <nav>
                    <ul>
                        <li><a href="/">Home</a></li>
                        <li><a href="/pricing">Pricing</a></li>
                    </ul>
                </nav>
            </header>
            <main>
                <article>
                    <h1>Welcome</h1>
                    <p>This is a paragraph with quite a few words that should
                       survive minification while the surrounding whitespace
                       collapses to a single space.</p>
                </article>
            </main>
            <footer>&copy; 2026 Example Inc.</footer>
        </body>
        </html>

        HTML,
        20,
    );

    $minifier = new HtmlMinifier;

    $stats = BenchmarkReport::measure(
        'HtmlMinifier::minify()',
        200,
        static fn () => $minifier->minify( $html ),
    );

    // Size reduction reported inline as a sanity check.
    $original = strlen( $html );
    $minified = strlen( $minifier->minify( $html ) );
    printf(
        "  BENCH  html minifier size reduction                        %d → %d bytes  (%.1f%% savings)\n",
        $original,
        $minified,
        100 - ( $minified / $original * 100 ),
    );

    expect( $stats['mean_ms'] )->toBeLessThan( 200.0 );
} );
