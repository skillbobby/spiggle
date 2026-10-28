<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Commands;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'api-guard:install
        {--migrate : Run the database migrations after publishing}
        {--force : Overwrite an existing config file}';

    protected $description = 'Publish the API Guard config, migrations and Filament assets.';

    public function handle(): int
    {
        $this->components->info('Installing Spiggle API Guard…');

        $this->callSilently('vendor:publish', array_filter([
            '--tag' => 'api-guard-config',
            '--force' => (bool) $this->option('force'),
        ]));

        $this->callSilently('vendor:publish', [
            '--tag' => 'api-guard-migrations',
        ]);

        if (class_exists(\Filament\Support\Facades\FilamentAsset::class)) {
            try {
                $this->callSilently('filament:assets');
                $this->components->task('Published Filament assets', fn (): bool => true);
            } catch (\Throwable) {
                $this->components->warn('Could not publish Filament assets. Run php artisan filament:assets manually.');
            }
        }

        if ($this->option('migrate')) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info('Add ApiGuardPlugin::make() to your panel provider, then visit API Guard → Settings.');

        return self::SUCCESS;
    }
}
