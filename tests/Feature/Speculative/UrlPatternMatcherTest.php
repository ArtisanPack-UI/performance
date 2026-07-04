<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Speculative\UrlPatternMatcher;

it( 'matches exact strings when no wildcard is present', function (): void {
    expect( UrlPatternMatcher::matches( '/logout', '/logout' ) )->toBeTrue()
        ->and( UrlPatternMatcher::matches( '/login', '/logout' ) )->toBeFalse();
} );

it( 'matches across path segments with `*`', function (): void {
    expect( UrlPatternMatcher::matches( '/products/42', '/products/*' ) )->toBeTrue()
        ->and( UrlPatternMatcher::matches( '/products/42/reviews', '/products/*' ) )->toBeTrue()
        ->and( UrlPatternMatcher::matches( '/products', '/products/*' ) )->toBeFalse();
} );

it( 'treats ** as an alias for * for readability', function (): void {
    expect( UrlPatternMatcher::matches( '/admin/users/42', '/admin/**' ) )->toBeTrue()
        ->and( UrlPatternMatcher::matches( '/admin/', '/admin/**' ) )->toBeTrue();
} );

it( 'matches trailing wildcards for extensions', function (): void {
    expect( UrlPatternMatcher::matches( '/docs/report.pdf', '*.pdf' ) )->toBeTrue()
        ->and( UrlPatternMatcher::matches( '/docs/report.html', '*.pdf' ) )->toBeFalse();
} );

it( 'returns false for empty pattern lists', function (): void {
    expect( UrlPatternMatcher::matchesAny( '/anything', [] ) )->toBeFalse();
} );

it( 'returns true when any pattern matches the URL', function (): void {
    $patterns = ['/logout', '/admin/*', '*.pdf'];

    expect( UrlPatternMatcher::matchesAny( '/admin/users', $patterns ) )->toBeTrue()
        ->and( UrlPatternMatcher::matchesAny( '/file.pdf', $patterns ) )->toBeTrue()
        ->and( UrlPatternMatcher::matchesAny( '/products/1', $patterns ) )->toBeFalse();
} );
