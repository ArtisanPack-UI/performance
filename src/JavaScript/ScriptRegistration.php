<?php

/**
 * Script registration descriptor.
 *
 * Fluent builder returned by `ScriptManager::register()` (and `Performance::script()`).
 * Holds the source URL, loading strategy, priority, optional name, inline
 * body, and conditional-loading hints. Doesn't render HTML itself — the
 * configured strategy is responsible for that — so the descriptor stays
 * cheap to construct, serialize, and inspect in tests.
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
 * Script registration descriptor.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class ScriptRegistration
{
	/**
	 * Default priority assigned to scripts that don't call `priority()`.
	 *
	 * Mirrors WordPress's `wp_enqueue_script` default so the values feel
	 * familiar to anyone coming from the WP ecosystem (lower = earlier).
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public const DEFAULT_PRIORITY = 10;

	/**
	 * Resolved strategy name (`defer`, `async`, `module`, `inline`).
	 *
	 * Null until either an explicit setter (`defer()`, `async()`, …) pins it
	 * or `strategy()` is called and lazily resolves the configured default.
	 * Lazy resolution matters because registrations can be built during a
	 * sibling provider's `register()` phase — before `PerformanceServiceProvider::boot()`
	 * has merged the package's config — so a constructor-time read of
	 * `artisanpack.performance.javascript.default_strategy` would always
	 * miss user overrides.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $strategy = null;

	/**
	 * Priority used to order scripts when rendering.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $priority = self::DEFAULT_PRIORITY;

	/**
	 * Optional name used as a lookup handle.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $name = null;

	/**
	 * Conditional-loading triggers (e.g. `interaction`, `visible`, `idle`).
	 *
	 * Emitted as `data-load-on="..."` so application JS can pick up the hint.
	 * Empty when no conditional loading is configured.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public array $loadOn = [];

	/**
	 * Optional CSS selector used by conditional loaders.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $target = null;

	/**
	 * Inline body when the registration uses the `inline` strategy.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $inlineContent = '';

	/**
	 * Extra `<script>` attributes appended verbatim during rendering.
	 *
	 * Values are escaped so callers can pass user-supplied input safely; keys
	 * are restricted to a conservative attribute-name pattern.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $attributes = [];

	/**
	 * Creates a new registration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $src The script source URL or path. Ignored when `inline` is used.
	 */
	public function __construct( public string $src )
	{
		// Strategy is intentionally NOT resolved here — see the docblock on
		// $strategy for why. `strategy()` performs the lazy lookup on first read.
	}

	/**
	 * Returns the resolved strategy name, lazily reading config on first call.
	 *
	 * Explicit calls to `defer()`/`async()`/`module()`/`inline()` short-circuit
	 * this lookup since they pin `$strategy` directly.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function strategy(): string
	{
		if ( null === $this->strategy ) {
			$default        = (string) config( 'artisanpack.performance.javascript.default_strategy', 'defer' );
			$this->strategy = strtolower( $default );
		}

		return $this->strategy;
	}

	/**
	 * Assigns a lookup handle for the registration.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $name Handle name.
	 *
	 * @return $this
	 */
	public function name( string $name ): self
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Switches the strategy to `defer`.
	 *
	 * @since 1.0.0
	 *
	 * @return $this
	 */
	public function defer(): self
	{
		$this->strategy = 'defer';

		return $this;
	}

	/**
	 * Switches the strategy to `async`.
	 *
	 * @since 1.0.0
	 *
	 * @return $this
	 */
	public function async(): self
	{
		$this->strategy = 'async';

		return $this;
	}

	/**
	 * Switches the strategy to `module` (ES module).
	 *
	 * @since 1.0.0
	 *
	 * @return $this
	 */
	public function module(): self
	{
		$this->strategy = 'module';

		return $this;
	}

	/**
	 * Switches the strategy to `inline` and optionally captures the body.
	 *
	 * The `src` already attached to the registration is preserved (renderers
	 * ignore it for inline scripts) so the caller can toggle between strategies
	 * without losing the original URL.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null $content Optional inline body. Pass null to set the strategy without overwriting an existing body.
	 *
	 * @return $this
	 */
	public function inline( ?string $content = null ): self
	{
		$this->strategy = 'inline';

		if ( null !== $content ) {
			$this->inlineContent = $content;
		}

		return $this;
	}

	/**
	 * Sets the rendering priority. Lower values render first.
	 *
	 * @since 1.0.0
	 *
	 * @param  int $priority Priority value.
	 *
	 * @return $this
	 */
	public function priority( int $priority ): self
	{
		$this->priority = $priority;

		return $this;
	}

	/**
	 * Sets conditional-loading triggers.
	 *
	 * Accepts a single trigger string, a list, or multiple positional
	 * arguments — `loadOn('interaction')`, `loadOn(['click', 'visible'])`, and
	 * `loadOn('click', 'visible')` all yield the same registration.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, string>|string $triggers          First trigger, or an array of triggers.
	 * @param  string                    ...$additional     Additional triggers.
	 *
	 * @return $this
	 */
	public function loadOn( string|array $triggers, string ...$additional ): self
	{
		$normalized = is_array( $triggers ) ? $triggers : [ $triggers ];
		$normalized = array_merge( $normalized, $additional );

		$this->loadOn = array_values( array_filter( array_map(
			static fn ( string $trigger ): string => strtolower( trim( $trigger ) ),
			$normalized,
		) ) );

		return $this;
	}

	/**
	 * Sets the CSS selector used by conditional loaders.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $selector CSS selector.
	 *
	 * @return $this
	 */
	public function target( string $selector ): self
	{
		$this->target = $selector;

		return $this;
	}

	/**
	 * Attaches an arbitrary `<script>` attribute (e.g. `integrity`, `crossorigin`).
	 *
	 * @since 1.0.0
	 *
	 * @param  string $name  Attribute name.
	 * @param  string $value Attribute value.
	 *
	 * @return $this
	 */
	public function attribute( string $name, string $value ): self
	{
		$this->attributes[ $name ] = $value;

		return $this;
	}

	/**
	 * Reports whether the registration uses the inline strategy.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isInline(): bool
	{
		return 'inline' === $this->strategy();
	}
}
