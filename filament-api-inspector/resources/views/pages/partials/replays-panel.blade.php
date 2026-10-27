<div class="ai-section">
    <div class="ai-section__header">
        <div>
            <h3 class="ai-section__title">Replay history</h3>
            <p class="ai-section__hint">The last {{ $history->count() }} replay attempts for this request.</p>
        </div>
    </div>

    @if ($history->isEmpty())
        <div class="ai-empty-note">
            <x-filament::icon icon="heroicon-o-arrow-path" class="ai-icon" />
            <div>
                <strong>No replays yet.</strong>
                <p>Use the replay console to simulate or re-send this request. Every attempt is recorded here.</p>
            </div>
        </div>
    @else
        <div class="ai-history">
            @foreach ($history as $replay)
                <details class="ai-history__item">
                    <summary class="ai-history__summary">
                        <span class="ai-mode ai-mode--{{ $replay->mode }}">
                            <x-filament::icon icon="{{ $replay->modeEnum()->icon() }}" class="ai-icon ai-icon--xs" />
                            {{ $replay->modeEnum()->label() }}
                        </span>

                        @if ($replay->error)
                            <span class="ai-pill ai-pill--danger">Blocked</span>
                        @elseif ($replay->status_code !== null)
                            <span class="ai-status ai-status--{{ $replay->statusColor() }}">
                                <span class="ai-status__dot"></span>
                                {{ $replay->status_code }}
                            </span>
                        @else
                            <span class="ai-pill ai-pill--info">Plan only</span>
                        @endif

                        <span class="ai-history__method ai-mono">{{ $replay->method }}</span>
                        <span class="ai-history__path ai-mono">{{ parse_url($replay->uri, PHP_URL_PATH) ?: $replay->uri }}</span>
                        <span class="ai-history__time">{{ $replay->created_at?->diffForHumans() }}</span>
                        @if ($replay->rolled_back)
                            <span class="ai-pill ai-pill--success">Rolled back</span>
                        @endif
                    </summary>

                    <div class="ai-history__body">
                        @if ($replay->error)
                            <div class="ai-error-box">{{ $replay->error }}</div>
                        @endif

                        @if ($replay->warnings() !== [])
                            <ul class="ai-warnings">
                                @foreach ($replay->warnings() as $warning)
                                    <li>
                                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="ai-icon ai-icon--xs" />
                                        <span>{{ $warning }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($replay->planSteps() !== [])
                            <ol class="ai-steps">
                                @foreach ($replay->planSteps() as $step)
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

                        @if ($replay->response_body)
                            <details class="ai-details">
                                <summary>Response body</summary>
                                <pre class="ai-code ai-code--compact" @if (is_array($replay->decodedResponse())) data-ai-json @endif>{{ \Spiggle\ApiInspector\Support\Body::pretty($replay->decodedResponse()) }}</pre>
                            </details>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    @endif
</div>
