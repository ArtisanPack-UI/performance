<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Support\MetricsChartDirectives;
use Illuminate\Support\Facades\Blade;

it( 'emits both the chart library and bootstrap script tags by default', function (): void {
    $html = MetricsChartDirectives::perfMetricsChartAssets();

    expect( $html )->toContain( MetricsChartDirectives::DEFAULT_CHART_LIBRARY_URL )
        ->toContain( MetricsChartDirectives::DEFAULT_SCRIPT_PATH );
} );

it( 'omits the chart library tag when libraryUrl is blanked', function (): void {
    $html = MetricsChartDirectives::perfMetricsChartAssets( [ 'libraryUrl' => '' ] );

    expect( $html )->not->toContain( MetricsChartDirectives::DEFAULT_CHART_LIBRARY_URL )
        ->toContain( MetricsChartDirectives::DEFAULT_SCRIPT_PATH );
} );

it( 'omits the bootstrap tag when src is blanked', function (): void {
    $html = MetricsChartDirectives::perfMetricsChartAssets( [ 'src' => '' ] );

    expect( $html )->toContain( MetricsChartDirectives::DEFAULT_CHART_LIBRARY_URL )
        ->not->toContain( MetricsChartDirectives::DEFAULT_SCRIPT_PATH );
} );

it( 'honors the chart_library_url config override', function (): void {
    config( [ 'artisanpack.performance.monitoring.chart_library_url' => 'https://example.test/chart.js' ] );

    expect( MetricsChartDirectives::perfMetricsChartAssets() )
        ->toContain( 'https://example.test/chart.js' );
} );

it( 'escapes attribute special characters in the supplied URLs', function (): void {
    $html = MetricsChartDirectives::perfMetricsChartAssets( [
        'libraryUrl' => 'https://evil.test/" onerror="alert(1)',
        'src'        => 'https://evil.test/bootstrap"<script>',
    ] );

    expect( $html )->not->toContain( '" onerror="' )
        ->not->toContain( '"<script>' )
        ->toContain( '&quot;' );
} );

it( 'renders the @perfMetricsChartAssets Blade directive', function (): void {
    $compiled = Blade::compileString( '@perfMetricsChartAssets' );

    expect( $compiled )->toContain( 'perfMetricsChartAssets()' );

    $compiledWithArgs = Blade::compileString( "@perfMetricsChartAssets(['libraryUrl' => 'https://cdn.example/chart.js'])" );

    expect( $compiledWithArgs )->toContain( "perfMetricsChartAssets(['libraryUrl' => 'https://cdn.example/chart.js'])" );
} );
