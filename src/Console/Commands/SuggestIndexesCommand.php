<?php

/**
 * `perf:suggest-indexes` artisan command.
 *
 * Reads the captured slow query log and prints ranked composite-index
 * suggestions. With `--migration` the command scaffolds a migration
 * file containing the suggested `$table->index()` calls so developers
 * can apply the changes in a single PR.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Console\Commands;

use ArtisanPackUI\Performance\Database\IndexSuggester;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Suggest indexes command class.
 *
 *
 * @since      1.0.0
 */
class SuggestIndexesCommand extends Command
{
    /**
     * The console command signature.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'perf:suggest-indexes
		{--migration : Generate a migration file containing the suggested indexes}
		{--path= : Override the migration directory (defaults to database/migrations)}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Analyze captured slow queries and suggest composite indexes.';

    /**
     * Executes the command.
     *
     * @since 1.0.0
     *
     * @param  IndexSuggester  $suggester  Resolved suggester instance.
     */
    public function handle( IndexSuggester $suggester ): int
    {
        $this->info( __( 'Analyzing slow queries...' ) );

        $suggestions = $suggester->suggest();

        if ( [] === $suggestions ) {
            $this->warn( __( 'No index suggestions could be derived from the captured slow queries.' ) );

            return self::SUCCESS;
        }

        $this->renderTable( $suggestions );

        if ( ! (bool) $this->option( 'migration' ) ) {
            return self::SUCCESS;
        }

        try {
            $path = $this->resolveMigrationPath();
            $body = $suggester->generateMigrationBody( $suggestions );
            $file = $this->writeMigration( $path, $body );
        } catch ( RuntimeException $exception ) {
            // Surface the validation/collision message as a clean error
            // line instead of an uncaught exception with a stack trace —
            // these failures (bad --path, duplicate filename) are
            // operator errors and the user-facing message is enough.
            $this->error( $exception->getMessage() );

            return self::FAILURE;
        }

        $this->info( __( 'Migration written to :path', [ 'path' => $file ] ) );

        return self::SUCCESS;
    }

    /**
     * Renders the suggestion table to the console.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{table: string, columns: array<int, string>, sources: array<int, string>, occurrences: int, impact: string}>  $suggestions  Ranked suggestions.
     */
    protected function renderTable( array $suggestions ): void
    {
        $rows = array_map( static function ( array $suggestion ): array {
            return [
                $suggestion['table'],
                'INDEX (' . implode( ', ', $suggestion['columns'] ) . ')',
                $suggestion['impact'],
                $suggestion['occurrences'],
            ];
        }, $suggestions );

        $this->table(
            [ __( 'Table' ), __( 'Suggested Index' ), __( 'Potential Impact' ), __( 'Occurrences' ) ],
            $rows,
        );
    }

    /**
     * Resolves the migration directory.
     *
     * Falls back to `database/migrations` under the application base path
     * when `--path` is omitted. When `--path` is supplied it is resolved
     * against the application base path and rejected if the canonical
     * resolution escapes that base path — without this guard a malicious
     * or careless invocation could drop a `return new class extends Migration`
     * PHP file into `vendor/`, `public/`, or any writable location.
     *
     * @since 1.0.0
     *
     * @throws RuntimeException When `--path` resolves outside the application base path.
     */
    protected function resolveMigrationPath(): string
    {
        $override = trim( (string) $this->option( 'path' ) );

        if ( '' === $override ) {
            return rtrim( base_path( 'database/migrations' ), DIRECTORY_SEPARATOR );
        }

        $base = rtrim( (string) realpath( base_path() ), DIRECTORY_SEPARATOR );

        if ( '' === $base ) {
            throw new RuntimeException( __( 'Unable to resolve the application base path.' ) );
        }

        // Anchor relative paths to the base path so `--path=database/...`
        // works without forcing callers to pass absolute paths. Absolute
        // paths still resolve via the join + realpath pass below.
        $candidate = $this->isAbsolutePath( $override )
            ? $override
            : $base . DIRECTORY_SEPARATOR . $override;

        // realpath on a not-yet-existing leaf returns false, so resolve
        // the deepest existing ancestor and append the remaining segments
        // — this lets us validate "would be under base path" before mkdir.
        $resolved = $this->resolvePotentialPath( $candidate );

        if ( ! str_starts_with( $resolved . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR ) ) {
            throw new RuntimeException( __(
                'Refusing to write migrations outside the application base path: :path',
                [ 'path' => $resolved ],
            ) );
        }

        return rtrim( $resolved, DIRECTORY_SEPARATOR );
    }

    /**
     * Reports whether the supplied path is absolute on the current platform.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Raw input path.
     */
    protected function isAbsolutePath( string $path ): bool
    {
        if ( '' === $path ) {
            return false;
        }

        if ( DIRECTORY_SEPARATOR === $path[0] ) {
            return true;
        }

        // Windows drive-letter paths (C:\foo). Cheap test; the realpath
        // call later does the heavy validation.
        return 1 === preg_match( '/^[A-Za-z]:[\\\\\/]/', $path );
    }

    /**
     * Resolves a path that may not yet exist by walking up to the deepest
     * existing ancestor and re-attaching the unresolved tail.
     *
     * Plain `realpath()` returns false the moment any path segment is
     * missing, which would block the legitimate "create database/migrations
     * if it doesn't exist" case. This helper anchors the realpath at the
     * deepest existing ancestor so we can still validate the canonical
     * destination is under the base path.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Candidate path (may not exist yet).
     */
    protected function resolvePotentialPath( string $path ): string
    {
        $normalized = rtrim( $path, DIRECTORY_SEPARATOR );
        $tail       = [];

        while ( '' !== $normalized && false === realpath( $normalized ) ) {
            $tail[]     = basename( $normalized );
            $parent     = dirname( $normalized );

            if ( $parent === $normalized ) {
                break;
            }

            $normalized = $parent;
        }

        $resolvedAncestor = '' === $normalized ? '' : (string) realpath( $normalized );

        if ( [] === $tail ) {
            return $resolvedAncestor;
        }

        return $resolvedAncestor . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, array_reverse( $tail ) );
    }

    /**
     * Writes the migration file to disk and returns its path.
     *
     * The filename uses a microsecond-resolution timestamp suffix so
     * two invocations in the same wall-clock second produce distinct
     * files instead of silently overwriting one another. The directory
     * is created with `Filesystem::ensureDirectoryExists()` for a more
     * descriptive failure if creation is denied.
     *
     * @since 1.0.0
     *
     * @param  string  $directory  Migration directory.
     * @param  string  $body  Migration body produced by IndexSuggester.
     *
     * @throws RuntimeException When the target file already exists or write fails.
     */
    protected function writeMigration( string $directory, string $body ): string
    {
        $filesystem = new Filesystem;
        $filesystem->ensureDirectoryExists( $directory );

        // `Y_m_d_His` matches Laravel's migration filename convention.
        // Append a microsecond suffix so back-to-back runs in the same
        // second don't collide. `microtime(true)` is monotonic enough
        // for filename disambiguation at command-line speed.
        [ $micro, $secs ] = explode( ' ', microtime() );
        $timestamp        = date( 'Y_m_d_His', (int) $secs ) . '_' . substr( $micro, 2, 6 );
        $filename         = $timestamp . '_perf_suggested_indexes.php';
        $path             = $directory . DIRECTORY_SEPARATOR . $filename;

        if ( $filesystem->exists( $path ) ) {
            throw new RuntimeException( __(
                'Refusing to overwrite existing migration: :path',
                [ 'path' => $path ],
            ) );
        }

        $contents = <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
        {$body}
            }

            public function down(): void
            {
                // Drop indexes manually if you need to roll back; the suggester
                // does not generate `dropIndex()` calls because the index names
                // depend on the column list and existing schema state.
            }
        };

        PHP;

        $filesystem->put( $path, $contents );

        return $path;
    }
}
