<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Contracts\ResourceHintProvider;
use ArtisanPackUI\Performance\Output\ResourceHint;
use ArtisanPackUI\Performance\Output\ResourceHintInjector;

beforeEach( function (): void {
	config( [ 'artisanpack.performance.resource_hints' => [
		'auto_generate'  => false,
		'preconnect'     => [],
		'dns_prefetch'   => [],
		'preload'        => [],
		'prefetch'       => [],
		'exclude_routes' => [],
	] ] );
} );

it( 'registers preconnect hints via the fluent helper', function (): void {
	$injector = new ResourceHintInjector();

	$injector->preconnect( 'https://fonts.googleapis.com' );

	$hints = $injector->all();

	expect( $hints )->toHaveCount( 1 )
		->and( $hints[0]->rel )->toBe( 'preconnect' )
		->and( $hints[0]->href )->toBe( 'https://fonts.googleapis.com' );
} );

it( 'pulls hints from config in canonical source order', function (): void {
	config( [ 'artisanpack.performance.resource_hints' => [
		'auto_generate' => false,
		'preconnect'    => [ 'https://fonts.googleapis.com' ],
		'dns_prefetch'  => [ 'https://analytics.example.com' ],
		'preload'       => [
			[ 'href' => '/fonts/inter.woff2', 'as' => 'font', 'crossorigin' => 'anonymous' ],
		],
		'prefetch'      => [ '/js/next-page.js' ],
	] ] );

	$rels = array_map(
		static fn ( ResourceHint $hint ): string => $hint->rel,
		( new ResourceHintInjector() )->all(),
	);

	expect( $rels )->toBe( [ 'preconnect', 'dns-prefetch', 'preload', 'prefetch' ] );
} );

it( 'deduplicates against config hints by (rel, href, as)', function (): void {
	config( [ 'artisanpack.performance.resource_hints.preconnect' => [ 'https://fonts.googleapis.com' ] ] );

	$injector = new ResourceHintInjector();
	$injector->preconnect( 'https://fonts.googleapis.com' );

	expect( $injector->all() )->toHaveCount( 1 );
} );

it( 'keeps preloads with different as values separate', function (): void {
	$injector = new ResourceHintInjector();
	$injector->preload( 'https://example.test/inter.woff2', as: 'font' );
	$injector->preload( 'https://example.test/inter.woff2', as: 'style' );

	expect( $injector->all() )->toHaveCount( 2 );
} );

it( 'filters hints by rel when rendering', function (): void {
	$injector = new ResourceHintInjector();
	$injector->preconnect( 'https://fonts.googleapis.com' );
	$injector->dnsPrefetch( 'https://analytics.example.com' );

	$html = $injector->render( [ 'preconnect' ] );

	expect( $html )->toContain( 'fonts.googleapis.com' )
		->and( $html )->not->toContain( 'analytics.example.com' );
} );

it( 'returns Link header values without href-grammar-breaking hints', function (): void {
	$injector = new ResourceHintInjector();
	$injector->preconnect( 'https://fonts.googleapis.com' );

	// Push a CR-laden href in via the auto-detected pool — manual would
	// short-circuit at ResourceHint construction.
	$injector->addAutoDetected( new ResourceHint( rel: 'preconnect', href: "https://evil.test\r\nLocation: /" ) );

	$headers = $injector->toLinkHeaders();

	expect( $headers )->toHaveCount( 1 )
		->and( $headers[0] )->toContain( 'fonts.googleapis.com' );
} );

it( 'invokes providers at render time', function (): void {
	$provider = new class implements ResourceHintProvider {
		public function hints(): array
		{
			return [
				new ResourceHint( rel: 'preload', href: '/js/dynamic.js', as: 'script' ),
			];
		}
	};

	$injector = ( new ResourceHintInjector() )->registerProvider( $provider );

	expect( $injector->all() )->toHaveCount( 1 )
		->and( $injector->all()[0]->href )->toBe( '/js/dynamic.js' );
} );

it( 'clears manual and auto-detected hints but preserves providers', function (): void {
	$provider = new class implements ResourceHintProvider {
		public function hints(): array
		{
			return [ new ResourceHint( rel: 'preconnect', href: 'https://providers.test' ) ];
		}
	};

	$injector = ( new ResourceHintInjector() )->registerProvider( $provider );
	$injector->preconnect( 'https://manual.test' );
	$injector->addAutoDetected( new ResourceHint( rel: 'preconnect', href: 'https://auto.test' ) );

	$injector->clear();

	$hosts = array_map(
		static fn ( ResourceHint $hint ): string => $hint->href,
		$injector->all(),
	);

	expect( $hosts )->toBe( [ 'https://providers.test' ] );
} );
