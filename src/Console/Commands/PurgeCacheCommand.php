<?php

/**
 * `perf:purge-cache` artisan command.
 *
 * Manually invalidates page or fragment caches written by the Performance
 * package. Accepts a `--type` (page, fragment, all) and either a `--pattern`
 * (page cache wildcards) or `--tag` (fragment cache tag).
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Console\Commands;

use ArtisanPackUI\Performance\Cache\CacheInvalidator;
use Illuminate\Console\Command;

/**
 * Purge cache command class.
 *
 *
 * @since      1.0.0
 */
class PurgeCacheCommand extends Command
{
    /**
     * The console command signature.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'perf:purge-cache
		{--type=all : Cache type to purge (page, fragment, all)}
		{--pattern= : Page cache wildcard pattern (only with --type=page)}
		{--tag= : Fragment cache tag (only with --type=fragment)}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Purge page or fragment caches written by the Performance package.';

    /**
     * Executes the command.
     *
     * @since 1.0.0
     *
     * @param  CacheInvalidator  $invalidator  Resolved invalidator instance.
     */
    public function handle( CacheInvalidator $invalidator ): int
    {
        $type    = strtolower( (string) $this->option( 'type' ) );
        $pattern = (string) $this->option( 'pattern' );
        $tag     = (string) $this->option( 'tag' );

        return match ( $type ) {
            'page'     => $this->purgePage( $invalidator, $pattern ),
            'fragment' => $this->purgeFragment( $invalidator, $tag ),
            'all'      => $this->purgeAll( $invalidator ),
            default    => $this->invalidType( $type ),
        };
    }

    /**
     * Handles `--type=page` invocations.
     *
     * @since 1.0.0
     *
     * @param  CacheInvalidator  $invalidator  Resolved invalidator.
     * @param  string  $pattern  Page cache pattern.
     */
    protected function purgePage( CacheInvalidator $invalidator, string $pattern ): int
    {
        if ( '' === $pattern ) {
            $count = $invalidator->flushPageCache();
            $this->info( __( 'Purged :count page cache entries (flush).', [ 'count' => $count ] ) );

            return self::SUCCESS;
        }

        $count = $invalidator->invalidatePagePattern( $pattern );
        $this->info( __( "Purged :count page cache entries matching ':pattern'.", [
            'count'   => $count,
            'pattern' => $pattern,
        ] ) );

        return self::SUCCESS;
    }

    /**
     * Handles `--type=fragment` invocations.
     *
     * @since 1.0.0
     *
     * @param  CacheInvalidator  $invalidator  Resolved invalidator.
     * @param  string  $tag  Fragment cache tag.
     */
    protected function purgeFragment( CacheInvalidator $invalidator, string $tag ): int
    {
        if ( '' === $tag ) {
            $this->error( __( 'A --tag value is required when purging fragment cache.' ) );

            return self::FAILURE;
        }

        $count = $invalidator->invalidateFragmentTag( $tag );
        $this->info( __( "Purged :count fragment cache entries tagged ':tag'.", [
            'count' => $count,
            'tag'   => $tag,
        ] ) );

        return self::SUCCESS;
    }

    /**
     * Handles `--type=all` invocations.
     *
     * @since 1.0.0
     *
     * @param  CacheInvalidator  $invalidator  Resolved invalidator.
     */
    protected function purgeAll( CacheInvalidator $invalidator ): int
    {
        $result = $invalidator->purgeAll();

        $this->info( __( 'Purged :page page entries and :fragments fragment entries.', [
            'page'      => $result['page'],
            'fragments' => $result['fragments'],
        ] ) );

        return self::SUCCESS;
    }

    /**
     * Reports an unsupported `--type` value and exits with failure.
     *
     * @since 1.0.0
     *
     * @param  string  $type  Caller-supplied type value.
     */
    protected function invalidType( string $type ): int
    {
        $this->error( __( 'Unknown --type value: :type. Use page, fragment, or all.', [ 'type' => $type ] ) );

        return self::FAILURE;
    }
}
