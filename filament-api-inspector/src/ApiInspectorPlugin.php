<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Spiggle\ApiInspector\Filament\Pages\SettingsPage;
use Spiggle\ApiInspector\Filament\Resources\ApiRequestResource;

final class ApiInspectorPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'api-inspector';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                ApiRequestResource::class,
            ])
            ->pages([
                SettingsPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
