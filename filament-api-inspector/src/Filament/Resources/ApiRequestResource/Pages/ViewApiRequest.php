<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Filament\Resources\ApiRequestResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Spiggle\ApiInspector\Enums\ReplayMode;
use Spiggle\ApiInspector\Filament\Resources\ApiRequestResource;
use Spiggle\ApiInspector\Models\ApiReplay;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Services\ReplayService;
use Spiggle\ApiInspector\Support\Body;
use Spiggle\ApiInspector\Support\CurlCommand;
use Spiggle\ApiInspector\Support\Settings;
use Throwable;

class ViewApiRequest extends ViewRecord
{
    protected static string $resource = ApiRequestResource::class;

    protected string $view = 'api-inspector::pages.view-request';

    public string $replayMode = 'simulated';

    public string $replayMethod = 'GET';

    public string $replayUri = '';

    public string $replayBody = '';

    public bool $replayRollback = true;

    public ?string $replayResultId = null;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->replayMethod = strtoupper((string) $this->record->method);
        $this->replayUri = (string) ($this->record->uri ?: $this->record->path);
        $this->replayBody = $this->defaultReplayBody();
        $this->replayRollback = app(Settings::class)->rollbackByDefault();
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        /** @var ApiRequest $record */
        $record = $this->getRecord();

        return $record->summary();
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        /** @var ApiRequest $record */
        $record = $this->getRecord();

        return ($record->captured_at?->format('M j, Y · H:i:s') ?? 'Unknown time')
            . '  ·  ' . ($record->status_code ?? '—') . ' ' . ($record->status_text ?? '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Delete')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete this request?')
                ->modalDescription('Its replay history will be removed too.')
                ->action(function (): void {
                    $this->record->replays()->delete();
                    $this->record->delete();

                    Notification::make()->success()->title('Request deleted')->send();

                    $this->redirect(ListApiRequests::getUrl());
                }),
        ];
    }

    public function runReplay(): void
    {
        $this->validate([
            'replayMode' => ['required', 'in:simulated,live'],
            'replayMethod' => ['required', 'string', 'max:20'],
            'replayUri' => ['required', 'string', 'max:2048'],
            'replayBody' => ['nullable', 'string', 'max:' . ((int) config('api-inspector.replay.max_body_kb', 256) * 1024)],
        ]);

        $settings = app(Settings::class);

        if ($this->replayMode === ReplayMode::Live->value && ! $settings->liveReplayEnabled()) {
            Notification::make()
                ->danger()
                ->title('Live replay is disabled')
                ->body('Enable live replays in API Inspector → Settings, or use simulated mode.')
                ->send();

            return;
        }

        try {
            /** @var ApiRequest $record */
            $record = $this->getRecord();

            $replay = app(ReplayService::class)->replay(
                $record,
                ReplayMode::from($this->replayMode),
                [
                    'method' => $this->replayMethod,
                    'uri' => $this->replayUri,
                    'payload' => $this->replayBody,
                    'rollback' => $this->replayMode === ReplayMode::Live->value && $this->replayRollback,
                ],
            );

            $this->replayResultId = $replay->id;
            $this->record->refresh();

            if ($replay->error !== null) {
                Notification::make()
                    ->danger()
                    ->title('Replay did not run')
                    ->body(\Illuminate\Support\Str::limit($replay->error, 180))
                    ->send();
            } else {
                Notification::make()
                    ->success()
                    ->title($replay->modeEnum() === ReplayMode::Simulated ? 'Replay simulated' : 'Replay sent')
                    ->body($replay->modeEnum() === ReplayMode::Simulated
                        ? 'The plan is ready below — nothing was executed.'
                        : 'Response ' . ($replay->status_code ?? '—') . ' in ' . $replay->durationForHumans())
                    ->send();
            }
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Replay failed')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function replayResult(): ?ApiReplay
    {
        /** @var ApiRequest $record */
        $record = $this->getRecord();

        if ($this->replayResultId !== null) {
            $replay = ApiReplay::query()->find($this->replayResultId);

            if ($replay !== null) {
                return $replay;
            }
        }

        return $record->replays()->first();
    }

    /**
     * @return Collection<int, ApiReplay>
     */
    public function replayHistory(): Collection
    {
        /** @var ApiRequest $record */
        $record = $this->getRecord();

        return $record->replays()->limit(8)->get();
    }

    public function curl(): string
    {
        /** @var ApiRequest $record */
        $record = $this->getRecord();

        return CurlCommand::from($record);
    }

    private function defaultReplayBody(): string
    {
        $payload = $this->record->decodedPayload();

        if ($payload === null) {
            return '';
        }

        return Body::pretty($payload);
    }
}

