<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Spiggle\ApiGuard\Filament\Pages\SettingsPage;
use Spiggle\ApiGuard\Filament\Resources\ApiRequestResource;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource;

final class ApiGuardPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'api-guard';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                ApiRequestResource::class,
                GuardRuleResource::class,
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
