<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spiggle\ApiInspector\Filament\Resources\ApiRequestResource\Pages\ListApiRequests;
use Spiggle\ApiInspector\Filament\Resources\ApiRequestResource\Pages\ViewApiRequest;
use Spiggle\ApiInspector\Filament\Resources\ApiRequestResource\Tables\ApiRequestsTable;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Support\Access;
use Throwable;

class ApiRequestResource extends Resource
{
    protected static ?string $model = ApiRequest::class;

    protected static ?string $recordTitleAttribute = 'uri';

    protected static ?string $slug = 'api-requests';

    public static function table(Table $table): Table
    {
        return ApiRequestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latestFirst();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiRequests::route('/'),
            'view' => ViewApiRequest::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Access::allowed();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return Access::allowed();
    }

    public static function getModelLabel(): string
    {
        return 'request';
    }

    public static function getPluralModelLabel(): string
    {
        return 'requests';
    }

    public static function getNavigationLabel(): string
    {
        return (string) config('api-inspector.navigation.requests_label', 'Requests');
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return (string) config('api-inspector.navigation.group', 'API Inspector');
    }

    public static function getNavigationIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return (string) config('api-inspector.navigation.icon', 'heroicon-o-signal');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('api-inspector.navigation.sort', 90);
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = ApiRequest::query()
                ->where('captured_at', '>=', now()->subHour())
                ->where('status_code', '>=', 500)
                ->count();

            return $count > 0 ? (string) $count : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'Server errors in the last hour';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['path', 'uri', 'route_name', 'ip', 'user_label'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string | \Illuminate\Contracts\Support\Htmlable
    {
        /** @var ApiRequest $record */
        return $record->summary();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ApiRequest $record */
        return [
            'Status' => $record->status_code ? (string) $record->status_code : '—',
            'When' => $record->captured_at?->diffForHumans() ?? '—',
        ];
    }
}
