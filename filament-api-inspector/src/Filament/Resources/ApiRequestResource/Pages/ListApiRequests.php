<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Filament\Resources\ApiRequestResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Spiggle\ApiInspector\Filament\Pages\SettingsPage;
use Spiggle\ApiInspector\Filament\Resources\ApiRequestResource;
use Spiggle\ApiInspector\Filament\Widgets\ApiRequestStats;
use Spiggle\ApiInspector\Models\ApiReplay;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Support\Access;
use Throwable;

class ListApiRequests extends ListRecords
{
    protected static string $resource = ApiRequestResource::class;

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'Request feed';
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'Live traffic, updated automatically. Inspect, filter or replay any request.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ApiRequestStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn (): string => SettingsPage::getUrl()),

            Action::make('clear')
                ->label('Clear feed')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => Access::allowed())
                ->requiresConfirmation()
                ->modalHeading('Delete every captured request?')
                ->modalDescription('This removes all requests and replay history. Capture settings are kept.')
                ->action(function (): void {
                    try {
                        ApiReplay::query()->delete();
                        $count = ApiRequest::query()->count();
                        ApiRequest::query()->delete();

                        Notification::make()
                            ->success()
                            ->title('Feed cleared')
                            ->body($count . ' request(s) removed.')
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Could not clear the feed')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
        ];
    }
}
