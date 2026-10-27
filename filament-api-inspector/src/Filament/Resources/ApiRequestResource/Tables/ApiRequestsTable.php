<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Filament\Resources\ApiRequestResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spiggle\ApiInspector\Filament\Pages\SettingsPage;
use Spiggle\ApiInspector\Filament\Resources\ApiRequestResource\Pages\ViewApiRequest;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Support\Access;
use Spiggle\ApiInspector\Support\Settings;

final class ApiRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('path')
                    ->label('Request')
                    ->searchable(['path', 'uri', 'route_name', 'controller_action', 'user_label', 'ip'])
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn (ApiRequest $record): string => view('api-inspector::tables.request-cell', ['record' => $record])->render()),

                TextColumn::make('status_code')
                    ->label('Status')
                    ->sortable()
                    ->alignEnd()
                    ->html()
                    ->formatStateUsing(fn (ApiRequest $record): string => view('api-inspector::tables.status-cell', ['record' => $record])->render()),

                TextColumn::make('duration_ms')
                    ->label('Latency')
                    ->sortable()
                    ->alignEnd()
                    ->html()
                    ->formatStateUsing(fn (ApiRequest $record): string => view('api-inspector::tables.duration-cell', ['record' => $record])->render()),

                TextColumn::make('user_label')
                    ->label('Caller')
                    ->placeholder('Guest')
                    ->searchable()
                    ->toggleable()
                    ->description(fn (ApiRequest $record): string => (string) ($record->ip ?: 'unknown IP'))
                    ->color('gray'),

                TextColumn::make('captured_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip('M j, Y H:i:s')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'PATCH' => 'PATCH',
                        'DELETE' => 'DELETE',
                        'OPTIONS' => 'OPTIONS',
                        'HEAD' => 'HEAD',
                    ])
                    ->multiple()
                    ->native(false),

                SelectFilter::make('status_family')
                    ->label('Response')
                    ->options([
                        'success' => '2xx · Success',
                        'redirect' => '3xx · Redirect',
                        'client' => '4xx · Client error',
                        'server' => '5xx · Server error',
                    ])
                    ->native(false)
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'success' => $query->whereBetween('status_code', [200, 299]),
                            'redirect' => $query->whereBetween('status_code', [300, 399]),
                            'client' => $query->whereBetween('status_code', [400, 499]),
                            'server' => $query->where('status_code', '>=', 500),
                            default => $query,
                        };
                    }),

                Filter::make('captured_at')
                    ->label('Date range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('captured_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('captured_at', '<=', $date));
                    }),

                Filter::make('failed')
                    ->label('Server errors only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('status_code', '>=', 500)),

                Filter::make('slow')
                    ->label('Slower than 1s')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('duration_ms', '>=', 1000)),

                Filter::make('replayed')
                    ->label('Replayed only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('replay_count', '>', 0)),
            ])
            ->recordUrl(fn (ApiRequest $record): string => ViewApiRequest::getUrl(['record' => $record]))
            ->actions([
                Action::make('inspect')
                    ->icon('heroicon-m-eye')
                    ->iconButton()
                    ->tooltip('Inspect request')
                    ->url(fn (ApiRequest $record): string => ViewApiRequest::getUrl(['record' => $record])),

                Action::make('replay')
                    ->icon('heroicon-m-arrow-path')
                    ->iconButton()
                    ->tooltip('Replay request')
                    ->color('primary')
                    ->url(fn (ApiRequest $record): string => ViewApiRequest::getUrl(['record' => $record, 'replay' => 1])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Access::allowed()),
                ]),
            ])
            ->defaultSort('captured_at', 'desc')
            ->poll(function (): ?string {
                $settings = app(Settings::class);

                return $settings->liveEnabled() ? $settings->liveInterval() . 's' : null;
            })
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->searchPlaceholder('Search path, route, IP or caller…')
            ->emptyStateHeading('Listening for requests')
            ->emptyStateDescription('Traffic appears here automatically. Open your app or send a request to get started.')
            ->emptyStateIcon('heroicon-o-signal')
            ->emptyStateActions([
                Action::make('settings')
                    ->label('Configure capture')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (): string => SettingsPage::getUrl()),
            ]);
    }
}

