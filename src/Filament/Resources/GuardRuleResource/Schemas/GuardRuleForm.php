<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Filament\Resources\GuardRuleResource\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spiggle\ApiGuard\Enums\GuardRuleType;

final class GuardRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rule')
                    ->description('Rules are evaluated by priority. A matching block rule wins over throttling.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Protect checkout'),

                        Select::make('type')
                            ->options(GuardRuleType::options())
                            ->default(GuardRuleType::Block->value)
                            ->required()
                            ->live(),

                        Toggle::make('enabled')
                            ->label('Rule is active')
                            ->default(true),

                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher numbers are evaluated first.'),
                    ]),

                Section::make('Match')
                    ->description('Leave a field empty to match everything. Paths support Laravel wildcards; IPs support wildcards and CIDR.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('path')
                            ->placeholder('api/orders*')
                            ->maxLength(512)
                            ->helperText('Request path without the leading slash.'),

                        TextInput::make('ip')
                            ->placeholder('203.0.113.* or 10.0.0.0/8')
                            ->maxLength(64)
                            ->helperText('Exact IP, wildcard or CIDR range.'),

                        CheckboxList::make('methods')
                            ->label('HTTP methods')
                            ->options([
                                'GET' => 'GET',
                                'HEAD' => 'HEAD',
                                'POST' => 'POST',
                                'PUT' => 'PUT',
                                'PATCH' => 'PATCH',
                                'DELETE' => 'DELETE',
                                'OPTIONS' => 'OPTIONS',
                            ])
                            ->columns(4)
                            ->columnSpanFull(),

                        TextInput::make('user_id')
                            ->label('Authenticated user ID')
                            ->maxLength(64)
                            ->helperText('Only match a specific user. Leave empty for everyone.'),
                    ]),

                Section::make('Throttling')
                    ->description('Applied through Laravel’s rate limiter. The key is the client IP or authenticated user.')
                    ->columns(3)
                    ->visible(fn ($get): bool => $get('type') === GuardRuleType::Throttle->value)
                    ->schema([
                        TextInput::make('throttle_limit')
                            ->label('Request limit')
                            ->numeric()
                            ->minValue(1)
                            ->default(60)
                            ->suffix('requests')
                            ->required(fn ($get): bool => $get('type') === GuardRuleType::Throttle->value),

                        TextInput::make('throttle_window')
                            ->label('Window')
                            ->numeric()
                            ->minValue(1)
                            ->default(60)
                            ->suffix('seconds')
                            ->required(fn ($get): bool => $get('type') === GuardRuleType::Throttle->value),

                        Select::make('throttle_key')
                            ->label('Limit by')
                            ->options([
                                'ip' => 'Client IP',
                                'user' => 'Authenticated user',
                            ])
                            ->default('ip')
                            ->native(false),
                    ]),

                Section::make('Response')
                    ->description('Returned only when the rule blocks or throttles a request.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('response_status')
                            ->label('HTTP status')
                            ->numeric()
                            ->minValue(400)
                            ->maxValue(599)
                            ->default(fn ($get): int => $get('type') === GuardRuleType::Throttle->value ? 429 : 403)
                            ->helperText('Use 429 for throttle rules.'),

                        Textarea::make('response_message')
                            ->label('Message')
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('Request blocked by API Guard.'),
                    ]),
            ]);
    }
}
