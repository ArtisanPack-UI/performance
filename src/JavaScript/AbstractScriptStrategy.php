<?php

/**
 * Shared base for script loading strategies.
 *
 * Centralizes the attribute-escaping and `data-load-on`/`data-target` emission
 * so the concrete strategies stay focused on the loading semantic they encode.
 * Attribute values are HTML-escaped before composition so callers can pass
 * user-controlled data (script names, target selectors) into the registration
 * builder without opening an XSS hole when the rendered HTML is echoed into
 * a Blade layout.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\JavaScript;

/**
 * Shared base for script loading strategies.
 *
 *
 * @since      1.0.0
 */
abstract class AbstractScriptStrategy implements ScriptStrategy
{
    /**
     * Returns the strategy's canonical name.
     *
     * @since 1.0.0
     */
    abstract public function name(): string;

    /**
     * Renders the registration to an HTML `<script>` element.
     *
     * @since 1.0.0
     *
     * @param  ScriptRegistration  $script  The script registration to render.
     */
    abstract public function render( ScriptRegistration $script ): string;

    /**
     * Returns the additional attributes shared by every external strategy.
     *
     * Conditional-loading hints (`data-load-on`, `data-target`) and arbitrary
     * attributes attached via `ScriptRegistration::attribute()` are emitted
     * here so each concrete strategy only owns its loading-semantic attribute.
     *
     * @since 1.0.0
     *
     * @param  ScriptRegistration  $script  The registration being rendered.
     *
     * @return string Pre-spaced attribute string (empty when there are none).
     */
    protected function sharedAttributes( ScriptRegistration $script ): string
    {
        $parts = [];

        if ( ! empty( $script->loadOn ) ) {
            $parts[] = 'data-load-on="' . $this->escape( implode( ' ', $script->loadOn ) ) . '"';
        }

        if ( null !== $script->target && '' !== $script->target ) {
            $parts[] = 'data-target="' . $this->escape( $script->target ) . '"';
        }

        if ( null !== $script->name && '' !== $script->name ) {
            $parts[] = 'data-script-name="' . $this->escape( $script->name ) . '"';
        }

        foreach ( $script->attributes as $name => $value ) {
            if ( ! $this->isSafeAttributeName( $name ) ) {
                continue;
            }

            $parts[] = $this->escape( $name ) . '="' . $this->escape( $value ) . '"';
        }

        return empty( $parts ) ? '' : ' ' . implode( ' ', $parts );
    }

    /**
     * HTML-escapes the given value for safe interpolation into attribute syntax.
     *
     * @since 1.0.0
     *
     * @param  string  $value  Caller-supplied value.
     */
    protected function escape( string $value ): string
    {
        return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
    }

    /**
     * Reports whether the given string is a syntactically valid HTML attribute name.
     *
     * Restricts to ASCII letters, digits, dashes, and underscores so malicious
     * attribute names (`onclick`, `"><script>`, …) can't be smuggled through
     * the arbitrary-attribute escape hatch.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Attribute name.
     */
    protected function isSafeAttributeName( string $name ): bool
    {
        return 1 === preg_match( '/^[A-Za-z_][A-Za-z0-9_-]*$/', $name );
    }
}
