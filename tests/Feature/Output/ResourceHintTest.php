<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Output\ResourceHint;

it( 'renders a preconnect link element', function (): void {
	$hint = new ResourceHint( rel: 'preconnect', href: 'https://fonts.googleapis.com' );

	expect( $hint->toLinkElement() )
		->toBe( '<link rel="preconnect" href="https://fonts.googleapis.com">' );
} );

it( 'renders a preload link element with as/type/crossorigin', function (): void {
	$hint = new ResourceHint(
		rel: 'preload',
		href: '/fonts/inter.woff2',
		as: 'font',
		type: 'font/woff2',
		crossorigin: 'anonymous',
	);

	$html = $hint->toLinkElement();

	expect( $html )->toContain( 'rel="preload"' )
		->and( $html )->toContain( 'href="/fonts/inter.woff2"' )
		->and( $html )->toContain( 'as="font"' )
		->and( $html )->toContain( 'type="font/woff2"' )
		->and( $html )->toContain( 'crossorigin="anonymous"' );
} );

it( 'emits bare crossorigin when an empty string is provided', function (): void {
	$hint = new ResourceHint( rel: 'preload', href: '/x.woff2', as: 'font', crossorigin: '' );

	expect( $hint->toLinkElement() )->toContain( ' crossorigin>' );
} );

it( 'escapes hostile attribute values to prevent breakout', function (): void {
	// Regression: a caller-supplied href that contains a quote must not
	// break out of the attribute. The link element double-quotes values
	// so the escape pass must convert " into &quot;.
	$hint = new ResourceHint(
		rel: 'preconnect',
		href: 'https://evil.test/"><script>alert(1)</script>',
	);

	$html = $hint->toLinkElement();

	expect( $html )->not->toContain( '"><script>' )
		->and( $html )->toContain( '&quot;' );
} );

it( 'drops unsupported as values rather than passing them through', function (): void {
	$hint = new ResourceHint( rel: 'preload', href: '/x.bin', as: 'magic-format' );

	expect( $hint->toLinkElement() )->not->toContain( 'as=' );
} );

it( 'drops unsupported crossorigin values', function (): void {
	$hint = new ResourceHint( rel: 'preload', href: '/x.bin', crossorigin: 'maybe' );

	expect( $hint->toLinkElement() )->not->toContain( 'crossorigin' );
} );

it( 'drops unsupported fetchpriority values', function (): void {
	$hint = new ResourceHint( rel: 'preload', href: '/x.bin', fetchpriority: 'urgent' );

	expect( $hint->toLinkElement() )->not->toContain( 'fetchpriority' );
} );

it( 'throws on unsupported rel values', function (): void {
	new ResourceHint( rel: 'modulepreload', href: '/x.js' );
} )->throws( InvalidArgumentException::class );

it( 'throws on empty href', function (): void {
	new ResourceHint( rel: 'preconnect', href: '   ' );
} )->throws( InvalidArgumentException::class );

it( 'renders a Link header value', function (): void {
	$hint = new ResourceHint(
		rel: 'preload',
		href: 'https://example.test/inter.woff2',
		as: 'font',
		type: 'font/woff2',
		crossorigin: 'anonymous',
	);

	expect( $hint->toLinkHeader() )
		->toBe( '<https://example.test/inter.woff2>; rel=preload; as=font; type="font/woff2"; crossorigin=anonymous' );
} );

it( 'uses (rel, href, as) as the dedup key', function (): void {
	$a = new ResourceHint( rel: 'preload', href: 'https://fonts.test/inter.woff2', as: 'font' );
	$b = new ResourceHint( rel: 'preload', href: 'https://fonts.test/inter.woff2', as: 'font' );
	$c = new ResourceHint( rel: 'preload', href: 'https://fonts.test/inter.woff2', as: 'style' );

	expect( $a->dedupKey() )->toBe( $b->dedupKey() )
		->and( $a->dedupKey() )->not->toBe( $c->dedupKey() );
} );

it( 'builds a hint from a string config entry', function (): void {
	$hint = ResourceHint::fromConfigEntry( 'preconnect', 'https://fonts.googleapis.com' );

	expect( $hint )->not->toBeNull()
		->and( $hint->rel )->toBe( 'preconnect' )
		->and( $hint->href )->toBe( 'https://fonts.googleapis.com' );
} );

it( 'builds a hint from a verbose config entry', function (): void {
	$hint = ResourceHint::fromConfigEntry( 'preload', [
		'href'        => '/fonts/inter.woff2',
		'as'          => 'font',
		'type'        => 'font/woff2',
		'crossorigin' => 'anonymous',
	] );

	expect( $hint )->not->toBeNull()
		->and( $hint->as )->toBe( 'font' )
		->and( $hint->type )->toBe( 'font/woff2' )
		->and( $hint->crossorigin )->toBe( 'anonymous' );
} );

it( 'lets a verbose entry override the default rel', function (): void {
	$hint = ResourceHint::fromConfigEntry( 'preconnect', [
		'rel'  => 'dns-prefetch',
		'href' => 'https://example.test',
	] );

	expect( $hint->rel )->toBe( 'dns-prefetch' );
} );

it( 'returns null when a config entry is missing href', function (): void {
	expect( ResourceHint::fromConfigEntry( 'preconnect', [ 'rel' => 'preconnect' ] ) )->toBeNull();
} );
