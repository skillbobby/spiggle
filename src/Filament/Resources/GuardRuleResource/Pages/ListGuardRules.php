<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource;

class ListGuardRules extends ListRecords
{
    protected static string $resource = GuardRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New rule'),
        ];
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'Guard rules';
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'Block or throttle matching traffic before it reaches your application.';
    }
}
