<?php

/**
 * Defer script loading strategy.
 *
 * Emits `<script src="..." defer></script>`. Browsers download the file in
 * parallel with HTML parsing and execute it after parsing completes, in the
 * order the scripts appear in the document — the right default for most
 * application scripts that depend on the DOM but don't need to block render.
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
 * Defer script loading strategy.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class DeferStrategy extends AbstractScriptStrategy
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
		return 'defer';
	}

	/**
	 * Renders the registration as `<script src="..." defer></script>`.
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
			'<script src="%s" defer%s></script>',
			$this->escape( $script->src ),
			$this->sharedAttributes( $script ),
		);
	}
}
