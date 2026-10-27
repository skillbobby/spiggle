<x-filament-panels::page>
    @php
        /** @var \Spiggle\ApiInspector\Models\ApiRequest $record */
        $record = $this->getRecord();
        $settings = app(\Spiggle\ApiInspector\Support\Settings::class);
        $capture = $settings->capture();
        $has = fn (string $field): bool => in_array($field, $capture, true);
        $result = $this->replayResult();
        $history = $this->replayHistory();
        $curl = $this->curl();
    @endphp

    <div class="ai-detail" x-data="{ tab: 'request' }">
        <section class="ai-hero">
            <div class="ai-hero__badges">
                <span class="ai-status ai-status--{{ $record->statusColor() }}">
                    <span class="ai-status__dot"></span>
                    {{ $record->status_code ?? '—' }} {{ $record->status_text }}
                </span>
                <span class="ai-pill ai-pill--muted">
                    <x-filament::icon icon="heroicon-m-clock" class="ai-icon ai-icon--xs" />
                    {{ $record->captured_at?->diffForHumans() ?? 'unknown' }}
                </span>
                @if ($record->is_replay)
                    <span class="ai-pill ai-pill--info">Replay</span>
                @endif
                @if ($record->exception_class)
                    <span class="ai-pill ai-pill--danger">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="ai-icon ai-icon--xs" />
                        Exception
                    </span>
                @endif
            </div>

            <div class="ai-hero__meta">
                <span class="ai-mono">{{ $record->ip ?: 'Unknown IP' }}</span>
                <span class="ai-sep"></span>
                <span>{{ $record->user_label ?: 'Guest' }}</span>
                <span class="ai-sep"></span>
                <span>{{ $record->host }}</span>
                @if ($record->route_name)
                    <span class="ai-sep"></span>
                    <span class="ai-mono">{{ $record->route_name }}</span>
                @endif
            </div>

            <div class="ai-metrics">
                <div class="ai-metric">
                    <span class="ai-metric__label">Latency</span>
                    <span class="ai-metric__value">{{ $record->durationForHumans() }}</span>
                </div>
                <div class="ai-metric">
                    <span class="ai-metric__label">Response size</span>
                    <span class="ai-metric__value">{{ $record->sizeForHumans($record->response_size) }}</span>
                </div>
                <div class="ai-metric">
                    <span class="ai-metric__label">Peak memory</span>
                    <span class="ai-metric__value">{{ $record->memory_bytes ? $record->sizeForHumans($record->memory_bytes) : '—' }}</span>
                </div>
                <div class="ai-metric">
                    <span class="ai-metric__label">Replays</span>
                    <span class="ai-metric__value">{{ $record->replay_count }}</span>
                    <span class="ai-metric__hint">{{ $record->last_replayed_at?->diffForHumans() ?? 'Never replayed' }}</span>
                </div>
            </div>
        </section>

        <div class="ai-detail__grid">
            <div class="ai-detail__main">
                <section class="ai-card ai-card--flush">
                    <div class="ai-tabbar" role="tablist" aria-label="Request detail">
                        <button type="button" role="tab" class="ai-tab" x-bind:class="tab === 'request' ? 'is-active' : ''" x-on:click="tab = 'request'" x-bind:aria-selected="tab === 'request'">
                            Request
                        </button>
                        <button type="button" role="tab" class="ai-tab" x-bind:class="tab === 'response' ? 'is-active' : ''" x-on:click="tab = 'response'" x-bind:aria-selected="tab === 'response'">
                            Response
                        </button>
                        <button type="button" role="tab" class="ai-tab" x-bind:class="tab === 'headers' ? 'is-active' : ''" x-on:click="tab = 'headers'" x-bind:aria-selected="tab === 'headers'">
                            Headers
                        </button>
                        <button type="button" role="tab" class="ai-tab" x-bind:class="tab === 'context' ? 'is-active' : ''" x-on:click="tab = 'context'" x-bind:aria-selected="tab === 'context'">
                            Context
                        </button>
                        <button type="button" role="tab" class="ai-tab" x-bind:class="tab === 'replays' ? 'is-active' : ''" x-on:click="tab = 'replays'" x-bind:aria-selected="tab === 'replays'">
                            Replays
                            <span class="ai-tab__count">{{ $history->count() }}</span>

                    <div class="ai-tabpanel" x-show="tab === 'request'" x-cloak>
                        @include('api-inspector::pages.partials.request-panel', ['record' => $record, 'has' => $has])
                    </div>

                    <div class="ai-tabpanel" x-show="tab === 'response'" x-cloak>
                        @include('api-inspector::pages.partials.response-panel', ['record' => $record, 'has' => $has])
                    </div>

                    <div class="ai-tabpanel" x-show="tab === 'headers'" x-cloak>
                        @include('api-inspector::pages.partials.headers-panel', ['record' => $record, 'has' => $has])
                    </div>

                    <div class="ai-tabpanel" x-show="tab === 'context'" x-cloak>
                        @include('api-inspector::pages.partials.context-panel', ['record' => $record, 'has' => $has])
                    </div>

                    <div class="ai-tabpanel" x-show="tab === 'replays'" x-cloak>
                        @include('api-inspector::pages.partials.replays-panel', ['record' => $record, 'history' => $history])
                    </div>
                </section>
            </div>

            <aside class="ai-detail__rail">
                @include('api-inspector::pages.partials.replay-console', ['record' => $record, 'result' => $result])
                @include('api-inspector::pages.partials.share-card', ['record' => $record, 'curl' => $curl])
            </aside>
        </div>
    </div>
</x-filament-panels::page>

                        </button>
                    </div>
