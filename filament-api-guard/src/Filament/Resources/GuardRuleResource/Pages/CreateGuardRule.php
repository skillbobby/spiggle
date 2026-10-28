<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource;

class CreateGuardRule extends CreateRecord
{
    protected static string $resource = GuardRuleResource::class;

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'Create guard rule';
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'A rule can match by path, method, IP and user.';
    }

    protected function getRedirectUrl(): string
    {
        return GuardRuleResource::getUrl();
    }
}
