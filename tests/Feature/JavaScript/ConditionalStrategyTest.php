<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Facades\Performance;
use ArtisanPackUI\Performance\JavaScript\ConditionalStrategy;
use ArtisanPackUI\Performance\JavaScript\ScriptManager;
use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;

it( 'ships ConditionalStrategy as a bundled strategy', function (): void {
	$strategies = ( new ScriptManager() )->strategies();

	expect( $strategies )->toHaveKey( 'conditional' )
		->and( $strategies['conditional'] )->toBeInstanceOf( ConditionalStrategy::class );
} );

it( 'parks the script under a non-executable MIME type', function (): void {
	$strategy = new ConditionalStrategy();
	$script   = ( new ScriptRegistration( '/js/heavy.js' ) )->conditional()->loadOn( 'visible' )->target( '#widget' );

	$html = $strategy->render( $script );

	expect( $html )->toContain( 'type="' . ConditionalStrategy::PARKED_TYPE . '"' )
		->and( $html )->toContain( 'data-src="/js/heavy.js"' )
		->and( $html )->toContain( 'data-load-on="visible"' )
		->and( $html )->toContain( 'data-target="#widget"' )
		->and( $html )->not->toContain( ' src="/js/heavy.js"' );
} );

it( 'pins the strategy via ScriptRegistration::conditional()', function (): void {
	$registration = ( new ScriptRegistration( '/js/x.js' ) )->conditional();

	expect( $registration->strategy() )->toBe( 'conditional' );
} );

it( 'renders facade-registered conditional scripts through the bundled strategy', function (): void {
	app()->forgetInstance( ScriptManager::class );

	Performance::script( '/js/heavy.js' )->conditional()->loadOn( 'idle' );

	$html = Performance::renderScripts();

	expect( $html )->toContain( 'type="' . ConditionalStrategy::PARKED_TYPE . '"' )
		->and( $html )->toContain( 'data-load-on="idle"' )
		->and( $html )->toContain( 'data-src="/js/heavy.js"' );
} );
