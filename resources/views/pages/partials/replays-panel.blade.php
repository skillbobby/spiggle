<div class="ag-section">
    <div class="ag-section__header">
        <div>
            <h3 class="ag-section__title">Replay history</h3>
            <p class="ag-section__hint">The last {{ $history->count() }} replay attempts for this request.</p>
        </div>
    </div>

    @if ($history->isEmpty())
        <div class="ag-empty-note">
            <x-filament::icon icon="heroicon-o-arrow-path" class="ag-icon" />
            <div>
                <strong>No replays yet.</strong>
                <p>Use the replay console to simulate or re-send this request. Every attempt is recorded here.</p>
            </div>
        </div>
    @else
        <div class="ag-history">
            @foreach ($history as $replay)
                <details class="ag-history__item">
                    <summary class="ag-history__summary">
                        <span class="ag-mode ag-mode--{{ $replay->mode }}">
                            <x-filament::icon icon="{{ $replay->modeEnum()->icon() }}" class="ag-icon ag-icon--xs" />
                            {{ $replay->modeEnum()->label() }}
                        </span>

                        @if ($replay->error)
                            <span class="ag-pill ag-pill--danger">Blocked</span>
                        @elseif ($replay->status_code !== null)
                            <span class="ag-status ag-status--{{ $replay->statusColor() }}">
                                <span class="ag-status__dot"></span>
                                {{ $replay->status_code }}
                            </span>
                        @else
                            <span class="ag-pill ag-pill--info">Plan only</span>
                        @endif

                        <span class="ag-history__method ag-mono">{{ $replay->method }}</span>
                        <span class="ag-history__path ag-mono">{{ parse_url($replay->uri, PHP_URL_PATH) ?: $replay->uri }}</span>
                        <span class="ag-history__time">{{ $replay->created_at?->diffForHumans() }}</span>
                        @if ($replay->rolled_back)
                            <span class="ag-pill ag-pill--success">Rolled back</span>
                        @endif
                    </summary>

                    <div class="ag-history__body">
                        @if ($replay->error)
                            <div class="ag-error-box">{{ $replay->error }}</div>
                        @endif

                        @if ($replay->warnings() !== [])
                            <ul class="ag-warnings">
                                @foreach ($replay->warnings() as $warning)
                                    <li>
                                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="ag-icon ag-icon--xs" />
                                        <span>{{ $warning }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($replay->planSteps() !== [])
                            <ol class="ag-steps">
                                @foreach ($replay->planSteps() as $step)
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

                        @if ($replay->response_body)
                            <details class="ag-details">
                                <summary>Response body</summary>
                                <pre class="ag-code ag-code--compact" @if (is_array($replay->decodedResponse())) data-ag-json @endif>{{ \Spiggle\ApiGuard\Support\Body::pretty($replay->decodedResponse()) }}</pre>
                            </details>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    @endif
</div>
