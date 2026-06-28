<?php

/**
 * `perf:warm-cache` artisan command.
 *
 * Pre-populates the page cache by hitting a curated list of URLs through
 * the public HTTP stack. URLs can be supplied via `--routes`, `--urls`, a
 * `--sitemap` path, or the package config — multiple sources are merged.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Console\Commands;

use ArtisanPackUI\Performance\Cache\PageCacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Warm cache command class.
 *
 *
 * @since      1.0.0
 */
class WarmCacheCommand extends Command
{
    /**
     * The console command signature.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'perf:warm-cache
		{--type=page : Cache type to warm (currently only "page" is supported)}
		{--routes= : Comma-separated list of route names to warm}
		{--urls= : Comma-separated list of URLs to warm}
		{--sitemap= : Path to a sitemap.xml file whose URLs should be warmed}
		{--concurrent= : Override the configured concurrency level}
		{--delay= : Override the configured inter-request delay in milliseconds}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Warm the page cache by issuing requests to a list of URLs.';

    /**
     * Executes the command.
     *
     * @since 1.0.0
     *
     * @param  PageCacheManager  $manager  Resolved page cache manager.
     */
    public function handle( PageCacheManager $manager ): int
    {
        $type = strtolower( (string) $this->option( 'type' ) );

        if ( 'page' !== $type ) {
            $this->error( __( 'Unsupported --type value: :type. Only "page" is supported.', [ 'type' => $type ] ) );

            return self::FAILURE;
        }

        $urls = $this->resolveUrls();

        if ( empty( $urls ) ) {
            $this->warn( __( 'No URLs to warm. Pass --routes, --urls, --sitemap, or configure cache_warming.routes.' ) );

            return self::SUCCESS;
        }

        $delayMs = $this->resolveDelay();

        $bar       = $this->output->createProgressBar( count( $urls ) );
        $succeeded = 0;
        $failed    = 0;
        $bar->start();

        // Drive the warmer in batch mode so `CacheWarmed` fires once with the
        // full URL list instead of once per URL. The progress callback runs
        // inline after each URL — that's where the bar advances, failures are
        // reported, and the inter-request delay is paced.
        $manager->warmPageCache(
            $urls,
            function ( string $url, array $entry ) use ( $bar, $delayMs, &$succeeded, &$failed ): void {
                if ( true === ( $entry['ok'] ?? false ) ) {
                    $succeeded++;
                } else {
                    $failed++;
                    $this->line( '' );
                    $this->warn( __( 'fail: :url (:reason)', [
                        'url'    => $url,
                        'reason' => $entry['error']
                            ?? __( 'HTTP :status', [ 'status' => (string) ( $entry['status'] ?? '?' ) ] ),
                    ] ) );
                }

                $bar->advance();

                if ( $delayMs > 0 ) {
                    usleep( $delayMs * 1000 );
                }
            },
        );

        $bar->finish();
        $this->line( '' );
        $this->info( __( 'Warmed :succeeded URLs, :failed failed.', [
            'succeeded' => $succeeded,
            'failed'    => $failed,
        ] ) );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolves the inter-request delay in milliseconds.
     *
     * Treats the option as "explicitly supplied" only when the CLI argument
     * was passed at all — `--delay=0` MUST override a non-zero configured
     * delay so operators can disable pacing for a fast local warm. The
     * earlier `(int) $opt ?: config(...)` form treated 0 as falsy and
     * silently fell through to the config value.
     *
     * @since 1.0.0
     */
    protected function resolveDelay(): int
    {
        $option = $this->option( 'delay' );

        if ( null !== $option && '' !== $option && is_numeric( $option ) ) {
            return max( 0, (int) $option );
        }

        return max( 0, (int) config( 'artisanpack.performance.cache_warming.delay_ms', 0 ) );
    }

    /**
     * Resolves the list of URLs to warm from CLI options, sitemap, and config.
     *
     * Order of precedence: explicit CLI options first, then sitemap, then
     * configured routes/URLs. Duplicates are removed while preserving the
     * first-seen ordering so high-priority URLs warm first.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function resolveUrls(): array
    {
        $urls = [];

        foreach ( $this->resolveCliRoutes() as $url ) {
            $urls[] = $url;
        }

        foreach ( $this->resolveCliUrls() as $url ) {
            $urls[] = $url;
        }

        foreach ( $this->resolveSitemap() as $url ) {
            $urls[] = $url;
        }

        foreach ( $this->resolveConfiguredRoutes() as $url ) {
            $urls[] = $url;
        }

        foreach ( $this->resolveConfiguredUrls() as $url ) {
            $urls[] = $url;
        }

        return array_values( array_unique( array_filter( $urls, static fn ( string $url ): bool => '' !== trim( $url ) ) ) );
    }

    /**
     * Resolves URLs from the `--routes` option.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function resolveCliRoutes(): array
    {
        $option = (string) $this->option( 'routes' );

        if ( '' === $option ) {
            return [];
        }

        return $this->routeNamesToUrls( $this->explode( $option ) );
    }

    /**
     * Resolves URLs from the `--urls` option.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function resolveCliUrls(): array
    {
        $option = (string) $this->option( 'urls' );

        if ( '' === $option ) {
            return [];
        }

        return $this->explode( $option );
    }

    /**
     * Resolves URLs from a `--sitemap` path.
     *
     * Expects a standard sitemap.xml document — every `<loc>` element is
     * extracted with a simple regex. Returns an empty list when the file
     * doesn't exist or can't be read.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function resolveSitemap(): array
    {
        $path = (string) $this->option( 'sitemap' );

        if ( '' === $path ) {
            return [];
        }

        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            $this->warn( __( 'Sitemap not readable: :path', [ 'path' => $path ] ) );

            return [];
        }

        $contents = file_get_contents( $path );

        if ( false === $contents ) {
            return [];
        }

        // Accept both `<loc>https://...</loc>` and the CDATA-wrapped form
        // `<loc><![CDATA[https://...]]></loc>`. The inner `[^<]+` rules out
        // accidental match across element boundaries; the CDATA wrapper is
        // stripped post-extraction so the URL passed to Http::get is the
        // bare URL.
        if ( false === preg_match_all( '/<loc[^>]*>(?:<!\[CDATA\[)?([^<\]]+)(?:\]\]>)?<\/loc>/i', $contents, $matches ) ) {
            return [];
        }

        $decoded = array_map(
            // html_entity_decode handles XML entities `&amp;`, `&lt;`, `&gt;`,
            // `&quot;`, `&apos;` per the sitemap.xml spec. Without this, a
            // legitimately-escaped `&amp;` in a query string ends up as a
            // literal `&amp;` in the warming URL.
            static fn ( string $url ): string => html_entity_decode( trim( $url ), ENT_QUOTES | ENT_XML1, 'UTF-8' ),
            $matches[1] ?? [],
        );

        return array_values( array_filter( $decoded, static fn ( string $url ): bool => '' !== $url ) );
    }

    /**
     * Resolves URLs from `cache_warming.routes` config.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function resolveConfiguredRoutes(): array
    {
        $names = (array) config( 'artisanpack.performance.cache_warming.routes', [] );

        return $this->routeNamesToUrls( array_values( array_filter( $names, 'is_string' ) ) );
    }

    /**
     * Resolves URLs from `cache_warming.urls` config.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function resolveConfiguredUrls(): array
    {
        $urls = (array) config( 'artisanpack.performance.cache_warming.urls', [] );

        return array_values( array_filter( $urls, 'is_string' ) );
    }

    /**
     * Converts a list of route names to their generated URLs.
     *
     * Routes that don't resolve (missing parameters, unknown names) are
     * skipped with a warning rather than aborting the run, so an out-of-date
     * config entry doesn't break the rest of the warming pass.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $names  Route names.
     *
     * @return array<int, string>
     */
    protected function routeNamesToUrls( array $names ): array
    {
        $urls = [];

        foreach ( $names as $name ) {
            $name = trim( (string) $name );

            if ( '' === $name ) {
                continue;
            }

            if ( ! Route::has( $name ) ) {
                $this->warn( __( 'Skipping unknown route name: :name', [ 'name' => $name ] ) );

                continue;
            }

            try {
                $urls[] = route( $name );
            } catch ( Throwable $exception ) {
                $this->warn( __( "Skipping route ':name': :reason", [
                    'name'   => $name,
                    'reason' => $exception->getMessage(),
                ] ) );
            }
        }

        return $urls;
    }

    /**
     * Splits a comma-separated option value into a trimmed list.
     *
     * @since 1.0.0
     *
     * @param  string  $value  Comma-separated option value.
     *
     * @return array<int, string>
     */
    protected function explode( string $value ): array
    {
        $parts = array_map( 'trim', explode( ',', $value ) );

        return array_values( array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );
    }
}
