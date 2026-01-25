# Create install command

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::10" ~"Area::Backend"

## Problem Statement

Package needs an easy installation command for developers.

## Proposed Solution

Create an Artisan command that handles all installation steps.

## Acceptance Criteria

### Command Features
- [ ] `php artisan performance:install` command
- [ ] Interactive prompts for options
- [ ] Non-interactive mode with `--no-interaction`
- [ ] Progress output during installation

### Installation Steps
- [ ] Publish configuration
- [ ] Publish views (optional)
- [ ] Run migrations
- [ ] Create cache directories
- [ ] Install npm dependencies (optional)
- [ ] Build assets (optional)
- [ ] Display next steps

### Command Options
- [ ] `--config` - Publish config only
- [ ] `--views` - Publish views
- [ ] `--migrations` - Run migrations
- [ ] `--no-interaction` - Skip prompts
- [ ] `--force` - Overwrite existing files

### Validation
- [ ] Check PHP version
- [ ] Check Laravel version
- [ ] Check required dependencies
- [ ] Check write permissions

## Use Cases

1. Quick package setup
2. Consistent installation across projects
3. Easy onboarding for developers

## Additional Context

```php
namespace ArtisanPackUI\Performance\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'performance:install
        {--config : Only publish configuration}
        {--views : Publish views}
        {--migrations : Run migrations}
        {--force : Overwrite existing files}';

    protected $description = 'Install the ArtisanPack UI Performance package';

    public function handle(): int
    {
        $this->info( 'Installing ArtisanPack UI Performance...' );

        // 1. Publish config
        $this->callSilently( 'vendor:publish', [
            '--tag' => 'performance-config',
            '--force' => $this->option( 'force' ),
        ] );
        $this->info( '✓ Configuration published' );

        // 2. Publish views (if requested)
        if ( $this->option( 'views' ) || $this->confirm( 'Publish views for customization?' ) ) {
            $this->callSilently( 'vendor:publish', [
                '--tag' => 'performance-views',
                '--force' => $this->option( 'force' ),
            ] );
            $this->info( '✓ Views published' );
        }

        // 3. Run migrations
        if ( $this->confirm( 'Run database migrations?', true ) ) {
            $this->callSilently( 'migrate' );
            $this->info( '✓ Migrations complete' );
        }

        $this->newLine();
        $this->info( 'ArtisanPack UI Performance installed successfully!' );
        $this->newLine();
        $this->info( 'Next steps:' );
        $this->line( '  1. Review config/artisanpack/performance.php' );
        $this->line( '  2. Enable desired features in configuration' );
        $this->line( '  3. Add middleware to routes' );
        $this->line( '  4. Visit /performance/dashboard' );

        return self::SUCCESS;
    }
}
```

---

**Related Issues:**
- #001 (Package Setup)
- #002 (Configuration System)
