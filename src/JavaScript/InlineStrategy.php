<?php

/**
 * Inline script loading strategy.
 *
 * Emits `<script>...body...</script>` for the registration's inline content
 * (set via `ScriptRegistration::inline($body)`). Reserve for tiny, critical
 * scripts where the network round-trip outweighs the bytes — large inline
 * blobs defeat HTTP caching and add to first-paint cost.
 *
 * The inline body is rendered verbatim; callers MUST ensure it is trusted
 * content they own (string literals in their codebase, build artifacts), not
 * user-controlled input. Inlining user-supplied content would constitute an
 * XSS sink. The `</script>` end-tag sequence is escaped at render time so
 * accidentally-bundled markup can't break out of the script block.
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
 * Inline script loading strategy.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class InlineStrategy extends AbstractScriptStrategy
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
		return 'inline';
	}

	/**
	 * Renders the registration as `<script>...inlineContent...</script>`.
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
			'<script%s>%s</script>',
			$this->sharedAttributes( $script ),
			$this->escapeInlineBody( $script->inlineContent ),
		);
	}

	/**
	 * Escapes the inline body so it cannot break out of the `<script>` block.
	 *
	 * Only the `</script` sequence is dangerous in an HTML `<script>` body —
	 * everything else is treated as JS source by the parser. Escaping the `<`
	 * (rather than the `/`) keeps the runtime JS semantically equivalent
	 * (`<\/script>` is still parsed as `</script>` by the JS engine) while
	 * preventing the HTML parser from closing the tag early.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $body Inline script body.
	 *
	 * @return string
	 */
	protected function escapeInlineBody( string $body ): string
	{
		return preg_replace( '#</(script)#i', '<\\/$1', $body ) ?? '';
	}
}
