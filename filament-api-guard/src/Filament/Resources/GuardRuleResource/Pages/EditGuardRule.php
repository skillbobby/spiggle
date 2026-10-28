<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource;

class EditGuardRule extends EditRecord
{
    protected static string $resource = GuardRuleResource::class;

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'Edit guard rule';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return GuardRuleResource::getUrl();
    }
}
