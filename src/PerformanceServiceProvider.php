<?php

/**
 * Performance service provider.
 *
 * Bootstraps the Performance package by merging configuration, registering
 * the `performance` container binding, loading and publishing database
 * migrations, and reserving the event-listener registration seam used by
 * subsequent phases.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance;

use ArtisanPackUI\Performance\Console\Commands\GenerateCriticalCssCommand;
use ArtisanPackUI\Performance\Console\Commands\GenerateWebPCommand;
use ArtisanPackUI\Performance\Css\CriticalCssExtractor;
use ArtisanPackUI\Performance\Images\DominantColorExtractor;
use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\JavaScript\ScriptManager;
use ArtisanPackUI\Performance\Output\ResourceHintInjector;
use ArtisanPackUI\Performance\Services\EmbedOptimizer;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use ArtisanPackUI\Performance\Services\ImageService;
use ArtisanPackUI\Performance\Services\PerformanceService;
use ArtisanPackUI\Performance\Speculative\PrefetchManager;
use ArtisanPackUI\Performance\Speculative\PrerenderManager;
use ArtisanPackUI\Performance\Speculative\SpeculativeRulesGenerator;
use ArtisanPackUI\Performance\Support\ResourceHintDirectives;
use ArtisanPackUI\Performance\Support\ScriptDirectives;
use ArtisanPackUI\Performance\Support\SpeculativeDirectives;
use ArtisanPackUI\Performance\View\Components\CriticalCss;
use ArtisanPackUI\Performance\View\Components\LazyImage;
use ArtisanPackUI\Performance\View\Components\PerfConditionalScript;
use ArtisanPackUI\Performance\View\Components\PerfEmbed;
use ArtisanPackUI\Performance\View\Components\PerfPrefetch;
use ArtisanPackUI\Performance\View\Components\PerfScript;
use ArtisanPackUI\Performance\View\Components\ResourceHints;
use ArtisanPackUI\Performance\View\Components\ResponsiveImage;
use ArtisanPackUI\Performance\View\Components\SpeculativeRules;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Performance package.
 *
 *
 * @since      1.0.0
 */
class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Registers package services.
     *
     * Merges the package configuration into a temporary key (re-merged into
     * the unified `artisanpack.performance` config during boot) and binds
     * the PerformanceService singleton accessed via the Performance facade.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/performance.php',
            'artisanpack-performance-temp',
        );

        $this->app->singleton( FormatConverter::class, function ( $app ) {
            return new FormatConverter;
        } );

        $this->app->singleton( DominantColorExtractor::class, function ( $app ) {
            return new DominantColorExtractor;
        } );

        $this->app->singleton( ImageService::class, function ( $app ) {
            return new ImageService(
                $app->make( FormatConverter::class ),
                $app->make( DominantColorExtractor::class ),
            );
        } );

        $this->app->singleton( ResponsiveImageGenerator::class, function ( $app ) {
            return new ResponsiveImageGenerator( $app->make( ImageService::class ) );
        } );

        $this->app->singleton( ScriptManager::class, function () {
            return new ScriptManager;
        } );

        $this->app->singleton( CriticalCssExtractor::class, function () {
            return new CriticalCssExtractor;
        } );

        $this->app->singleton( ResourceHintInjector::class, function () {
            return new ResourceHintInjector;
        } );

        $this->app->singleton( SpeculativeRulesGenerator::class, function () {
            return new SpeculativeRulesGenerator;
        } );

        $this->app->singleton( PrefetchManager::class, function () {
            return new PrefetchManager;
        } );

        $this->app->singleton( PrerenderManager::class, function () {
            return new PrerenderManager;
        } );

        $this->app->singleton( EmbedOptimizer::class, function () {
            return new EmbedOptimizer;
        } );

        $this->app->singleton( 'performance', function ( $app ) {
            return new PerformanceService(
                $app->make( ImageService::class ),
                null,
                $app->make( ScriptManager::class ),
                $app->make( CriticalCssExtractor::class ),
                $app->make( ResourceHintInjector::class ),
                $app->make( SpeculativeRulesGenerator::class ),
                $app->make( PrefetchManager::class ),
                $app->make( PrerenderManager::class ),
                $app->make( EmbedOptimizer::class ),
            );
        } );

        $this->app->singleton( PerformanceService::class, function ( $app ) {
            return $app->make( 'performance' );
        } );
    }

    /**
     * Bootstraps package services.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $this->mergeConfiguration();
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
        $this->loadViewsFrom( __DIR__ . '/../resources/views', 'performance' );
        $this->loadBladeComponents();
        $this->registerBladeDirectives();
        $this->registerCriticalCssSources();
        $this->registerEventListeners();
        $this->registerOctaneResetHooks();
        $this->registerCommands();
        $this->publishConfiguration();
    }

    /**
     * Resets per-request singletons at the start of every Octane request.
     *
     * The prefetch and prerender managers retain URLs registered during a
     * request (via `Performance::prefetch()` / `Performance::prerender()`).
     * Under traditional PHP-FPM each request is a fresh process so the
     * singletons start empty; under Octane / Swoole / RoadRunner the
     * worker survives across requests, so a URL registered for request A
     * would leak into request B's speculation-rules document — a privacy
     * issue when the URLs are user-specific. This hook flushes both
     * managers when Octane is in use.
     *
     * @since 1.0.0
     */
    protected function registerOctaneResetHooks(): void
    {
        if ( ! class_exists( '\Laravel\Octane\Events\RequestReceived' ) ) {
            return;
        }

        $events = $this->app->make( 'events' );

        $events->listen( '\Laravel\Octane\Events\RequestReceived', function ( $event ): void {
            $sandbox = $event->sandbox ?? $this->app;
            $sandbox->make( PrefetchManager::class )->flush();
            $sandbox->make( PrerenderManager::class )->flush();
        } );
    }

    /**
     * Registers the package's Blade components under the `perf` prefix.
     *
     * The components are also published under `resources/views/components`
     * so applications can override the templates without forking the
     * component classes.
     *
     * @since 1.0.0
     */
    protected function loadBladeComponents(): void
    {
        $this->loadViewComponentsAs( 'perf', [
            LazyImage::class,
            ResponsiveImage::class,
            CriticalCss::class,
            ResourceHints::class,
            'script'             => PerfScript::class,
            'conditional-script' => PerfConditionalScript::class,
            'speculative-rules'  => SpeculativeRules::class,
            'prefetch'           => PerfPrefetch::class,
            'embed'              => PerfEmbed::class,
        ] );
    }

    /**
     * Registers the package's console commands.
     *
     * @since 1.0.0
     */
    protected function registerCommands(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->commands( [
                GenerateWebPCommand::class,
                GenerateCriticalCssCommand::class,
            ] );
        }
    }

    /**
     * Registers the package's Blade directives.
     *
     * `@criticalCss` resolves the current route name (falling back to
     * `default` when none is named) and emits the cached critical-CSS
     * `<style>` block produced by `CriticalCssExtractor`.
     *
     * @since 1.0.0
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive( 'criticalCss', function ( string $expression ): string {
            $argument = trim( $expression );

            if ( '' === $argument ) {
                $argument = 'null';
            }

            // Route-name fallback uses CriticalCssExtractor::DEFAULT_ROUTE so
            // the sentinel stays in lockstep with the extractor's own
            // `generate()` fallback. Hardcoding the literal here would let
            // the two call sites drift if the sentinel ever changes.
            return sprintf(
                '<?php echo app(%s)->inlineFor(%s ?? (request()->route()?->getName() ?? %s)); ?>',
                CriticalCssExtractor::class . '::class',
                $argument,
                var_export( CriticalCssExtractor::DEFAULT_ROUTE, true ),
            );
        } );

        // Resource hint directives — each compiles to a single `<link>` element.
        // Argument forwarding is positional and matches the runtime helper
        // (`ResourceHintDirectives::preconnect()` etc.) so optional attributes
        // like `crossorigin` or `as` work without parsing the Blade expression
        // here.
        $resourceHintHelper = ResourceHintDirectives::class;

        Blade::directive( 'preconnect', static fn ( string $expression ): string => sprintf(
            '<?php echo \\%s::preconnect(%s); ?>',
            $resourceHintHelper,
            $expression,
        ) );

        Blade::directive( 'dnsPrefetch', static fn ( string $expression ): string => sprintf(
            '<?php echo \\%s::dnsPrefetch(%s); ?>',
            $resourceHintHelper,
            $expression,
        ) );

        Blade::directive( 'preload', static fn ( string $expression ): string => sprintf(
            '<?php echo \\%s::preload(%s); ?>',
            $resourceHintHelper,
            $expression,
        ) );

        Blade::directive( 'prefetch', static fn ( string $expression ): string => sprintf(
            '<?php echo \\%s::prefetch(%s); ?>',
            $resourceHintHelper,
            $expression,
        ) );

        // Script loading directives. The helper hands the registration to the
        // resolved `ScriptManager` so applications that swap a strategy
        // (e.g. for CSP-aware rendering) see their override picked up by the
        // directive output as well.
        $scriptHelper = ScriptDirectives::class;

        Blade::directive( 'deferScript', static fn ( string $expression ): string => sprintf(
            '<?php echo \\%s::defer(%s); ?>',
            $scriptHelper,
            $expression,
        ) );

        Blade::directive( 'asyncScript', static fn ( string $expression ): string => sprintf(
            '<?php echo \\%s::async(%s); ?>',
            $scriptHelper,
            $expression,
        ) );

        Blade::directive( 'moduleScript', static fn ( string $expression ): string => sprintf(
            '<?php echo \\%s::module(%s); ?>',
            $scriptHelper,
            $expression,
        ) );

        Blade::directive( 'conditionalScript', static fn ( string $expression ): string => sprintf(
            '<?php echo \\%s::conditional(%s); ?>',
            $scriptHelper,
            $expression,
        ) );

        // Speculative loading directive. With no argument the directive emits
        // the configured + manager-supplied rules; with a single argument the
        // expression is forwarded verbatim so callers can pass per-page
        // override arrays inline (e.g. `@speculativeRules(['prerender' => …])`).
        $speculativeHelper = SpeculativeDirectives::class;

        Blade::directive( 'speculativeRules', static function ( string $expression ) use ( $speculativeHelper ): string {
            $argument = trim( $expression );

            if ( '' === $argument ) {
                return sprintf( '<?php echo \\%s::render(); ?>', $speculativeHelper );
            }

            return sprintf(
                '<?php echo \\%s::render(%s); ?>',
                $speculativeHelper,
                $argument,
            );
        } );
    }

    /**
     * Registers critical CSS sources declared in package config.
     *
     * Reads `artisanpack.performance.css.critical.sources` (a map of
     * `route => path-or-content` or `route => [paths-or-contents]`) and feeds
     * each entry into the singleton extractor so application service providers
     * don't have to wire registration themselves for static, config-driven
     * setups.
     *
     * @since 1.0.0
     */
    protected function registerCriticalCssSources(): void
    {
        $sources = (array) config( 'artisanpack.performance.css.critical.sources', [] );

        if ( empty( $sources ) ) {
            return;
        }

        $extractor = $this->app->make( CriticalCssExtractor::class );

        foreach ( $sources as $route => $entries ) {
            foreach ( (array) $entries as $entry ) {
                $extractor->registerSource( (string) $route, (string) $entry );
            }
        }
    }

    /**
     * Merges the package's defaults into the unified `artisanpack.performance` config.
     *
     * Any user overrides published to `config/artisanpack/performance.php`
     * win over the package defaults. List-valued options (e.g. `images.sizes`,
     * `exclude_patterns`, `vary_by`) are replaced wholesale rather than merged
     * per-index so users can shrink them.
     *
     * @since 1.0.0
     */
    protected function mergeConfiguration(): void
    {
        $packageDefaults = config( 'artisanpack-performance-temp', [] );
        $userConfig      = config( 'artisanpack.performance', [] );
        $mergedConfig    = $this->mergeConfigArrays( $packageDefaults, $userConfig );
        config( ['artisanpack.performance' => $mergedConfig] );
    }

    /**
     * Recursively merges two config arrays, replacing list-valued entries wholesale.
     *
     * Behaves like `array_replace_recursive` for associative keys, but for
     * numerically-indexed (list) arrays the user value replaces the default
     * entirely. This prevents per-index bleed-through when a user shortens or
     * reorders a list option.
     *
     * @since 1.0.0
     *
     * @param  array<mixed, mixed>  $defaults  The package defaults.
     * @param  array<mixed, mixed>  $overrides  The user overrides.
     *
     * @return array<mixed, mixed> The merged result.
     */
    protected function mergeConfigArrays( array $defaults, array $overrides ): array
    {
        foreach ( $overrides as $key => $value ) {
            if (
                is_array( $value )
                && isset( $defaults[ $key ] )
                && is_array( $defaults[ $key ] )
                && ! $this->isList( $value )
                && ! $this->isList( $defaults[ $key ] )
            ) {
                $defaults[ $key ] = $this->mergeConfigArrays( $defaults[ $key ], $value );

                continue;
            }

            $defaults[ $key ] = $value;
        }

        return $defaults;
    }

    /**
     * Detects whether the given array is a list (sequential integer keys from 0).
     *
     * Backports `array_is_list()` for PHP 8.0 compatibility. The package
     * requires PHP 8.2+ but this guards against any future runtime where the
     * function is shadowed.
     *
     * @since 1.0.0
     *
     * @param  array<mixed, mixed>  $value  The array to inspect.
     *
     * @return bool True when the array is a list.
     */
    protected function isList( array $value ): bool
    {
        return array_is_list( $value );
    }

    /**
     * Registers default event listeners.
     *
     * Phase 1 reserves this seam without binding any listeners. Subsequent
     * phases bind listeners for image optimization, query analysis, and
     * threshold alerting here.
     *
     * @since 1.0.0
     */
    protected function registerEventListeners(): void
    {
        // Bindings registered by later phases (image, monitoring, alerts).
    }

    /**
     * Publishes the package configuration file.
     *
     * @since 1.0.0
     */
    protected function publishConfiguration(): void
    {
        if ( ! $this->app->runningInConsole() ) {
            return;
        }

        $this->publishes( [
            __DIR__ . '/../config/performance.php' => config_path( 'artisanpack/performance.php' ),
        ], 'artisanpack-performance-config' );

        $this->publishes( [
            __DIR__ . '/../resources/js' => resource_path( 'js/vendor/artisanpack-performance'),
        ], 'artisanpack-performance-js');
    }
}
