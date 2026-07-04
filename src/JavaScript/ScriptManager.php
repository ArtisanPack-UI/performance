<?php

/**
 * Script manager service.
 *
 * Central registry for application JavaScript. Callers register scripts via
 * the fluent `ScriptRegistration` builder (typically `Performance::script($src)`)
 * and the manager keeps them in registration order while exposing them in
 * priority order for rendering. Strategy resolution is pluggable: every
 * registered strategy is looked up by name when `render()` builds the HTML
 * block, so applications can override or extend the four bundled strategies.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\JavaScript;

use RuntimeException;

/**
 * Script manager service.
 *
 *
 * @since      1.0.0
 */
class ScriptManager
{
    /**
     * Registered scripts in insertion order.
     *
     * @since 1.0.0
     *
     * @var array<int, ScriptRegistration>
     */
    protected array $scripts = [];

    /**
     * Strategy renderers keyed by canonical name.
     *
     * @since 1.0.0
     *
     * @var array<string, ScriptStrategy>
     */
    protected array $strategies;

    /**
     * Creates a new script manager seeded with the four bundled strategies.
     *
     * @since 1.0.0
     *
     * @param  array<int, ScriptStrategy>|null  $strategies  Optional strategy overrides. When null the four
     *                                                       bundled strategies (defer/async/module/inline) are
     *                                                       registered.
     */
    public function __construct( ?array $strategies = null )
    {
        $this->strategies = [];

        $bundled = $strategies ?? [
            new DeferStrategy,
            new AsyncStrategy,
            new ModuleStrategy,
            new InlineStrategy,
            new ConditionalStrategy,
        ];

        foreach ( $bundled as $strategy ) {
            $this->registerStrategy( $strategy );
        }
    }

    /**
     * Registers a script source and returns its fluent registration.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Script source URL or path. Pass any placeholder for `inline` scripts.
     */
    public function register( string $src ): ScriptRegistration
    {
        $registration    = new ScriptRegistration( $src );
        $this->scripts[] = $registration;

        return $registration;
    }

    /**
     * Registers (or replaces) a strategy renderer.
     *
     * @since 1.0.0
     *
     * @param  ScriptStrategy  $strategy  Strategy instance.
     */
    public function registerStrategy( ScriptStrategy $strategy ): void
    {
        $this->strategies[ strtolower( $strategy->name() ) ] = $strategy;
    }

    /**
     * Returns every registered script in priority order.
     *
     * Lower priorities render first. Registrations sharing a priority preserve
     * their relative insertion order so the result is deterministic.
     *
     * @since 1.0.0
     *
     * @return array<int, ScriptRegistration>
     */
    public function all(): array
    {
        $indexed = [];

        foreach ( $this->scripts as $index => $script ) {
            $indexed[] = ['order' => $index, 'script' => $script];
        }

        usort(
            $indexed,
            static function ( array $a, array $b ): int {
                if ( $a['script']->priority === $b['script']->priority ) {
                    return $a['order'] <=> $b['order'];
                }

                return $a['script']->priority <=> $b['script']->priority;
            },
        );

        return array_map( static fn ( array $entry ): ScriptRegistration => $entry['script'], $indexed );
    }

    /**
     * Returns the registration with the given handle name.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Handle assigned via `ScriptRegistration::name()`.
     */
    public function find( string $name ): ?ScriptRegistration
    {
        foreach ( $this->scripts as $script ) {
            if ( $script->name === $name ) {
                return $script;
            }
        }

        return null;
    }

    /**
     * Returns true when at least one script has been registered.
     *
     * @since 1.0.0
     */
    public function hasScripts(): bool
    {
        return ! empty( $this->scripts );
    }

    /**
     * Renders every registered script to a newline-joined HTML block.
     *
     * Registrations whose strategy isn't known are skipped silently so
     * application code never explodes when a strategy is renamed or removed
     * mid-deploy. Strict callers can introspect `all()` and call
     * `renderOne()` themselves to fail loudly.
     *
     * @since 1.0.0
     */
    public function render(): string
    {
        $html = [];

        foreach ( $this->all() as $script ) {
            $strategy = $this->resolveStrategy( $script->strategy() );

            if ( null === $strategy ) {
                continue;
            }

            $html[] = $strategy->render( $script );
        }

        return implode( "\n", $html );
    }

    /**
     * Renders a single registration, throwing when its strategy is unknown.
     *
     * @since 1.0.0
     *
     * @param  ScriptRegistration  $script  Registration to render.
     *
     * @throws RuntimeException When no strategy matches the registration.
     */
    public function renderOne( ScriptRegistration $script ): string
    {
        $name     = $script->strategy();
        $strategy = $this->resolveStrategy( $name );

        if ( null === $strategy ) {
            throw new RuntimeException( "No script strategy registered for '{$name}'." );
        }

        return $strategy->render( $script );
    }

    /**
     * Removes every registered script.
     *
     * @since 1.0.0
     */
    public function clear(): void
    {
        $this->scripts = [];
    }

    /**
     * Returns the strategy renderers keyed by canonical name.
     *
     * Exposed primarily for tests and applications that want to introspect
     * the strategy registry without invoking `render()`.
     *
     * @since 1.0.0
     *
     * @return array<string, ScriptStrategy>
     */
    public function strategies(): array
    {
        return $this->strategies;
    }

    /**
     * Resolves a strategy by name.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Strategy name.
     */
    protected function resolveStrategy( string $name ): ?ScriptStrategy
    {
        return $this->strategies[ strtolower( $name ) ] ?? null;
    }
}
