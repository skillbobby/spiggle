<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spiggle\ApiGuard\Enums\GuardRuleType;
use Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Pages\CreateGuardRule;
use Spiggle\ApiGuard\Models\GuardRule;

final class GuardRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (GuardRule $record): string => $record->summary()),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => GuardRuleType::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => GuardRuleType::tryFrom($state)?->color() ?? 'gray')
                    ->icon(fn (string $state): string => GuardRuleType::tryFrom($state)?->icon() ?? 'heroicon-o-shield-check'),

                ToggleColumn::make('enabled')
                    ->label('Active'),

                TextColumn::make('throttle_limit')
                    ->label('Limit')
                    ->formatStateUsing(fn (GuardRule $record): string => $record->typeEnum() === GuardRuleType::Throttle
                        ? $record->throttleLimit() . ' / ' . $record->throttleWindow() . 's'
                        : '—')
                    ->toggleable()
                    ->visibleFrom('md'),

                TextColumn::make('hit_count')
                    ->label('Hits')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->visibleFrom('md'),

                TextColumn::make('last_hit_at')
                    ->label('Last hit')
                    ->since()
                    ->placeholder('Never')
                    ->toggleable()
                    ->alignEnd()
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(GuardRuleType::options())
                    ->native(false),
                SelectFilter::make('enabled')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Paused',
                    ])
                    ->native(false),
            ])
            ->defaultSort('priority', 'desc')
            ->stackedOnMobile()
            ->recordUrl(fn (GuardRule $record): string => \Spiggle\ApiGuard\Filament\Resources\GuardRuleResource::getUrl('edit', ['record' => $record]))
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No guard rules yet')
            ->emptyStateDescription('Block abusive traffic or throttle a path in a few clicks.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Create rule')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => CreateGuardRule::getUrl()),
            ]);
    }
}
