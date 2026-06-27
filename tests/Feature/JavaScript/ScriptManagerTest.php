<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Facades\Performance;
use ArtisanPackUI\Performance\JavaScript\AsyncStrategy;
use ArtisanPackUI\Performance\JavaScript\ConditionalStrategy;
use ArtisanPackUI\Performance\JavaScript\DeferStrategy;
use ArtisanPackUI\Performance\JavaScript\InlineStrategy;
use ArtisanPackUI\Performance\JavaScript\ModuleStrategy;
use ArtisanPackUI\Performance\JavaScript\ScriptManager;
use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;
use ArtisanPackUI\Performance\JavaScript\ScriptStrategy;

it( 'registers a script and returns a fluent ScriptRegistration', function (): void {
	$manager = new ScriptManager();

	$registration = $manager->register( '/js/app.js' );

	expect( $registration )->toBeInstanceOf( ScriptRegistration::class )
		->and( $registration->src )->toBe( '/js/app.js' );
} );

it( 'seeds the five bundled strategies by default', function (): void {
	$strategies = ( new ScriptManager() )->strategies();

	expect( $strategies )->toHaveCount( 5 )
		->and( $strategies )->toHaveKey( 'defer' )
		->and( $strategies )->toHaveKey( 'async' )
		->and( $strategies )->toHaveKey( 'module' )
		->and( $strategies )->toHaveKey( 'inline' )
		->and( $strategies )->toHaveKey( 'conditional' )
		->and( $strategies['defer'] )->toBeInstanceOf( DeferStrategy::class )
		->and( $strategies['async'] )->toBeInstanceOf( AsyncStrategy::class )
		->and( $strategies['module'] )->toBeInstanceOf( ModuleStrategy::class )
		->and( $strategies['inline'] )->toBeInstanceOf( InlineStrategy::class )
		->and( $strategies['conditional'] )->toBeInstanceOf( ConditionalStrategy::class );
} );

it( 'sorts registered scripts by priority ascending', function (): void {
	$manager = new ScriptManager();

	$manager->register( '/js/late.js' )->priority( 50 );
	$manager->register( '/js/early.js' )->priority( 5 );
	$manager->register( '/js/middle.js' );

	$all = $manager->all();

	expect( $all[0]->src )->toBe( '/js/early.js' )
		->and( $all[1]->src )->toBe( '/js/middle.js' )
		->and( $all[2]->src )->toBe( '/js/late.js' );
} );

it( 'preserves registration order between scripts of equal priority', function (): void {
	$manager = new ScriptManager();

	$manager->register( '/js/first.js' );
	$manager->register( '/js/second.js' );
	$manager->register( '/js/third.js' );

	$all = $manager->all();

	expect( array_map( static fn ( ScriptRegistration $r ): string => $r->src, $all ) )
		->toBe( [ '/js/first.js', '/js/second.js', '/js/third.js' ] );
} );

it( 'finds scripts by their declared name', function (): void {
	$manager = new ScriptManager();

	$manager->register( '/js/x.js' )->name( 'analytics' );

	$found = $manager->find( 'analytics' );

	expect( $found )->not->toBeNull()
		->and( $found->src )->toBe( '/js/x.js' );
} );

it( 'renders every registered script to a newline-joined HTML block', function (): void {
	$manager = new ScriptManager();

	$manager->register( '/js/app.js' )->defer()->priority( 1 );
	$manager->register( '/js/analytics.js' )->async()->priority( 5 );

	$html = $manager->render();

	expect( $html )->toContain( '<script src="/js/app.js" defer></script>' )
		->and( $html )->toContain( '<script src="/js/analytics.js" async></script>' )
		->and( $html )->toContain( "\n" );
} );

it( 'silently skips registrations with an unknown strategy when rendering', function (): void {
	$manager          = new ScriptManager();
	$script           = $manager->register( '/js/x.js' );
	$script->strategy = 'unknown';

	expect( $manager->render() )->toBe( '' );
} );

it( 'throws via renderOne() when a strategy is missing', function (): void {
	$manager          = new ScriptManager();
	$script           = $manager->register( '/js/x.js' );
	$script->strategy = 'unknown';

	expect( fn () => $manager->renderOne( $script ) )
		->toThrow( RuntimeException::class, 'unknown' );
} );

it( 'allows replacing or extending strategy renderers', function (): void {
	$custom = new class implements ScriptStrategy {
		public function name(): string
		{
			return 'defer';
		}

		public function render( ScriptRegistration $script ): string
		{
			return '<!-- replaced ' . $script->src . ' -->';
		}
	};

	$manager = new ScriptManager();
	$manager->registerStrategy( $custom );
	$manager->register( '/js/x.js' );

	expect( $manager->render() )->toBe( '<!-- replaced /js/x.js -->' );
} );

it( 'clears all registered scripts', function (): void {
	$manager = new ScriptManager();
	$manager->register( '/js/x.js' );

	expect( $manager->hasScripts() )->toBeTrue();

	$manager->clear();

	expect( $manager->hasScripts() )->toBeFalse();
} );

it( 'returns a ScriptRegistration from the Performance facade', function (): void {
	$registration = Performance::script( '/js/facade.js' );

	expect( $registration )->toBeInstanceOf( ScriptRegistration::class )
		->and( $registration->src )->toBe( '/js/facade.js' );

	expect( Performance::getScripts() )->toHaveCount( 1 );
} );

it( 'renders every facade-registered script via renderScripts()', function (): void {
	// Reset the singleton so prior tests don't leak registrations.
	app()->forgetInstance( ScriptManager::class );

	Performance::script( '/js/one.js' )->defer();
	Performance::script( '/js/two.js' )->async();

	$html = Performance::renderScripts();

	expect( $html )->toContain( '/js/one.js' )
		->and( $html )->toContain( '/js/two.js' );
} );

it( 'reads the default strategy from package config when none is chosen', function (): void {
	$script = new ScriptRegistration( '/js/y.js' );

	// Set config AFTER construction to prove the strategy is resolved lazily —
	// constructor-time reads would miss user overrides applied later in the
	// boot sequence (e.g. a sibling service provider's boot() phase).
	config( [ 'artisanpack.performance.javascript.default_strategy' => 'async' ] );

	expect( $script->strategy() )->toBe( 'async' );
} );

it( 'pins the strategy through explicit setters and short-circuits the lazy lookup', function (): void {
	$script = ( new ScriptRegistration( '/js/x.js' ) )->module();

	config( [ 'artisanpack.performance.javascript.default_strategy' => 'async' ] );

	expect( $script->strategy() )->toBe( 'module' );
} );
