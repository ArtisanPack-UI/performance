<?php

/**
 * Script Blade component.
 *
 * Renders a single `<script>` tag using the package's strategy registry.
 * The `strategy` attribute selects the strategy (`defer` by default,
 * with `async`, `module`, and `conditional` available out of the box);
 * conditional-loading hints (`load-on`, `target`) and pass-through
 * `<script>` attributes wired via `$attributes` route through the same
 * shared-attribute pipeline used by directly-registered scripts.
 *
 * Components register their `<script>` tag inline at their use site
 * rather than feeding the global manager, so per-view tags appear where
 * the developer wrote them. Use `Performance::script()` plus
 * `Performance::renderScripts()` for the centralized collection model.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\View\Components;

use ArtisanPackUI\Performance\JavaScript\ScriptManager;
use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use Throwable;

/**
 * Script component class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class PerfScript extends Component
{
	/**
	 * Resolved `<script>` HTML.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $html = '';

	/**
	 * Creates a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string                          $src      Script source URL.
	 * @param string|null                     $strategy Strategy name — defaults to the configured default when null.
	 * @param int|null                        $priority Optional priority (forwarded to the registration; preserved for callers that hand the registration to a custom manager).
	 * @param string|null                     $name     Optional script handle emitted as `data-script-name`.
	 * @param array<int, string>|string|null  $loadOn   Optional conditional loading triggers.
	 * @param string|null                     $target   Optional CSS selector for conditional loading.
	 */
	public function __construct(
		public string $src,
		public ?string $strategy = null,
		public ?int $priority = null,
		public ?string $name = null,
		public string|array|null $loadOn = null,
		public ?string $target = null,
	) {
	}

	/**
	 * Returns the resolved registration with caller-supplied attributes folded in.
	 *
	 * Exposed so the Blade template can hand `$attributes` through and so
	 * tests can inspect the registration without rendering.
	 *
	 * @since 1.0.0
	 *
	 * @param  ComponentAttributeBag|null $attributes Caller-supplied attributes from the Blade tag.
	 *
	 * @return ScriptRegistration
	 */
	public function buildRegistration( ?ComponentAttributeBag $attributes = null ): ScriptRegistration
	{
		$registration = new ScriptRegistration( $this->src );

		$this->applyStrategy( $registration );

		if ( null !== $this->priority ) {
			$registration->priority( $this->priority );
		}

		if ( null !== $this->name && '' !== $this->name ) {
			$registration->name( $this->name );
		}

		if ( null !== $this->loadOn ) {
			$registration->loadOn( $this->loadOn );

			// Empty string / whitespace-only triggers strip to [] inside
			// ScriptRegistration::loadOn(). Without at least one resolved
			// trigger the conditional strategy would park the script under
			// the non-executable MIME type but emit no data-load-on, so the
			// runtime can never activate it. Only auto-switch to
			// conditional when at least one trigger survives normalization.
			if (
				null === $this->strategy
				&& null === $registration->strategy
				&& ! empty( $registration->loadOn )
			) {
				$registration->conditional();
			}
		}

		if ( null !== $this->target && '' !== $this->target ) {
			$registration->target( $this->target );
		}

		foreach ( $this->extractAttributes( $attributes ) as $attrName => $attrValue ) {
			$registration->attribute( $attrName, $attrValue );
		}

		return $registration;
	}

	/**
	 * Renders the resolved registration to the HTML emitted by the template.
	 *
	 * @since 1.0.0
	 *
	 * @param  ComponentAttributeBag|null $attributes Caller-supplied attributes from the Blade tag.
	 *
	 * @return string
	 */
	public function renderScript( ?ComponentAttributeBag $attributes = null ): string
	{
		$this->html = $this->resolveHtml( $attributes );

		return $this->html;
	}

	/**
	 * Returns the view that invokes `renderScript($attributes)` at view-render time.
	 *
	 * Blade's class-component pipeline calls `data()` BEFORE
	 * `withAttributes()` binds caller-supplied attributes, so we can't
	 * pre-compute the HTML in either of those hooks. Returning a
	 * `Closure` from `render()` would handle the timing but routes the
	 * Closure's return string through `Component::extractBladeViewFromString()`,
	 * which writes it to disk and recompiles it as Blade — opening a
	 * `{{ ... }}` injection path via attacker-controlled `src` or
	 * attributes. A static template invoking the `$renderScript()`
	 * method-as-closure (exposed by `Component::data()` with `$this`
	 * bound) reads `$attributes` after Blade has bound them and
	 * `{!! ... !!}` echoes the result verbatim without re-compilation.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'performance::components.perf-script' );
	}

	/**
	 * Pins the strategy on the registration when one was supplied.
	 *
	 * Leaves the registration's strategy null when none was specified so
	 * `ScriptRegistration::strategy()`'s lazy lookup applies the
	 * configured default at render time.
	 *
	 * @since 1.0.0
	 *
	 * @param  ScriptRegistration $registration Transient registration.
	 *
	 * @return void
	 */
	protected function applyStrategy( ScriptRegistration $registration ): void
	{
		if ( null === $this->strategy ) {
			return;
		}

		$registration->strategy = strtolower( trim( $this->strategy ) );
	}

	/**
	 * Pulls additional `<script>` attributes from the component bag.
	 *
	 * Filters out the structural component props (`src`, `strategy`, …)
	 * since they're already represented on the registration. Returns an
	 * associative `name => string-value` map; arrays are skipped because
	 * the script attribute syntax can only encode scalars.
	 *
	 * @since 1.0.0
	 *
	 * @param  ComponentAttributeBag|null $attributes Caller-supplied attributes from the Blade tag.
	 *
	 * @return array<string, string>
	 */
	protected function extractAttributes( ?ComponentAttributeBag $attributes ): array
	{
		if ( null === $attributes ) {
			return [];
		}

		$reserved = [ 'src', 'strategy', 'priority', 'name', 'load-on', 'loadOn', 'target' ];
		$result   = [];

		foreach ( $attributes->getAttributes() as $name => $value ) {
			if ( in_array( $name, $reserved, true ) ) {
				continue;
			}

			if ( is_bool( $value ) ) {
				if ( true === $value ) {
					$result[ (string) $name ] = '';
				}
				continue;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$result[ (string) $name ] = (string) $value;
		}

		return $result;
	}

	/**
	 * Resolves the HTML emitted by the component, swallowing render errors.
	 *
	 * @since 1.0.0
	 *
	 * @param  ComponentAttributeBag|null $attributes Caller-supplied attributes from the Blade tag.
	 *
	 * @return string
	 */
	protected function resolveHtml( ?ComponentAttributeBag $attributes ): string
	{
		$registration = $this->buildRegistration( $attributes );

		try {
			return app( ScriptManager::class )->renderOne( $registration );
		} catch ( Throwable ) {
			$manager = new ScriptManager();

			return $manager->renderOne( $registration );
		}
	}
}
