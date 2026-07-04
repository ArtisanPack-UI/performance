<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Monitoring\WebVitals;

it( 'classifies a value at or under the good threshold as good', function (): void {
    expect( WebVitals::classify( 'LCP', 2500.0 ) )->toBe( 'good' )
        ->and( WebVitals::classify( 'LCP', 1800.0 ) )->toBe( 'good' )
        ->and( WebVitals::classify( 'CLS', 0.10 ) )->toBe( 'good' )
        ->and( WebVitals::classify( 'CLS', 0.05 ) )->toBe( 'good' );
} );

it( 'classifies a value between the good and poor thresholds as needs-improvement', function (): void {
    expect( WebVitals::classify( 'LCP', 3500.0 ) )->toBe( 'needs-improvement' )
        ->and( WebVitals::classify( 'INP', 450.0 ) )->toBe( 'needs-improvement' )
        ->and( WebVitals::classify( 'CLS', 0.20 ) )->toBe( 'needs-improvement' );
} );

it( 'classifies a value strictly above the poor threshold as poor', function (): void {
    expect( WebVitals::classify( 'LCP', 4500.0 ) )->toBe( 'poor' )
        ->and( WebVitals::classify( 'INP', 600.0 ) )->toBe( 'poor' )
        ->and( WebVitals::classify( 'CLS', 0.30 ) )->toBe( 'poor' );
} );

it( 'applies metric-specific (not flat-2x) poor thresholds', function (): void {
    // The previous implementation used good * 2 for the poor boundary;
    // these cases would have misclassified under that rule.

    // LCP poor threshold is 4000ms (1.6x of good=2500), not 5000ms.
    expect( WebVitals::classify( 'LCP', 4500.0 ) )->toBe( 'poor' );

    // INP poor threshold is 500ms (2.5x of good=200), not 400ms.
    expect( WebVitals::classify( 'INP', 450.0 ) )->toBe( 'needs-improvement' );

    // FID poor threshold is 300ms (3x of good=100), not 200ms.
    expect( WebVitals::classify( 'FID', 250.0 ) )->toBe( 'needs-improvement' );
} );

it( 'returns unknown for null values or unrecognized metrics', function (): void {
    expect( WebVitals::classify( 'LCP', null ) )->toBe( 'unknown' )
        ->and( WebVitals::classify( 'NOT_A_METRIC', 100.0 ) )->toBe( 'unknown' );
} );

it( 'classifies the boundary points exactly per web.dev guidance', function (): void {
    expect( WebVitals::classify( 'LCP', 4000.0 ) )->toBe( 'needs-improvement' )
        ->and( WebVitals::classify( 'LCP', 4000.001 ) )->toBe( 'poor' )
        ->and( WebVitals::classify( 'INP', 200.0 ) )->toBe( 'good' )
        ->and( WebVitals::classify( 'INP', 200.001 ) )->toBe( 'needs-improvement' );
} );
