<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Spiggle\ApiGuard\Models\ApiRequest;
use Spiggle\ApiGuard\Support\Access;
use Spiggle\ApiGuard\Support\Settings;
use Throwable;

final class LiveBar extends Component
{
    public bool $authorized = false;

    public bool $live = true;

    public bool $captureEnabled = true;

    public bool $onFeed = false;

    public int $interval = 5;

    public int $total = 0;

    public int $errors = 0;

    public int $newCount = 0;

    public int $newErrorCount = 0;

    public ?string $updatedAt = null;

    public ?string $lastSeenId = null;

    /**
     * @var array<int, array{method: string, path: string, status: int|null}>
     */
    public array $latest = [];

    public function mount(): void
    {
        $this->authorized = Access::allowed();
        $this->onFeed = request()->is('*/api-guard/requests');

        if (! $this->authorized) {
            return;
        }

        $settings = app(Settings::class);
        $this->live = $settings->liveEnabled();
        $this->captureEnabled = $settings->isEnabled();
        $this->interval = $settings->liveInterval();
        $this->lastSeenId = $this->latestRequestId();
        $this->updatedAt = now()->toIso8601String();

        $this->hydrateCounts();
    }

    public function poll(): void
    {
        if (! $this->authorized) {
            return;
        }

        $settings = app(Settings::class);

        if (! $settings->liveEnabled()) {
            $this->live = false;

            return;
        }

        $this->live = true;
        $this->captureEnabled = $settings->isEnabled();
        $this->interval = $settings->liveInterval();

        try {
            $newQuery = ApiRequest::query();

            if ($this->lastSeenId !== null) {
                $newQuery->where('id', '>', $this->lastSeenId);
            } else {
                $newQuery->where('captured_at', '>=', now()->subSeconds($this->interval));
            }

            $this->newCount = (clone $newQuery)->count();
            $this->newErrorCount = (clone $newQuery)->where('status_code', '>=', 500)->count();

            $this->latest = (clone $newQuery)
                ->latestFirst()
                ->limit(3)
                ->get(['id', 'method', 'path', 'status_code'])
                ->map(fn (ApiRequest $request): array => [
                    'method' => (string) $request->method,
                    'path' => (string) $request->path,
                    'status' => $request->status_code,
                ])
                ->all();

            $newestId = (clone $newQuery)->orderByDesc('id')->value('id');

            if (is_string($newestId)) {
                $this->lastSeenId = $newestId;
            }
        } catch (Throwable) {
            $this->newCount = 0;
            $this->newErrorCount = 0;
            $this->latest = [];
        }

        $this->updatedAt = now()->toIso8601String();

        $this->hydrateCounts();
        $this->notify($settings);
    }

    public function render(): View
    {
        return view('api-guard::livewire.live-bar');
    }

    private function latestRequestId(): ?string
    {
        try {
            $id = ApiRequest::query()->orderByDesc('id')->value('id');

            return is_string($id) ? $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function hydrateCounts(): void
    {
        try {
            $from = now()->subDay();

            $this->total = ApiRequest::query()->where('captured_at', '>=', $from)->count();
            $this->errors = ApiRequest::query()
                ->where('captured_at', '>=', $from)
                ->where('status_code', '>=', 500)
                ->count();
            $this->updatedAt = now()->toIso8601String();
        } catch (Throwable) {
            $this->total = 0;
            $this->errors = 0;
        }
    }

    private function notify(Settings $settings): void
    {
        if ($this->newCount <= 0) {
            return;
        }

        if ($this->newErrorCount > 0 && (bool) $settings->get('live.notify_on_error', true)) {
            $latest = $this->latest[0] ?? null;

            Notification::make()
                ->danger()
                ->title($this->newErrorCount === 1 ? 'New server error captured' : $this->newErrorCount . ' new server errors captured')
                ->body($latest ? $latest['method'] . ' ' . $latest['path'] : null)
                ->seconds(7)
                ->send();

            return;
        }

        if ((bool) $settings->get('live.notify_on_new', true)) {
            $latest = $this->latest[0] ?? null;

            Notification::make()
                ->success()
                ->title($this->newCount === 1 ? 'New request captured' : $this->newCount . ' new requests captured')
                ->body($latest ? $latest['method'] . ' ' . $latest['path'] : null)
                ->seconds(5)
                ->send();
        }
    }
}
