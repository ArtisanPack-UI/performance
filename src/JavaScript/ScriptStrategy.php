<?php

/**
 * Script loading strategy contract.
 *
 * Every concrete strategy describes how a single `ScriptRegistration` should
 * be rendered into HTML — defer attribute, async attribute, module type, or
 * inline body. `ScriptManager` resolves a strategy by name when rendering so
 * registrations stay decoupled from their final HTML representation and
 * applications can swap in their own strategies via the manager's resolver.
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
 * Script loading strategy contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
interface ScriptStrategy
{
	/**
	 * Returns the strategy's canonical name (`defer`, `async`, `module`, `inline`).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * Renders the given registration to an HTML `<script>` element.
	 *
	 * @since 1.0.0
	 *
	 * @param  ScriptRegistration $script The script registration to render.
	 *
	 * @return string
	 */
	public function render( ScriptRegistration $script ): string;
}
