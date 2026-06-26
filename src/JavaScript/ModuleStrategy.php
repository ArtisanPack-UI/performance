<?php

/**
 * ES module script loading strategy.
 *
 * Emits `<script type="module" src="..."></script>`. Browsers download the
 * module in parallel with HTML parsing and execute it after parsing completes
 * (module scripts are defer-by-default per the HTML spec). Use for modern ES
 * module entry points.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\JavaScript;

/**
 * ES module script loading strategy.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class ModuleStrategy extends AbstractScriptStrategy
{
	/**
	 * Returns the strategy's canonical name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function name(): string
	{
		return 'module';
	}

	/**
	 * Renders the registration as `<script type="module" src="..."></script>`.
	 *
	 * @since 1.0.0
	 *
	 * @param  ScriptRegistration $script The registration to render.
	 *
	 * @return string
	 */
	public function render( ScriptRegistration $script ): string
	{
		return sprintf(
			'<script type="module" src="%s"%s></script>',
			$this->escape( $script->src ),
			$this->sharedAttributes( $script ),
		);
	}
}
