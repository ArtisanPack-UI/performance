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

use ArtisanPackUI\Performance\Ai\Agents\OptimizationSuggestionAgent;
use ArtisanPackUI\Performance\Ai\Agents\PerformanceInsightAgent;
use ArtisanPackUI\Performance\Cache\CacheInvalidator;
use ArtisanPackUI\Performance\Cache\CacheStatistics;
use ArtisanPackUI\Performance\Cache\CacheStrategyManager;
use ArtisanPackUI\Performance\Cache\FragmentCache;
use ArtisanPackUI\Performance\Cache\PageCacheManager;
use ArtisanPackUI\Performance\Console\Commands\AggregateMetricsCommand;
use ArtisanPackUI\Performance\Console\Commands\GenerateCriticalCssCommand;
use ArtisanPackUI\Performance\Console\Commands\GenerateWebPCommand;
use ArtisanPackUI\Performance\Console\Commands\InstallCommand;
use ArtisanPackUI\Performance\Console\Commands\PurgeCacheCommand;
use ArtisanPackUI\Performance\Console\Commands\SuggestIndexesCommand;
use ArtisanPackUI\Performance\Console\Commands\WarmCacheCommand;
use ArtisanPackUI\Performance\Css\CriticalCssExtractor;
use ArtisanPackUI\Performance\Database\IndexSuggester;
use ArtisanPackUI\Performance\Database\N1Detector;
use ArtisanPackUI\Performance\Database\QueryAnalyzer;
use ArtisanPackUI\Performance\Database\SlowQueryLogger;
use ArtisanPackUI\Performance\Http\Middleware\EarlyHints;
use ArtisanPackUI\Performance\Http\Middleware\MinifyHtml;
use ArtisanPackUI\Performance\Images\DominantColorExtractor;
use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use ArtisanPackUI\Performance\JavaScript\ScriptManager;
use ArtisanPackUI\Performance\Listeners\OptimizeUploadedMedia;
use ArtisanPackUI\Performance\Livewire\Ai\OptimizationSuggestionPanel as OptimizationSuggestionPanelComponent;
use ArtisanPackUI\Performance\Livewire\Ai\QueryInsightPanel as QueryInsightPanelComponent;
use ArtisanPackUI\Performance\Livewire\CacheManager as CacheManagerComponent;
use ArtisanPackUI\Performance\Livewire\MetricsChart as MetricsChartComponent;
use ArtisanPackUI\Performance\Livewire\PerformanceDashboard as PerformanceDashboardComponent;
use ArtisanPackUI\Performance\Livewire\QueryAnalyzer as QueryAnalyzerComponent;
use ArtisanPackUI\Performance\Livewire\RecommendationsPanel as RecommendationsPanelComponent;
use ArtisanPackUI\Performance\Monitoring\MetricsAggregator;
use ArtisanPackUI\Performance\Monitoring\RecommendationEngine;
use ArtisanPackUI\Performance\Output\HtmlMinifier;
use ArtisanPackUI\Performance\Output\OutputBuffer;
use ArtisanPackUI\Performance\Output\ResourceHintInjector;
use ArtisanPackUI\Performance\Services\EmbedOptimizer;
use ArtisanPackUI\Performance\Services\Image\FormatConverter;
use ArtisanPackUI\Performance\Services\ImageService;
use ArtisanPackUI\Performance\Services\MediaLibraryDetector;
use ArtisanPackUI\Performance\Services\PerformanceService;
use ArtisanPackUI\Performance\Speculative\PrefetchManager;
use ArtisanPackUI\Performance\Speculative\PrerenderManager;
use ArtisanPackUI\Performance\Speculative\SpeculativeRulesGenerator;
use ArtisanPackUI\Performance\Support\MetricsChartDirectives;
use ArtisanPackUI\Performance\Support\MonitorDirectives;
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
use Illuminate\Support\Facades\Route;
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

        $this->app->singleton( PageCacheManager::class, function () {
            return new PageCacheManager;
        } );

        $this->app->singleton( FragmentCache::class, function () {
            return new FragmentCache;
        } );

        $this->app->singleton( CacheInvalidator::class, function ( $app ) {
            return new CacheInvalidator(
                $app->make( PageCacheManager::class ),
                $app->make( FragmentCache::class ),
            );
        } );

        $this->app->singleton( CacheStrategyManager::class, function () {
            return new CacheStrategyManager;
        } );

        $this->app->singleton( CacheStatistics::class, function () {
            return new CacheStatistics;
        } );

        $this->app->singleton( MetricsAggregator::class, function () {
            return new MetricsAggregator;
        } );

        $this->app->singleton( RecommendationEngine::class, function () {
            return new RecommendationEngine;
        } );

        $this->app->singleton( MediaLibraryDetector::class, function () {
            return new MediaLibraryDetector;
        } );

        $this->app->singleton( QueryAnalyzer::class, function () {
            return new QueryAnalyzer;
        } );

        $this->app->singleton( N1Detector::class, function ( $app ) {
            return new N1Detector( $app->make( QueryAnalyzer::class ) );
        } );

        $this->app->singleton( SlowQueryLogger::class, function ( $app ) {
            return new SlowQueryLogger( $app->make( QueryAnalyzer::class ) );
        } );

        $this->app->singleton( IndexSuggester::class, function () {
            return new IndexSuggester;
        } );

        $this->app->singleton( HtmlMinifier::class, function () {
            return new HtmlMinifier;
        } );

        $this->app->singleton( OutputBuffer::class, function () {
            return new OutputBuffer;
        } );

        $this->app->singleton( MinifyHtml::class, function ( $app ) {
            return new MinifyHtml( $app->make( HtmlMinifier::class ) );
        } );

        $this->app->singleton( EarlyHints::class, function ( $app ) {
            return new EarlyHints( $app->make( ResourceHintInjector::class ) );
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
                $app->make( PageCacheManager::class ),
                $app->make( FragmentCache::class ),
                $app->make( CacheInvalidator::class ),
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
        $this->registerLivewireComponents();
        $this->registerMiddlewareAliases();
        $this->registerCriticalCssSources();
        $this->registerEventListeners();
        $this->registerMediaLibraryIntegration();
        $this->registerOctaneResetHooks();
        $this->registerCommands();
        $this->registerAiGate();
        $this->registerRoutes();
        $this->publishConfiguration();
    }

    /**
     * Declare AI features owned by this package.
     *
     * Auto-discovered by artisanpack-ui/ai when the ai package is installed.
     * Each entry maps a fully-qualified feature key to the agent class that
     * fulfills it, along with a human-readable label and description for the
     * admin UI.
     *
     * @since 1.1.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function aiFeatures(): array
    {
        return [
            'performance.query_insight'          => [
                'agent'       => PerformanceInsightAgent::class,
                'package'     => 'artisanpack-ui/performance',
                'label'       => __( 'Query insight' ),
                'description' => __( 'Explain why a slow query is slow and suggest indexes or rewrites.' ),
            ],
            'performance.optimization_suggestion' => [
                'agent'       => OptimizationSuggestionAgent::class,
                'package'     => 'artisanpack-ui/performance',
                'label'       => __( 'Optimization suggestions' ),
                'description' => __( 'Look at aggregate performance metrics and recommend where to focus optimization work.' ),
            ],
        ];
    }

    /**
     * Registers listeners for the artisanpack-ui/media-library package.
     *
     * When the detector reports the integration should run we hook the
     * media library's upload lifecycle so freshly-uploaded images run
     * through the ImageService (optimization + modern format
     * conversion). Absent the media-library package the method is a
     * no-op — every call site is guarded on class existence so the
     * package continues to load without media-library installed.
     *
     * The integration status is logged once at boot (info-level to the
     * `performance` channel when configured, otherwise the default
     * channel) so operators can confirm the wiring landed as expected.
     *
     * @since 1.0.0
     */
    protected function registerMediaLibraryIntegration(): void
    {
        $detector = $this->app->make( MediaLibraryDetector::class );
        $status   = $detector->status();
        $logger   = logger();

        if ( ! $status['installed'] ) {
            $logger->debug( 'artisanpack-ui/performance: media-library not detected — integration skipped.' );

            return;
        }

        if ( ! $status['enabled'] ) {
            $logger->info( 'artisanpack-ui/performance: media-library detected but integration disabled via config.' );

            return;
        }

        $logger->info( 'artisanpack-ui/performance: media-library integration enabled.', [
            'source'                     => $status['source'],
            'optimize_on_upload'         => $detector->shouldOptimizeOnUpload(),
            'generate_formats_on_upload' => $detector->shouldGenerateFormatsOnUpload(),
        ] );

        $this->wireMediaLibraryListeners();
    }

    /**
     * Wires the `OptimizeUploadedMedia` listener into the media-library upload path.
     *
     * Chooses a single dispatch source to prevent duplicate job runs when
     * both hooks are available:
     *
     *   1. If media-library publishes a dedicated `MediaUploaded` event,
     *      subscribe to it — the intent-carrying event is preferred over
     *      the generic Eloquent model event.
     *   2. Otherwise, fall back to the `Media::created` Eloquent event.
     *
     * The fallback closure captures `$app` (not `$this`) as a `static`
     * closure so the ServiceProvider instance isn't kept alive by the
     * model's global event registry under Octane / long-running workers.
     *
     * @since 1.0.0
     */
    protected function wireMediaLibraryListeners(): void
    {
        $uploadedEvent = '\ArtisanPackUI\MediaLibrary\Events\MediaUploaded';

        if ( class_exists( $uploadedEvent ) ) {
            $events = $this->app->make( 'events' );
            $events->listen( $uploadedEvent, [ OptimizeUploadedMedia::class, 'handle' ] );

            return;
        }

        $mediaModel = '\ArtisanPackUI\MediaLibrary\Models\Media';

        if ( class_exists( $mediaModel ) && method_exists( $mediaModel, 'created' ) ) {
            $app = $this->app;

            $mediaModel::created( static function ( $media ) use ( $app ): void {
                $app->make( OptimizeUploadedMedia::class )->handle( $media );
            } );
        }
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
            $sandbox->make( QueryAnalyzer::class )->reset();
            $sandbox->make( N1Detector::class )->reset();
            $sandbox->make( OutputBuffer::class )->reset();
        } );
    }

    /**
     * Registers route-middleware aliases for the package's HTTP middlewares.
     *
     * `perf.minify` and `perf.early-hints` give application route
     * groups a stable, namespaced shorthand that survives between
     * package releases — important because the FQCNs (which include
     * `Http\Middleware`) would otherwise leak into the application's
     * routing tables and require coordinated updates if the class
     * ever moved.
     *
     * @since 1.0.0
     */
    protected function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'] ?? null;

        if ( null === $router || ! method_exists( $router, 'aliasMiddleware' ) ) {
            return;
        }

        $router->aliasMiddleware( 'perf.minify', MinifyHtml::class );
        $router->aliasMiddleware( 'perf.early-hints', EarlyHints::class );
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
                InstallCommand::class,
                GenerateWebPCommand::class,
                GenerateCriticalCssCommand::class,
                WarmCacheCommand::class,
                PurgeCacheCommand::class,
                SuggestIndexesCommand::class,
                AggregateMetricsCommand::class,
            ] );
        }
    }

    /**
     * Registers the package's Livewire components.
     *
     * The components are registered under the `perf-` prefix so they don't
     * clash with application Livewire components named `dashboard` or
     * `cache-manager`. Registration is a no-op when Livewire is not
     * installed — applications using the package without the dashboard
     * still load the rest of the surface normally.
     *
     * @since 1.0.0
     */
    protected function registerLivewireComponents(): void
    {
        if ( ! class_exists( '\Livewire\Livewire' ) ) {
            return;
        }

        \Livewire\Livewire::component( 'perf-performance-dashboard', PerformanceDashboardComponent::class );
        \Livewire\Livewire::component( 'perf-metrics-chart', MetricsChartComponent::class );
        \Livewire\Livewire::component( 'perf-cache-manager', CacheManagerComponent::class );
        \Livewire\Livewire::component( 'perf-query-analyzer', QueryAnalyzerComponent::class );
        \Livewire\Livewire::component( 'perf-recommendations-panel', RecommendationsPanelComponent::class );
        \Livewire\Livewire::component( 'perf-ai-query-insight-panel', QueryInsightPanelComponent::class );
        \Livewire\Livewire::component( 'perf-ai-optimization-suggestion-panel', OptimizationSuggestionPanelComponent::class );
    }

    /**
     * Registers the package's HTTP routes.
     *
     * The API surface is gated by `routes.enabled` so applications that
     * only consume the optimization helpers can disable the dashboard
     * endpoints entirely. The configured `api_prefix` is normalized to a
     * single leading segment so callers can supply either `api/perf` or
     * `/api/perf` without producing a double-slashed URI.
     *
     * @since 1.0.0
     */
    protected function registerRoutes(): void
    {
        if ( false === (bool) config( 'artisanpack.performance.routes.enabled', true ) ) {
            return;
        }

        $prefix     = trim( (string) config( 'artisanpack.performance.routes.api_prefix', 'api/performance' ), '/' );
        $middleware = (array) config( 'artisanpack.performance.routes.api_middleware', [ 'api' ] );
        $throttle   = (string) config( 'artisanpack.performance.routes.api_throttle', '60,1' );

        $middleware[] = 'throttle:' . $throttle;

        // Admin route group needs session-capable middleware because
        // `RecommendationsAdminApiController` persists dismissals via
        // `Session::put/get`. The default `['api']` middleware group in
        // Laravel 11/12 does not include `StartSession`, so we prepend
        // it (and its dependency `EncryptCookies` + response cookie
        // writer) here — hosts can override with a custom stack in
        // `artisanpack.performance.routes.admin_middleware`.
        $adminMiddleware = (array) config(
            'artisanpack.performance.routes.admin_middleware',
            [
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            ],
        );

        $adminMiddleware[] = 'throttle:' . $throttle;

        // AI route group rides a separate middleware stack. These
        // endpoints dispatch to paid LLM providers so the shipped
        // default gates them behind Sanctum plus a `performance.ai.use`
        // Gate — sharing the stateless public middleware used by the
        // metrics ingest would let anonymous callers drain provider
        // credit. Hosts running a different guard can override the
        // stack; the Gate check inside the controller still fires.
        $aiMiddleware = (array) config(
            'artisanpack.performance.routes.ai_middleware',
            [ 'api', 'auth:sanctum' ],
        );

        $aiMiddleware[] = 'throttle:' . $throttle;

        Route::middleware( $adminMiddleware )
            ->prefix( $prefix )
            ->name( 'artisanpack.performance.api.admin.' )
            ->group( __DIR__ . '/../routes/api-admin.php' );

        Route::middleware( $aiMiddleware )
            ->prefix( $prefix )
            ->name( 'artisanpack.performance.api.' )
            ->group( __DIR__ . '/../routes/api-ai.php' );

        Route::middleware( $middleware )
            ->prefix( $prefix )
            ->name( 'artisanpack.performance.api.' )
            ->group( __DIR__ . '/../routes/api.php' );
    }

    /**
     * Register the default `performance.ai.use` authorization gate.
     *
     * The AI API endpoints (`/api/performance/ai/*`) gate on this
     * ability so that paid quota isn't spent by every authenticated
     * user. Ships with a permissive default (any authenticated user can
     * use AI features) so upgrades are non-breaking; installers should
     * override this gate in their own `AuthServiceProvider` to enforce
     * a stricter policy — limit to admins, apply per-tenant quotas, etc.
     *
     * @since 1.1.0
     */
    protected function registerAiGate(): void
    {
        $gate = \Illuminate\Support\Facades\Gate::getFacadeRoot();

        // Guard so an installer who registered their own `performance.ai.use`
        // before our boot() (via a plugin, package-first AuthServiceProvider,
        // etc.) is not silently overwritten by the permissive default.
        if ( method_exists( $gate, 'has' ) && $gate->has( 'performance.ai.use' ) ) {
            return;
        }

        \Illuminate\Support\Facades\Gate::define(
            'performance.ai.use',
            static function ( $user = null ): bool {
                return null !== $user;
            },
        );
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

        // Fragment cache directive. `@cache(key, ttl, tags)` captures the
        // enclosed Blade output via `ob_start()` and round-trips it through
        // the FragmentCache so the rendered partial is reused on subsequent
        // requests. The TTL and tag list are optional — falling through to
        // the configured `default_ttl` and an empty tag list respectively.
        //
        // State is pushed onto a per-render stack so nested
        // `@cache ... @cache ... @endcache @endcache` blocks don't clobber
        // each other's scope. Without the stack the shared variables would
        // be either fatally `unset()` (inner-MISS case) or silently
        // overwritten (inner-HIT case), poisoning the outer write.
        Blade::directive( 'cache', static function ( string $expression ): string {
            $argument = trim( $expression );

            if ( '' === $argument ) {
                $argument = "''";
            }

            // Blade can't skip statements between `@cache` and `@endcache` —
            // the directive output is just inlined PHP, not a control
            // structure. So even on a HIT, the body code still runs. We
            // suppress its rendered output by starting an output buffer at
            // `@cache` in BOTH branches: on HIT the cached content is
            // echoed first and the buffered body is discarded at
            // `@endcache`; on MISS the buffered body is the value we
            // cache + echo. State is pushed onto a per-render stack so
            // nested directives don't clobber each other's frames.
            return sprintf(
                '<?php $__perfFragmentStack = $__perfFragmentStack ?? []; '
                . '$__perfFragmentArgs = [%s]; '
                . '$__perfFragmentFrame = [ '
                . '    "cache" => app(\\%s::class), '
                . '    "key" => (string) ($__perfFragmentArgs[0] ?? \'\'), '
                . '    "ttl" => (int) ($__perfFragmentArgs[1] ?? 0), '
                . '    "tags" => (array) ($__perfFragmentArgs[2] ?? []), '
                . ']; '
                . '$__perfFragmentHit = $__perfFragmentFrame["cache"]->get($__perfFragmentFrame["key"]); '
                . 'if (is_string($__perfFragmentHit)) { '
                . '    echo $__perfFragmentHit; '
                . '    $__perfFragmentFrame["hit"] = true; '
                . '} else { '
                . '    $__perfFragmentFrame["hit"] = false; '
                . '} '
                . 'ob_start(); '
                . '$__perfFragmentStack[] = $__perfFragmentFrame; '
                . 'unset($__perfFragmentArgs, $__perfFragmentFrame, $__perfFragmentHit); ?>',
                $argument,
                FragmentCache::class,
            );
        } );

        // Performance monitor directive. With no argument the directive
        // emits the default web-vitals bootstrap; with a single
        // argument the expression is forwarded verbatim so callers can
        // pass per-page configuration overrides inline (e.g.
        // `@perfMonitor(['extra' => ['tenant' => $tenant->id]])`).
        $monitorHelper = MonitorDirectives::class;

        Blade::directive( 'perfMonitor', static function ( string $expression ) use ( $monitorHelper ): string {
            $argument = trim( $expression );

            if ( '' === $argument ) {
                return sprintf( '<?php echo \\%s::perfMonitor(); ?>', $monitorHelper );
            }

            return sprintf(
                '<?php echo \\%s::perfMonitor(%s); ?>',
                $monitorHelper,
                $argument,
            );
        } );

        // Metrics chart assets directive. Emits the Chart.js loader plus
        // the package's small bootstrap module that binds Chart.js to the
        // `[data-metrics-chart]` containers rendered by the MetricsChart
        // Livewire component. Optional argument forwards overrides
        // (e.g. `@perfMetricsChartAssets(['libraryUrl' => ''])` when the
        // host page already loads Chart.js).
        $metricsChartHelper = MetricsChartDirectives::class;

        Blade::directive( 'perfMetricsChartAssets', static function ( string $expression ) use ( $metricsChartHelper ): string {
            $argument = trim( $expression );

            if ( '' === $argument ) {
                return sprintf( '<?php echo \\%s::perfMetricsChartAssets(); ?>', $metricsChartHelper );
            }

            return sprintf(
                '<?php echo \\%s::perfMetricsChartAssets(%s); ?>',
                $metricsChartHelper,
                $argument,
            );
        } );

        Blade::directive( 'endcache', static function (): string {
            return '<?php $__perfFragmentFrame = array_pop($__perfFragmentStack); '
                . 'if (is_array($__perfFragmentFrame)) { '
                . '    $__perfFragmentOutput = (string) ob_get_clean(); '
                . '    if (false === $__perfFragmentFrame["hit"]) { '
                . '        $__perfFragmentFrame["cache"]->put('
                . '            $__perfFragmentFrame["key"], '
                . '            $__perfFragmentOutput, '
                . '            $__perfFragmentFrame["ttl"], '
                . '            $__perfFragmentFrame["tags"]'
                . '        ); '
                . '        echo $__perfFragmentOutput; '
                . '    } '
                . '    unset($__perfFragmentOutput); '
                . '} '
                . 'unset($__perfFragmentFrame); ?>';
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
        $this->bootDatabaseObservers();
    }

    /**
     * Subscribes the QueryAnalyzer and N1Detector to DB events.
     *
     * Reads the relevant feature flags so apps that haven't opted into
     * `query_optimization` pay nothing — no listener bound, no events
     * fired. The detector also re-reads its own enabled flag inside
     * `enable()`, so toggling at runtime still works.
     *
     * @since 1.0.0
     */
    protected function bootDatabaseObservers(): void
    {
        if ( $this->app->runningUnitTests() ) {
            // Tests opt into the observers explicitly so the listener
            // chain is exactly the surface the test under test cares
            // about — otherwise every test would inherit a listener
            // that fires unrelated events.
            return;
        }

        if ( (bool) config( 'artisanpack.performance.features.query_optimization', false ) ) {
            $this->app->make( QueryAnalyzer::class )->enableQueryLogging();
        }

        if ( (bool) config( 'artisanpack.performance.database.n1_detection.enabled', false ) ) {
            $this->app->make( N1Detector::class )->enable();
        }

        if ( (bool) config( 'artisanpack.performance.database.slow_query_logging.enabled', false ) ) {
            $this->app->make( SlowQueryLogger::class )->enable();
        }
    }

    /**
     * Publishes the package configuration file and view assets.
     *
     * Registers three tags:
     * - `artisanpack-performance-config` publishes the package config.
     * - `artisanpack-performance-js` publishes the client-side bundle.
     * - `performance-views` publishes every Blade template so
     *   applications can fork any component template without forking
     *   the component class.
     * - `performance-css` publishes the bundled base stylesheet with
     *   the CSS custom properties themes can override.
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
            __DIR__ . '/../resources/js' => resource_path( 'js/vendor/artisanpack-performance' ),
        ], 'artisanpack-performance-js' );

        $this->publishes( [
            __DIR__ . '/../resources/views' => resource_path( 'views/vendor/artisanpack-ui/performance' ),
        ], 'performance-views' );

        $cssFile = __DIR__ . '/../resources/css/performance.css';

        if ( is_file( $cssFile ) ) {
            $this->publishes( [
                $cssFile => resource_path( 'css/vendor/artisanpack-ui/performance.css' ),
            ], 'performance-css' );
        }
    }
}
