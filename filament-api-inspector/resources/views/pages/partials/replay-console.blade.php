@php
    $settings = app(\Spiggle\ApiInspector\Support\Settings::class);
    $liveEnabled = $settings->liveReplayEnabled();
    $steps = $result?->planSteps() ?? [];
    $warnings = $result?->warnings() ?? [];
    $replayBody = $result?->decodedResponse();
@endphp

<section class="ai-card ai-console" id="replay-console" x-data>
    <div class="ai-console__head">
        <div>
            <span class="ai-settings-hero__eyebrow">Time machine</span>
            <h3 class="ai-console__title">Replay console</h3>
            <p class="ai-console__copy">Re-run this request through the app, or simulate it without touching anything.</p>
        </div>
        <x-filament::icon icon="heroicon-o-arrow-path" class="ai-console__icon" />
    </div>

    <div class="ai-seg" role="group" aria-label="Replay mode">
        <button type="button" class="ai-seg__btn {{ $replayMode === 'simulated' ? 'is-active' : '' }}" wire:click="$set('replayMode', 'simulated')">
            <span class="ai-seg__title">Simulated</span>
            <span class="ai-seg__hint">Nothing runs</span>
        </button>
        <button type="button" class="ai-seg__btn {{ $replayMode === 'live' ? 'is-active' : '' }}" wire:click="$set('replayMode', 'live')" @disabled(! $liveEnabled)>
            <span class="ai-seg__title">Live</span>
            <span class="ai-seg__hint">Sends request</span>
        </button>
    </div>

    @if (! $liveEnabled)
        <div class="ai-note">
            Live replay is disabled. Simulated mode still explains exactly what would happen.
        </div>
    @endif

    <div class="ai-form">
        <div class="ai-form__row">
            <span class="ai-label">Method</span>
            <div class="ai-methods" role="group" aria-label="Replay method">
                @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $method)
                    <button
                        type="button"
                        class="ai-method-choice {{ $replayMethod === $method ? 'is-active' : '' }}"
                        wire:click="$set('replayMethod', '{{ $method }}')"
                        aria-pressed="{{ $replayMethod === $method ? 'true' : 'false' }}"
                    >
                        {{ $method }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="ai-form__row">
            <label class="ai-label" for="ai-replay-uri">Target URI</label>
            <input id="ai-replay-uri" type="text" class="ai-input ai-mono" wire:model="replayUri" spellcheck="false">
            @error('replayUri') <span class="ai-error">{{ $message }}</span> @enderror
        </div>

        <div class="ai-form__row">
            <div class="ai-label-row">
                <label class="ai-label" for="ai-replay-body">Body</label>
                <span class="ai-hint">JSON or raw text</span>
            </div>
            <textarea id="ai-replay-body" class="ai-input ai-textarea ai-mono" rows="7" wire:model="replayBody" spellcheck="false" placeholder="{ }"></textarea>
            @error('replayBody') <span class="ai-error">{{ $message }}</span> @enderror
        </div>

        @if ($replayMode === 'live')
            <label class="ai-check">
                <input type="checkbox" wire:model="replayRollback">
                <span>
                    <strong>Safe mode: roll back database writes</strong>
                    <small>Emails, queues and third-party calls cannot be undone.</small>
                </span>
            </label>
            <div class="ai-danger-note">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="ai-icon" />
                Live replays run inside your real application. Only continue if you understand the side effects.
            </div>
        @endif

        <button type="button" class="ai-run" wire:click="runReplay" wire:loading.attr="disabled" wire:target="runReplay">
            <span wire:loading.remove wire:target="runReplay">
                <x-filament::icon icon="{{ $replayMode === 'live' ? 'heroicon-o-play' : 'heroicon-o-beaker' }}" class="ai-icon" />
                {{ $replayMode === 'live' ? 'Send live replay' : 'Simulate replay' }}
            </span>
            <span wire:loading wire:target="runReplay" class="ai-run__loading">
                <x-filament::loading-indicator class="ai-icon" />
                Working…
            </span>
        </button>
    </div>

    @if ($result)
        <div class="ai-result" id="ai-replay-result">
            <div class="ai-result__head">
                <span class="ai-mode ai-mode--{{ $result->mode }}">
                    <x-filament::icon icon="{{ $result->modeEnum()->icon() }}" class="ai-icon ai-icon--xs" />
                    {{ $result->modeEnum()->label() }}
                </span>
                @if ($result->error)
                    <span class="ai-pill ai-pill--danger">Blocked</span>
                @elseif ($result->modeEnum()->executesRequest())
                    <span class="ai-status ai-status--{{ $result->statusColor() }}">
                        <span class="ai-status__dot"></span>
                        {{ $result->status_code ?? '—' }}
                    </span>
                    <span class="ai-pill ai-pill--muted">{{ $result->durationForHumans() }}</span>
                    @if ($result->rolled_back)
                        <span class="ai-pill ai-pill--success">
                            <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="ai-icon ai-icon--xs" />
                            DB rolled back
                        </span>
                    @endif
                @else
                    <span class="ai-pill ai-pill--info">Preview only</span>
                @endif
                <span class="ai-pill ai-pill--muted">{{ $result->created_at?->diffForHumans() }}</span>
            </div>

            @if ($result->error)
                <div class="ai-error-box">{{ $result->error }}</div>
            @endif

            @if ($warnings !== [])
                <ul class="ai-warnings">
                    @foreach ($warnings as $warning)
                        <li>
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="ai-icon ai-icon--xs" />
                            <span>{{ $warning }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($steps !== [])
                <ol class="ai-steps">
                    @foreach ($steps as $step)
                        <li class="ai-step ai-step--{{ $step['status'] ?? 'info' }}">
                            <span class="ai-step__marker"></span>
                            <div class="ai-step__body">
                                <span class="ai-step__label">{{ $step['label'] }}</span>
                                <span class="ai-step__detail">{{ $step['detail'] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($result->modeEnum()->executesRequest() && $replayBody !== null)
                <details class="ai-details">
                    <summary>Response body</summary>
                    @if ($result->response_truncated)
                        <div class="ai-warning">The replay response was truncated.</div>
                    @endif
                    <pre class="ai-code ai-code--compact" @if (is_array($replayBody)) data-ai-json @endif>{{ \Spiggle\ApiInspector\Support\Body::pretty($replayBody) }}</pre>
                </details>
            @endif

            @if ($result->modeEnum() === \Spiggle\ApiInspector\Enums\ReplayMode::Simulated && $record->response_body !== null)
                <div class="ai-note">
                    Simulation does not execute the request. The response below is the original capture,
                    shown as a reference for what this route returned.
                </div>
                <details class="ai-details">
                    <summary>Original response · {{ $record->status_code }} {{ $record->status_text }}</summary>
                    <pre class="ai-code ai-code--compact" @if (is_array($record->decodedResponse())) data-ai-json @endif>{{ \Spiggle\ApiInspector\Support\Body::pretty($record->decodedResponse()) }}</pre>
                </details>
            @endif
        </div>
    @else
        <div class="ai-console__empty">
            <x-filament::icon icon="heroicon-o-beaker" class="ai-icon" />
            <p>Run a simulated replay to see the route, auth state and side effects before anything is sent.</p>
        </div>
    @endif
</section>

