<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Filament\Resources\ApiRequestResource\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Spiggle\ApiGuard\Enums\GuardRuleType;
use Spiggle\ApiGuard\Models\ApiRequest;
use Spiggle\ApiGuard\Models\GuardRule;

final class GuardRuleActions
{
    public static function block(): Action
    {
        return Action::make('blockRequest')
            ->label('Block this request')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->modalHeading('Block matching requests')
            ->modalDescription('Create an active block rule. Paths support wildcards; clear a field to match more traffic.')
            ->fillForm(fn (ApiRequest $record): array => [
                'name' => 'Block ' . $record->path,
                'path' => ltrim((string) $record->path, '/'),
                'methods' => [$record->method],
                'response_status' => 403,
                'response_message' => 'Request blocked by API Guard.',
            ])
            ->form(self::matchForm())
            ->action(function (ApiRequest $record, array $data): void {
                GuardRule::query()->create([
                    'name' => $data['name'],
                    'type' => GuardRuleType::Block->value,
                    'enabled' => true,
                    'priority' => (int) ($data['priority'] ?? 10),
                    'path' => $data['path'] ?: null,
                    'methods' => $data['methods'] ?: null,
                    'ip' => $data['ip'] ?: null,
                    'user_id' => $data['user_id'] ?: null,
                    'response_status' => (int) ($data['response_status'] ?? 403),
                    'response_message' => $data['response_message'] ?: null,
                ]);

                Notification::make()
                    ->success()
                    ->title('Block rule created')
                    ->body($record->method . ' ' . $record->path . ' will now be blocked.')
                    ->send();
            });
    }

    public static function throttle(): Action
    {
        return Action::make('throttleRequest')
            ->label('Throttle this request')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->modalHeading('Throttle matching requests')
            ->modalDescription('Create an active throttle rule. The limit is applied per client IP or authenticated user.')
            ->fillForm(fn (ApiRequest $record): array => [
                'name' => 'Throttle ' . $record->path,
                'path' => ltrim((string) $record->path, '/'),
                'methods' => [$record->method],
                'throttle_limit' => 60,
                'throttle_window' => 60,
                'throttle_key' => 'ip',
                'response_status' => 429,
                'response_message' => 'Too many requests. Please retry later.',
            ])
            ->form(self::matchForm(throttle: true))
            ->action(function (ApiRequest $record, array $data): void {
                GuardRule::query()->create([
                    'name' => $data['name'],
                    'type' => GuardRuleType::Throttle->value,
                    'enabled' => true,
                    'priority' => (int) ($data['priority'] ?? 5),
                    'path' => $data['path'] ?: null,
                    'methods' => $data['methods'] ?: null,
                    'ip' => $data['ip'] ?: null,
                    'user_id' => $data['user_id'] ?: null,
                    'throttle_limit' => (int) ($data['throttle_limit'] ?? 60),
                    'throttle_window' => (int) ($data['throttle_window'] ?? 60),
                    'throttle_key' => $data['throttle_key'] ?? 'ip',
                    'response_status' => (int) ($data['response_status'] ?? 429),
                    'response_message' => $data['response_message'] ?: null,
                ]);

                Notification::make()
                    ->success()
                    ->title('Throttle rule created')
                    ->body($record->method . ' ' . $record->path . ' will be rate limited.')
                    ->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function matchForm(bool $throttle = false): array
    {
        $fields = [
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('path')
                ->label('Path')
                ->maxLength(512)
                ->helperText('Wildcards supported, e.g. api/orders*'),

            CheckboxList::make('methods')
                ->label('Methods')
                ->options([
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'PATCH' => 'PATCH',
                    'DELETE' => 'DELETE',
                    'HEAD' => 'HEAD',
                    'OPTIONS' => 'OPTIONS',
                ])
                ->columns(4),

            TextInput::make('ip')
                ->label('Client IP')
                ->maxLength(64)
                ->placeholder('Optional — exact, wildcard or CIDR'),

            TextInput::make('user_id')
                ->label('User ID')
                ->maxLength(64)
                ->placeholder('Optional'),

            TextInput::make('priority')
                ->numeric()
                ->default($throttle ? 5 : 10),
        ];

        if ($throttle) {
            $fields[] = TextInput::make('throttle_limit')
                ->label('Limit')
                ->numeric()
                ->minValue(1)
                ->default(60)
                ->required();

            $fields[] = TextInput::make('throttle_window')
                ->label('Window (seconds)')
                ->numeric()
                ->minValue(1)
                ->default(60)
                ->required();

            $fields[] = Select::make('throttle_key')
                ->label('Limit by')
                ->options([
                    'ip' => 'Client IP',
                    'user' => 'Authenticated user',
                ])
                ->default('ip')
                ->native(false);
        }

        $fields[] = TextInput::make('response_status')
            ->label('HTTP status')
            ->numeric()
            ->minValue(400)
            ->maxValue(599)
            ->default($throttle ? 429 : 403);

        $fields[] = Textarea::make('response_message')
            ->label('Message')
            ->rows(2)
            ->columnSpanFull();

        return $fields;
    }
}

