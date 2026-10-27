<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Support\Access;
use Spiggle\ApiInspector\Support\Settings;
use Throwable;

final class LiveBar extends Component
{
    public bool $authorized = false;

    public bool $live = true;

    public int $interval = 5;

    public int $total = 0;

    public int $errors = 0;

    public int $newCount = 0;

    public int $newErrorCount = 0;

    public ?string $updatedAt = null;

    public ?string $lastSeenAt = null;

    /**
     * @var array<int, array{method: string, path: string, status: int|null}>
     */
    public array $latest = [];

    public function mount(): void
    {
        $this->authorized = Access::allowed();

        if (! $this->authorized) {
            return;
        }

        $settings = app(Settings::class);
        $this->live = $settings->liveEnabled();
        $this->interval = $settings->liveInterval();
        $this->lastSeenAt = now()->toIso8601String();
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
        $this->interval = $settings->liveInterval();

        $since = $this->lastSeenAt ? Carbon::parse($this->lastSeenAt) : now()->subSeconds($this->interval);

        try {
            $newQuery = ApiRequest::query()->where('captured_at', '>', $since);
            $this->newCount = (clone $newQuery)->count();
            $this->newErrorCount = (clone $newQuery)->where('status_code', '>=', 500)->count();

            $this->latest = (clone $newQuery)
                ->latestFirst()
                ->limit(3)
                ->get(['method', 'path', 'status_code'])
                ->map(fn (ApiRequest $request): array => [
                    'method' => (string) $request->method,
                    'path' => (string) $request->path,
                    'status' => $request->status_code,
                ])
                ->all();
        } catch (Throwable) {
            $this->newCount = 0;
            $this->newErrorCount = 0;
            $this->latest = [];
        }

        $this->lastSeenAt = now()->toIso8601String();
        $this->updatedAt = now()->toIso8601String();

        $this->hydrateCounts();
        $this->notify($settings);
    }

    public function render(): View
    {
        return view('api-inspector::livewire.live-bar');
    }

    private function hydrateCounts(): void
    {
        try {
            $from = now()->startOfDay();

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
            Notification::make()
                ->danger()
                ->title($this->newErrorCount === 1 ? 'New server error captured' : $this->newErrorCount . ' new server errors captured')
                ->body($this->latest[0]['method'] . ' ' . $this->latest[0]['path'])
                ->seconds(7)
                ->send();

            return;
        }

        if ((bool) $settings->get('live.notify_on_new', true)) {
            Notification::make()
                ->success()
                ->title($this->newCount === 1 ? 'New request captured' : $this->newCount . ' new requests captured')
                ->body($this->latest[0]['method'] . ' ' . $this->latest[0]['path'])
                ->seconds(5)
                ->send();
        }
    }
}
