<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Pages\CreateGuardRule;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Pages\EditGuardRule;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Pages\ListGuardRules;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Schemas\GuardRuleForm;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Tables\GuardRulesTable;
use Spiggle\ApiGuard\Models\GuardRule;
use Spiggle\ApiGuard\Support\Access;

class GuardRuleResource extends Resource
{
    protected static ?string $model = GuardRule::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'api-guard/rules';

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return GuardRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuardRulesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderByDesc('priority')
            ->orderBy('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuardRules::route('/'),
            'create' => CreateGuardRule::route('/create'),
            'edit' => EditGuardRule::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Access::allowed();
    }

    public static function canCreate(): bool
    {
        return Access::allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return Access::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return Access::allowed();
    }

    public static function getModelLabel(): string
    {
        return 'guard rule';
    }

    public static function getPluralModelLabel(): string
    {
        return 'guard rules';
    }

    public static function getNavigationLabel(): string
    {
        return (string) config('api-guard.navigation.rules_label', 'Rules');
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return (string) config('api-guard.navigation.group', 'API Guard');
    }

    public static function getNavigationIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return (string) config('api-guard.navigation.rules_icon', 'heroicon-o-no-symbol');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('api-guard.navigation.sort', 90) + 1;
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = GuardRule::query()->where('enabled', true)->count();

            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'Enabled guard rules';
    }
}
