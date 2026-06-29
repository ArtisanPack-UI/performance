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

        $path = $this->resolveMigrationPath();
        $body = $suggester->generateMigrationBody( $suggestions );
        $file = $this->writeMigration( $path, $body );

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
     * when `--path` is omitted. The directory is created if it doesn't
     * exist so the command works on a fresh install without manual setup.
     *
     * @since 1.0.0
     */
    protected function resolveMigrationPath(): string
    {
        $override = (string) $this->option( 'path' );

        if ( '' !== $override ) {
            return rtrim( $override, DIRECTORY_SEPARATOR );
        }

        return rtrim( base_path( 'database/migrations' ), DIRECTORY_SEPARATOR );
    }

    /**
     * Writes the migration file to disk and returns its path.
     *
     * @since 1.0.0
     *
     * @param  string  $directory  Migration directory.
     * @param  string  $body  Migration body produced by IndexSuggester.
     */
    protected function writeMigration( string $directory, string $body ): string
    {
        if ( ! is_dir( $directory ) ) {
            mkdir( $directory, 0755, true );
        }

        $timestamp = date( 'Y_m_d_His' );
        $filename  = $timestamp . '_perf_suggested_indexes.php';
        $path      = $directory . DIRECTORY_SEPARATOR . $filename;

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

        file_put_contents( $path, $contents );

        return $path;
    }
}
