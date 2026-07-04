<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Css\CriticalCssExtractor;
use Tests\Benchmarks\BenchmarkReport;

it( 'measures critical CSS extraction throughput', function (): void {
    // Build a chunky CSS blob covering the common patterns the
    // extractor tokenizes: at-rules, nested media queries, keyframes,
    // and body selectors.
    $chunk = <<<'CSS'
    @font-face { font-family: 'Foo'; src: url(/foo.woff2); }
    @keyframes spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
    body { margin: 0; font-family: sans-serif; }
    .hero { padding: 4rem; background: hsl(200 30% 20%); }
    .hero .title { font-size: 3rem; }
    .footer { color: gray; padding: 2rem; }
    @media (min-width: 600px) { .hero { padding: 6rem; } }
    @media (min-width: 1200px) { .hero { padding: 8rem; } }
    CSS;

    $css       = str_repeat( $chunk . "\n", 40 );
    $extractor = new CriticalCssExtractor;

    $stats = BenchmarkReport::measure(
        'CriticalCssExtractor::extract()',
        100,
        static fn () => $extractor->extract( $css ),
    );

    $extracted = $extractor->extract( $css );
    printf(
        "  BENCH  critical CSS extraction size reduction              %d → %d bytes  (%.1f%% savings)\n",
        strlen( $css ),
        strlen( $extracted ),
        100 - ( strlen( $extracted ) / strlen( $css ) * 100 ),
    );

    expect( $stats['mean_ms'] )->toBeLessThan( 200.0 );
} );
