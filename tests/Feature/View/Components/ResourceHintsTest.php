<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Output\ResourceHintInjector;
use Illuminate\Support\Facades\Blade;

beforeEach( function (): void {
	config( [ 'artisanpack.performance.resource_hints' => [
		'auto_generate'  => false,
		'preconnect'     => [],
		'dns_prefetch'   => [],
		'preload'        => [],
		'prefetch'       => [],
		'exclude_routes' => [],
	] ] );

	// Reset the injector singleton so config tests don't leak state.
	app()->forgetInstance( ResourceHintInjector::class );
} );

it( 'renders config-driven hints via the component', function (): void {
	config( [ 'artisanpack.performance.resource_hints.preconnect' => [
		'https://fonts.googleapis.com',
	] ] );

	$html = Blade::render( '<x-perf-resource-hints />' );

	expect( $html )->toContain( 'rel="preconnect"' )
		->and( $html )->toContain( 'href="https://fonts.googleapis.com"' );
} );

it( 'renders manually-registered hints via the singleton', function (): void {
	app( ResourceHintInjector::class )->preload(
		href: '/fonts/inter.woff2',
		as: 'font',
		type: 'font/woff2',
		crossorigin: 'anonymous',
	);

	$html = Blade::render( '<x-perf-resource-hints />' );

	expect( $html )->toContain( 'rel="preload"' )
		->and( $html )->toContain( 'as="font"' )
		->and( $html )->toContain( 'crossorigin="anonymous"' );
} );

it( 'renders an inline :hints descriptor list when supplied', function (): void {
	$html = Blade::render(
		'<x-perf-resource-hints :hints="$hints" />',
		[ 'hints' => [
			[ 'rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com' ],
			[ 'rel' => 'preload', 'href' => '/fonts/inter.woff2', 'as' => 'font' ],
		] ],
	);

	expect( $html )->toContain( 'fonts.googleapis.com' )
		->and( $html )->toContain( '/fonts/inter.woff2' )
		->and( $html )->toContain( 'as="font"' );
} );

it( 'short-circuits the injector when :hints is supplied', function (): void {
	// Inline list should NOT be combined with registry contents — that
	// would surprise callers who pass a list to opt out of global hints.
	app( ResourceHintInjector::class )->preconnect( 'https://global.test' );

	$html = Blade::render(
		'<x-perf-resource-hints :hints="$hints" />',
		[ 'hints' => [ 'https://inline.test' ] ],
	);

	expect( $html )->toContain( 'inline.test' )
		->and( $html )->not->toContain( 'global.test' );
} );

it( 'filters by the only attribute', function (): void {
	config( [ 'artisanpack.performance.resource_hints' => [
		'auto_generate' => false,
		'preconnect'    => [ 'https://fonts.googleapis.com' ],
		'dns_prefetch'  => [ 'https://analytics.example.com' ],
		'preload'       => [],
		'prefetch'      => [],
	] ] );

	$html = Blade::render( '<x-perf-resource-hints only="preconnect" />' );

	expect( $html )->toContain( 'fonts.googleapis.com' )
		->and( $html )->not->toContain( 'analytics.example.com' );
} );

it( 'renders nothing when no hints resolve', function (): void {
	$html = Blade::render( '<x-perf-resource-hints />' );

	// Empty body except for the surrounding component shell whitespace.
	expect( trim( $html ) )->toBe( '' );
} );
