<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Commands;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'api-inspector:install
        {--migrate : Run the database migrations after publishing}
        {--force : Overwrite an existing config file}';

    protected $description = 'Publish the API Inspector config, migrations and Filament assets.';

    public function handle(): int
    {
        $this->components->info('Installing Spiggle API Inspector…');

        $this->callSilently('vendor:publish', array_filter([
            '--tag' => 'api-inspector-config',
            '--force' => (bool) $this->option('force'),
        ]));

        $this->callSilently('vendor:publish', [
            '--tag' => 'api-inspector-migrations',
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
        $this->components->info('Add ApiInspectorPlugin::make() to your panel provider, then visit API Inspector → Settings.');

        return self::SUCCESS;
    }
}
