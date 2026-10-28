<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Filament\Resources\ApiRequestResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spiggle\ApiGuard\Filament\Resources\ApiRequestResource\Actions\GuardRuleActions;
use Spiggle\ApiGuard\Filament\Resources\ApiRequestResource\Pages\ViewApiRequest;
use Spiggle\ApiGuard\Models\ApiRequest;
use Spiggle\ApiGuard\Support\Access;
use Spiggle\ApiGuard\Support\Settings;

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
                    ->extraHeaderAttributes(['style' => 'width: 54%', 'class' => 'ag-col-request'])
                    ->extraCellAttributes(['style' => 'width: 54%', 'class' => 'ag-list-cell ag-col-request'])
                    ->formatStateUsing(fn (ApiRequest $record): string => view('api-guard::tables.request-cell', ['record' => $record])->render()),

                TextColumn::make('status_code')
                    ->label('Status')
                    ->sortable()
                    ->alignEnd()
                    ->html()
                    ->extraHeaderAttributes(['style' => 'width: 108px', 'class' => 'ag-col-status'])
                    ->extraCellAttributes(['style' => 'width: 108px', 'class' => 'ag-list-cell ag-col-status'])
                    ->formatStateUsing(fn (ApiRequest $record): string => view('api-guard::tables.status-cell', ['record' => $record])->render()),

                TextColumn::make('duration_ms')
                    ->label('Latency')
                    ->sortable()
                    ->alignEnd()
                    ->html()
                    ->extraHeaderAttributes(['style' => 'width: 84px', 'class' => 'ag-col-latency'])
                    ->extraCellAttributes(['style' => 'width: 84px', 'class' => 'ag-list-cell ag-col-latency'])
                    ->formatStateUsing(fn (ApiRequest $record): string => view('api-guard::tables.duration-cell', ['record' => $record])->render()),

                TextColumn::make('captured_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip('M j, Y H:i:s')
                    ->sortable()
                    ->alignEnd()
                    ->extraHeaderAttributes(['style' => 'width: 92px', 'class' => 'ag-col-when'])
                    ->extraCellAttributes(['style' => 'width: 92px', 'class' => 'ag-col-when']),
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
                    ->native(false)
                    ->options([
                        'success' => '2xx · Success',
                        'redirect' => '3xx · Redirect',
                        'client' => '4xx · Client error',
                        'server' => '5xx · Server error',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'success' => $query->whereBetween('status_code', [200, 299]),
                            'redirect' => $query->whereBetween('status_code', [300, 399]),
                            'client' => $query->whereBetween('status_code', [400, 499]),
                            'server' => $query->where('status_code', '>=', 500),
                            default => $query,
                        };
                    }),

                SelectFilter::make('user_label')
                    ->label('Caller')
                    ->native(false)
                    ->searchable()
                    ->options(fn (): array => ApiRequest::query()
                        ->whereNotNull('user_label')
                        ->where('user_label', '!=', '')
                        ->distinct()
                        ->orderBy('user_label')
                        ->limit(50)
                        ->pluck('user_label', 'user_label')
                        ->all()),

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

                SelectFilter::make('guard_action')
                    ->label('Guard')
                    ->options([
                        'blocked' => 'Blocked',
                        'throttled' => 'Throttled',
                    ])
                    ->native(false),
            ])
            ->recordUrl(fn (ApiRequest $record): string => ViewApiRequest::getUrl(['record' => $record]))
            ->actions([
                Action::make('inspect')
                    ->icon('heroicon-m-eye')
                    ->iconButton()
                    ->tooltip('Inspect request')
                    ->color('gray')
                    ->url(fn (ApiRequest $record): string => ViewApiRequest::getUrl(['record' => $record])),

                Action::make('replay')
                    ->icon('heroicon-m-arrow-path')
                    ->iconButton()
                    ->tooltip('Replay request')
                    ->color('primary')
                    ->url(fn (ApiRequest $record): string => ViewApiRequest::getUrl(['record' => $record, 'replay' => 1])),

                ActionGroup::make([
                    GuardRuleActions::block(),
                    GuardRuleActions::throttle(),
                ])
                    ->icon('heroicon-m-shield-exclamation')
                    ->color('warning')
                    ->tooltip('Block or throttle this request'),
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
            ->searchPlaceholder('Search path, route, IP…')
            ->emptyState(fn () => view('api-guard::tables.empty-state'));
    }
}

