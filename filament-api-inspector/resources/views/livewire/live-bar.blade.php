@if ($authorized)
    @php
        $updated = $updatedAt ? \Illuminate\Support\Carbon::parse($updatedAt) : null;
        $secondsAgo = $updated ? max(0, (int) $updated->diffInSeconds(now())) : 0;
        $updatedLabel = $secondsAgo < 5 ? 'just now' : $updated->diffForHumans(short: true);
    @endphp

    <div class="ai-dock" @if ($live) wire:poll.{{ $interval }}s="poll" @endif role="status" aria-live="polite">
        <div class="ai-dock__progress" wire:loading.class="is-loading"></div>

        <div class="ai-dock__inner">
            <div class="ai-dock__status">
                <span class="ai-dock__dot {{ $live && $captureEnabled ? 'is-live' : 'is-paused' }}"></span>
                <div class="ai-dock__status-copy">
                    <span class="ai-dock__title">
                        {{ $live ? ($captureEnabled ? 'Live capture' : 'Capture paused') : 'Live updates paused' }}
                    </span>
                    <span class="ai-dock__updated">
                        Updated {{ $updatedLabel }}
                    </span>
                </div>
            </div>

            <div class="ai-dock__stats">
                <span class="ai-dock__stat">
                    <strong>{{ number_format($total) }}</strong>
                    <span>today</span>
                </span>
                <span class="ai-dock__stat {{ $errors > 0 ? 'is-danger' : '' }}">
                    <strong>{{ number_format($errors) }}</strong>
                    <span>{{ $errors === 1 ? 'error' : 'errors' }}</span>
                </span>
                @if ($newCount > 0)
                    <span class="ai-dock__new">
                        +{{ $newCount }} new
                    </span>
                @endif
            </div>

            <a
                href="{{ \Spiggle\ApiInspector\Filament\Resources\ApiRequestResource::getUrl() }}"
                class="ai-dock__link"
            >
                <span>Open feed</span>
                <x-filament::icon icon="heroicon-m-arrow-right" class="ai-icon ai-icon--xs" />
            </a>
        </div>
    </div>
@endif
