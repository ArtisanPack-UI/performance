<?php

/**
 * Async script loading strategy.
 *
 * Emits `<script src="..." async></script>`. Browsers download the file in
 * parallel with HTML parsing and execute it as soon as the download finishes
 * — execution order between async scripts is not guaranteed. Reserve for
 * independent scripts (analytics, third-party widgets) that don't depend on
 * each other or on the rest of the application bundle.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\JavaScript;

/**
 * Async script loading strategy.
 *
 *
 * @since      1.0.0
 */
class AsyncStrategy extends AbstractScriptStrategy
{
    /**
     * Returns the strategy's canonical name.
     *
     * @since 1.0.0
     */
    public function name(): string
    {
        return 'async';
    }

    /**
     * Renders the registration as `<script src="..." async></script>`.
     *
     * @since 1.0.0
     *
     * @param  ScriptRegistration  $script  The registration to render.
     */
    public function render( ScriptRegistration $script ): string
    {
        return sprintf(
            '<script src="%s" async%s></script>',
            $this->escape( $script->src ),
            $this->sharedAttributes( $script ),
        );
    }
}
