<?php

/**
 * Conditional script loading strategy.
 *
 * Emits a parked script tag with a non-executable MIME type so the browser
 * neither fetches nor executes the source until a companion runtime hoists
 * it on the configured trigger (`interaction`, `visible`, `idle`). The
 * registration's `loadOn`/`target` are propagated to the `data-load-on`
 * and `data-target` attributes via the shared base so the runtime sees the
 * same hints used by the rest of the script pipeline.
 *
 * Pairing this strategy with the runtime is intentional. Application code
 * scans `script[type="application/x-perf-script"]`, reads `data-src`, and
 * swaps in a live `<script>` once the trigger fires.
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
 * Conditional script loading strategy.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class ConditionalStrategy extends AbstractScriptStrategy
{
	/**
	 * Non-executable MIME type used to park the script.
	 *
	 * Browsers refuse to evaluate scripts whose type is not a recognized
	 * JavaScript MIME, so the source URL stays inert until the runtime
	 * activates it. The literal is centralized here so the runtime and
	 * the tests reference the same value.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const PARKED_TYPE = 'application/x-perf-script';

	/**
	 * Returns the strategy's canonical name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function name(): string
	{
		return 'conditional';
	}

	/**
	 * Renders the registration as a parked `<script>` element.
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
			'<script type="%s" data-src="%s"%s></script>',
			self::PARKED_TYPE,
			$this->escape( $script->src ),
			$this->sharedAttributes( $script ),
		);
	}
}
