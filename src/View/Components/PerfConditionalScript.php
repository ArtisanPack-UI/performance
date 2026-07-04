<?php

/**
 * Conditional script Blade component.
 *
 * Thin variant of `<x-perf-script>` that pins the conditional strategy
 * and defaults `loadOn` to `visible`. Use when the call site is
 * obviously conditional and you want to skip the explicit
 * `strategy="conditional"` boilerplate. Pass-through attributes,
 * `priority`, and `name` behave identically to the parent component.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\View\Components;

use ArtisanPackUI\Performance\JavaScript\ScriptRegistration;

/**
 * Conditional script component class.
 *
 *
 * @since      1.0.0
 */
final class PerfConditionalScript extends PerfScript
{
    /**
     * Creates a new component instance.
     *
     * @since 1.0.0
     *
     * @param  string  $src  Script source URL.
     * @param  int|null  $priority  Optional priority forwarded to the registration.
     * @param  string|null  $name  Optional script handle emitted as `data-script-name`.
     * @param  array<int, string>|string|null  $loadOn  Conditional loading triggers. Defaults to `visible`.
     * @param  string|null  $target  Optional CSS selector for conditional loading.
     */
    public function __construct(
        string $src,
        ?int $priority = null,
        ?string $name = null,
        string|array|null $loadOn = null,
        ?string $target = null,
    ) {
        parent::__construct(
            src: $src,
            strategy: 'conditional',
            priority: $priority,
            name: $name,
            loadOn: $loadOn ?? 'visible',
            target: $target,
        );
    }

    /**
     * Forces the conditional strategy on the resolved registration.
     *
     * Overriding the parent's behavior guarantees that a caller passing
     * `strategy="defer"` on `<x-perf-conditional-script>` (despite the
     * component's intent) still routes through the conditional strategy.
     *
     * @since 1.0.0
     *
     * @param  ScriptRegistration  $registration  Transient registration.
     */
    protected function applyStrategy( ScriptRegistration $registration ): void
    {
        $registration->conditional();
    }
}
