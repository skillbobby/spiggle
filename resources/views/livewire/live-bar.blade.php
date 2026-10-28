@if ($authorized)
    @php
        $updated = $updatedAt ? \Illuminate\Support\Carbon::parse($updatedAt) : null;
        $secondsAgo = $updated ? max(0, (int) $updated->diffInSeconds(now())) : 0;
        $updatedLabel = $secondsAgo < 5 ? 'just now' : $updated->diffForHumans(short: true);
    @endphp

    <div class="ag-dock" @if ($live) wire:poll.{{ $interval }}s="poll" @endif aria-label="Live API Guard status">
        <div class="ag-dock__progress" wire:loading.class="is-loading" aria-hidden="true"></div>

        <div class="ag-dock__inner">
            <div class="ag-dock__status">
                <span class="ag-dock__dot {{ $live && $captureEnabled ? 'is-live' : 'is-paused' }}"></span>
                <div class="ag-dock__status-copy">
                    <span class="ag-dock__title">
                        {{ $live ? ($captureEnabled ? 'Live tracking' : 'Tracking paused') : 'Live updates paused' }}
                    </span>
                    <span class="ag-dock__updated">
                        Updated {{ $updatedLabel }}
                    </span>
                </div>
            </div>

            <div class="ag-dock__stats">
                <span class="ag-dock__stat">
                    <strong>{{ number_format($total) }}</strong>
                    <span>24h</span>
                </span>
                <span class="ag-dock__stat {{ $errors > 0 ? 'is-danger' : '' }}">
                    <strong>{{ number_format($errors) }}</strong>
                    <span>{{ $errors === 1 ? 'error' : 'errors' }}</span>
                </span>
                @if ($newCount > 0)
                    <span class="ag-dock__new">
                        +{{ $newCount }} new
                    </span>
                @endif
            </div>

            @if ($onFeed)
                <a
                    href="#"
                    class="ag-dock__link"
                    x-on:click.prevent="window.scrollTo({ top: 0, behavior: 'smooth' })"
                >
                    <span>Top</span>
                    <x-filament::icon icon="heroicon-m-arrow-up" class="ag-icon ag-icon--xs" />
                </a>
            @else
                <a
                    href="{{ \Spiggle\ApiGuard\Filament\Resources\ApiRequestResource::getUrl() }}"
                    class="ag-dock__link"
                >
                    <span>Open feed</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" class="ag-icon ag-icon--xs" />
                </a>
            @endif
        </div>
    </div>
@endif
