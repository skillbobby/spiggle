@php
    $settings = app(\Spiggle\ApiGuard\Support\Settings::class);
    $liveEnabled = $settings->liveReplayEnabled();
    $steps = $result?->planSteps() ?? [];
    $warnings = $result?->warnings() ?? [];
    $replayBody = $result?->decodedResponse();
@endphp

<section class="ag-card ag-console" id="replay-console" x-data>
    <div class="ag-console__head">
        <div>
            <span class="ag-settings-hero__eyebrow">Time machine</span>
            <h3 class="ag-console__title">Replay console</h3>
            <p class="ag-console__copy">Re-run this request through the app, or simulate it without touching anything.</p>
        </div>
        <x-filament::icon icon="heroicon-o-arrow-path" class="ag-console__icon" />
    </div>

    <div class="ag-seg" role="group" aria-label="Replay mode">
        <button type="button" class="ag-seg__btn {{ $replayMode === 'simulated' ? 'is-active' : '' }}" wire:click="$set('replayMode', 'simulated')">
            <span class="ag-seg__title">Simulated</span>
            <span class="ag-seg__hint">Nothing runs</span>
        </button>
        <button type="button" class="ag-seg__btn {{ $replayMode === 'live' ? 'is-active' : '' }}" wire:click="$set('replayMode', 'live')" @disabled(! $liveEnabled)>
            <span class="ag-seg__title">Live</span>
            <span class="ag-seg__hint">Sends request</span>
        </button>
    </div>

    @if (! $liveEnabled)
        <div class="ag-note">
            Live replay is disabled. Simulated mode still explains exactly what would happen.
        </div>
    @endif

    <div class="ag-form">
        <div class="ag-form__row">
            <span class="ag-label">Method</span>
            <div class="ag-methods" role="group" aria-label="Replay method">
                @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $method)
                    <button
                        type="button"
                        class="ag-method-choice {{ $replayMethod === $method ? 'is-active' : '' }}"
                        wire:click="$set('replayMethod', '{{ $method }}')"
                        aria-pressed="{{ $replayMethod === $method ? 'true' : 'false' }}"
                    >
                        {{ $method }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="ag-form__row">
            <label class="ag-label" for="ag-replay-uri">Target URI</label>
            <input id="ag-replay-uri" type="text" class="ag-input ag-mono" wire:model="replayUri" spellcheck="false">
            @error('replayUri') <span class="ag-error">{{ $message }}</span> @enderror
        </div>

        <div class="ag-form__row">
            <div class="ag-label-row">
                <label class="ag-label" for="ag-replay-body">Body</label>
                <span class="ag-hint">JSON or raw text</span>
            </div>
            <textarea id="ag-replay-body" class="ag-input ag-textarea ag-mono" rows="7" wire:model="replayBody" spellcheck="false" placeholder="{ }"></textarea>
            @error('replayBody') <span class="ag-error">{{ $message }}</span> @enderror
        </div>

        @if ($replayMode === 'live')
            <label class="ag-check">
                <input type="checkbox" wire:model="replayRollback">
                <span>
                    <strong>Safe mode: roll back database writes</strong>
                    <small>Emails, queues and third-party calls cannot be undone.</small>
                </span>
            </label>
            <div class="ag-danger-note">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="ag-icon" />
                Live replays run inside your real application. Only continue if you understand the side effects.
            </div>
        @endif

        <button type="button" class="ag-run" wire:click="runReplay" wire:loading.attr="disabled" wire:target="runReplay">
            <span wire:loading.remove wire:target="runReplay">
                <x-filament::icon icon="{{ $replayMode === 'live' ? 'heroicon-o-play' : 'heroicon-o-beaker' }}" class="ag-icon" />
                {{ $replayMode === 'live' ? 'Send live replay' : 'Simulate replay' }}
            </span>
            <span wire:loading wire:target="runReplay" class="ag-run__loading">
                <x-filament::loading-indicator class="ag-icon" />
                Working…
            </span>
        </button>
    </div>

    @if ($result)
        <div class="ag-result" id="ag-replay-result">
            <div class="ag-result__head">
                <span class="ag-mode ag-mode--{{ $result->mode }}">
                    <x-filament::icon icon="{{ $result->modeEnum()->icon() }}" class="ag-icon ag-icon--xs" />
                    {{ $result->modeEnum()->label() }}
                </span>
                @if ($result->error)
                    <span class="ag-pill ag-pill--danger">Blocked</span>
                @elseif ($result->modeEnum()->executesRequest())
                    <span class="ag-status ag-status--{{ $result->statusColor() }}">
                        <span class="ag-status__dot"></span>
                        {{ $result->status_code ?? '—' }}
                    </span>
                    <span class="ag-pill ag-pill--muted">{{ $result->durationForHumans() }}</span>
                    @if ($result->rolled_back)
                        <span class="ag-pill ag-pill--success">
                            <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="ag-icon ag-icon--xs" />
                            DB rolled back
                        </span>
                    @endif
                @else
                    <span class="ag-pill ag-pill--info">Preview only</span>
                @endif
                <span class="ag-pill ag-pill--muted">{{ $result->created_at?->diffForHumans() }}</span>
            </div>

            @if ($result->error)
                <div class="ag-error-box">{{ $result->error }}</div>
            @endif

            @if ($warnings !== [])
                <ul class="ag-warnings">
                    @foreach ($warnings as $warning)
                        <li>
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="ag-icon ag-icon--xs" />
                            <span>{{ $warning }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($steps !== [])
                <ol class="ag-steps">
                    @foreach ($steps as $step)
                        <li class="ag-step ag-step--{{ $step['status'] ?? 'info' }}">
                            <span class="ag-step__marker"></span>
                            <div class="ag-step__body">
                                <span class="ag-step__label">{{ $step['label'] }}</span>
                                <span class="ag-step__detail">{{ $step['detail'] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($result->modeEnum()->executesRequest() && $replayBody !== null)
                <details class="ag-details">
                    <summary>Response body</summary>
                    @if ($result->response_truncated)
                        <div class="ag-warning">The replay response was truncated.</div>
                    @endif
                    <pre class="ag-code ag-code--compact" @if (is_array($replayBody)) data-ag-json @endif>{{ \Spiggle\ApiGuard\Support\Body::pretty($replayBody) }}</pre>
                </details>
            @endif

            @if ($result->modeEnum() === \Spiggle\ApiGuard\Enums\ReplayMode::Simulated && $record->response_body !== null)
                <div class="ag-note">
                    Simulation does not execute the request. The response below is the original tracking record,
                    shown as a reference for what this route returned.
                </div>
                <details class="ag-details">
                    <summary>Original response · {{ $record->status_code }} {{ $record->status_text }}</summary>
                    <pre class="ag-code ag-code--compact" @if (is_array($record->decodedResponse())) data-ag-json @endif>{{ \Spiggle\ApiGuard\Support\Body::pretty($record->decodedResponse()) }}</pre>
                </details>
            @endif
        </div>
    @else
        <div class="ag-console__empty">
            <x-filament::icon icon="heroicon-o-beaker" class="ag-icon" />
            <p>Run a simulated replay to see the route, auth state and side effects before anything is sent.</p>
        </div>
    @endif
</section>

