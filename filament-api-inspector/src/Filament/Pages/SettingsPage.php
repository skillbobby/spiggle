<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Spiggle\ApiInspector\Enums\CaptureField;
use Spiggle\ApiInspector\Support\Access;
use Spiggle\ApiInspector\Support\Settings;
use Throwable;

/**
 * @property-read Schema $form
 */
class SettingsPage extends Page
{
    protected static ?string $slug = 'api-inspector/settings';

    protected string $view = 'api-inspector::pages.settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(Settings::class)->formData());
    }

    public static function canAccess(): bool
    {
        return Access::allowed();
    }

    public static function getNavigationLabel(): string
    {
        return (string) config('api-inspector.navigation.settings_label', 'Settings');
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
        return (int) config('api-inspector.navigation.sort', 90) + 1;
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'API Inspector settings';
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'Choose what gets captured, what stays private, and how replays behave. Changes apply immediately.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        $this->captureTab(),
                        $this->privacyTab(),
                        $this->replayTab(),
                        $this->liveTab(),
                        $this->retentionTab(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->icon('heroicon-o-check')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save()),
            Action::make('reset')
                ->label('Reset to defaults')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reset inspector settings?')
                ->modalDescription('This removes your saved overrides and restores the package defaults. Captured requests are not deleted.')
                ->action(fn () => $this->resetSettings()),
        ];
    }

    public function save(): void
    {
        try {
            $state = $this->form->getState();
            app(Settings::class)->save($state);
            $this->form->fill(app(Settings::class)->formData());

            Notification::make()
                ->success()
                ->title('Settings saved')
                ->body('New requests will be captured with these rules.')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Could not save settings')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function resetSettings(): void
    {
        try {
            app(Settings::class)->reset();
            $this->form->fill(app(Settings::class)->formData());

            Notification::make()
                ->success()
                ->title('Defaults restored')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Could not restore defaults')
                ->body($exception->getMessage())
                ->send();
        }
    }

    private function captureTab(): Tab
    {
        return Tab::make('Capture')
            ->icon('heroicon-o-signal')
            ->schema([
                Section::make('Inspector status')
                    ->description('The master switch is checked before every request. Turning it off stops all writes immediately.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Capture incoming requests')
                            ->helperText('Captures API, web and panel traffic that passes the rules below.')
                            ->inline(false)
                            ->live(),
                    ]),

                Section::make('What to record')
                    ->description('Pick exactly which pieces of each request are stored. Core fields are always kept so a request can be identified.')
                    ->schema([
                        CheckboxList::make('capture')
                            ->hiddenLabel()
                            ->options(CaptureField::options())
                            ->descriptions(collect(CaptureField::descriptions())
                                ->map(fn (string $description, string $value): string => in_array($value, CaptureField::coreValues(), true)
                                    ? $description . ' Always captured.'
                                    : $description)
                                ->all())
                            ->columns(2)
                            ->bulkToggleable()
                            ->searchable()
                            ->helperText('HTTP method, URI, response status and captured at are always stored so every request can be identified.')
                            ->live(),
                    ]),

                Section::make('Filters')
                    ->description('Paths accept Laravel wildcards such as api/* or webhooks/stripe. Leave “only” empty to capture everything not ignored.')
                    ->columns(2)
                    ->schema([
                        TagsInput::make('only_paths')
                            ->label('Only these paths')
                            ->placeholder('api/*')
                            ->helperText('Allow list. When empty, every path is eligible.'),
                        TagsInput::make('ignore_paths')
                            ->label('Ignore these paths')
                            ->placeholder('health')
                            ->helperText('Checked after the allow list.'),
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
                        TextInput::make('sample_rate')
                            ->label('Sample rate')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Capture a percentage of matching requests. Keep at 100 for a complete feed.'),
                    ]),
            ]);
    }

    private function privacyTab(): Tab
    {
        return Tab::make('Privacy')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Section::make('Redaction')
                    ->description('Matching header names and JSON keys are replaced with [redacted] before a record touches the database.')
                    ->columns(2)
                    ->schema([
                        TagsInput::make('redact_headers')
                            ->label('Redact headers')
                            ->placeholder('authorization')
                            ->columnSpan(1),
                        TagsInput::make('redact_keys')
                            ->label('Redact body & query keys')
                            ->placeholder('password')
                            ->columnSpan(1),
                    ]),

                Section::make('Sensitive values')
                    ->schema([
                        Placeholder::make('privacy_note')
                            ->hiddenLabel()
                            ->content('Cookies, session IDs and authorization headers are never stored in clear text. Session IDs are only captured when the “Session ID” field is enabled above.'),
                    ]),
            ]);
    }



    private function replayTab(): Tab
    {
        return Tab::make('Replay')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Section::make('Replay behaviour')
                    ->columns(2)
                    ->schema([
                        Toggle::make('replay.enabled')
                            ->label('Enable replays')
                            ->helperText('Available from every captured request.')
                            ->live(),
                        Toggle::make('replay.allow_live')
                            ->label('Allow live replays')
                            ->helperText('Simulated replays remain available when this is off.')
                            ->live(),
                        Toggle::make('replay.rollback_by_default')
                            ->label('Roll back database writes by default')
                            ->helperText('Suggested for mutating requests. External side effects cannot be undone.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Allowed methods')
                    ->description('Live replays are blocked for methods that are not selected here.')
                    ->schema([
                        CheckboxList::make('replay.allowed_methods')
                            ->hiddenLabel()
                            ->options([
                                'GET' => 'GET',
                                'HEAD' => 'HEAD',
                                'POST' => 'POST',
                                'PUT' => 'PUT',
                                'PATCH' => 'PATCH',
                                'DELETE' => 'DELETE',
                            ])
                            ->columns(3),
                    ]),
            ]);
    }

    private function liveTab(): Tab
    {
        return Tab::make('Live feed')
            ->icon('heroicon-o-bolt')
            ->schema([
                Section::make('Background updates')
                    ->description('The request feed refreshes itself and shows a live status bar while you work.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('live.enabled')
                            ->label('Enable live updates')
                            ->live(),
                        Select::make('live.interval')
                            ->label('Refresh every')
                            ->options([
                                2 => '2 seconds',
                                5 => '5 seconds',
                                10 => '10 seconds',
                                15 => '15 seconds',
                                30 => '30 seconds',
                                60 => '1 minute',
                            ])
                            ->native(false),
                        Toggle::make('live.notify_on_new')
                            ->label('Toast when requests arrive'),
                        Toggle::make('live.notify_on_error')
                            ->label('Toast when a 5xx error arrives'),
                    ]),
            ]);
    }

    private function retentionTab(): Tab
    {
        return Tab::make('Retention')
            ->icon('heroicon-o-archive-box')
            ->schema([
                Section::make('Automatic pruning')
                    ->description('Requests older than the retention window are removed by the daily api-inspector:prune command.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('retention_days')
                            ->label('Keep requests for')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(3650)
                            ->suffix('days')
                            ->helperText('Set to 0 to keep history indefinitely (subject to the cap below).'),
                        TextInput::make('max_records')
                            ->label('Maximum stored requests')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000000)
                            ->helperText('Oldest records are pruned once the feed grows beyond this. 0 disables the cap.'),
                    ]),

                Section::make('Storage')
                    ->schema([
                        Placeholder::make('retention_note')
                            ->hiddenLabel()
                            ->content('You can prune immediately with: php artisan api-inspector:prune --days=7. Add --dry-run to preview.'),
                    ]),
            ]);
    }
}
