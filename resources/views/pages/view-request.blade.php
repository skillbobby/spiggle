<x-filament-panels::page>
    @php
        /** @var \Spiggle\ApiGuard\Models\ApiRequest $record */
        $record = $this->getRecord();
        $settings = app(\Spiggle\ApiGuard\Support\Settings::class);
        $capture = $settings->capture();
        $has = fn (string $field): bool => in_array($field, $capture, true);
        $result = $this->replayResult();
        $history = $this->replayHistory();
        $curl = $this->curl();
    @endphp

    <div class="ag-detail" x-data="{ tab: 'request' }">
        <section class="ag-hero">
            <div class="ag-hero__badges">
                <span class="ag-status ag-status--{{ $record->statusColor() }}">
                    <span class="ag-status__dot"></span>
                    {{ $record->status_code ?? '—' }} {{ $record->status_text }}
                </span>
                <span class="ag-pill ag-pill--muted">
                    <x-filament::icon icon="heroicon-m-clock" class="ag-icon ag-icon--xs" />
                    {{ $record->captured_at?->diffForHumans() ?? 'unknown' }}
                </span>
                @if ($record->is_replay)
                    <span class="ag-pill ag-pill--info">Replay</span>
                @endif
                @if ($record->exception_class)
                    <span class="ag-pill ag-pill--danger">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="ag-icon ag-icon--xs" />
                        Exception
                    </span>
                @endif
                @if ($record->guard_action)
                    <span class="ag-status ag-status--{{ $record->guardColor() }}" title="{{ $record->guard_rule_name ?: 'API Guard rule' }}">
                        <span class="ag-status__dot"></span>
                        {{ $record->guardLabel() }}
                    </span>
                @endif
                <a href="#replay-console" class="ag-hero__replay">
                    <x-filament::icon icon="heroicon-m-arrow-path" class="ag-icon ag-icon--xs" />
                    Replay request
                    <x-filament::icon icon="heroicon-m-arrow-down" class="ag-icon ag-icon--xs" />
                </a>
            </div>

            <div class="ag-hero__meta">
                <span class="ag-mono">{{ $record->ip ?: 'Unknown IP' }}</span>
                <span class="ag-sep"></span>
                <span>{{ $record->user_label ?: 'Guest' }}</span>
                <span class="ag-sep"></span>
                @if ($record->host && $record->host !== $record->ip)
                    <span>{{ $record->host }}</span>
                @endif
                @if ($record->route_name)
                    <span class="ag-sep"></span>
                    <span class="ag-mono">{{ $record->route_name }}</span>
                @endif
            </div>

            <div class="ag-metrics">
                <div class="ag-metric">
                    <span class="ag-metric__label">Latency</span>
                    <span class="ag-metric__value">{{ $record->durationForHumans() }}</span>
                </div>
                <div class="ag-metric">
                    <span class="ag-metric__label">Response size</span>
                    <span class="ag-metric__value">{{ $record->sizeForHumans($record->response_size) }}</span>
                </div>
                <div class="ag-metric">
                    <span class="ag-metric__label">Peak memory</span>
                    <span class="ag-metric__value">{{ $record->memory_bytes ? $record->sizeForHumans($record->memory_bytes) : '—' }}</span>
                </div>
                <div class="ag-metric">
                    <span class="ag-metric__label">Replays</span>
                    <span class="ag-metric__value">{{ $record->replay_count }}</span>
                    <span class="ag-metric__hint">{{ $record->last_replayed_at?->diffForHumans() ?? 'Never replayed' }}</span>
                </div>
            </div>
        </section>

        <div class="ag-detail__grid">
            <div class="ag-detail__main">
                <section class="ag-card ag-card--flush">
                    <div class="ag-tabbar" role="tablist" aria-label="Request detail">
                        <button type="button" role="tab" id="ag-tab-request" aria-controls="ag-panel-request" class="ag-tab" x-bind:class="tab === 'request' ? 'is-active' : ''" x-on:click="tab = 'request'" x-bind:aria-selected="tab === 'request'">
                            Request
                        </button>
                        <button type="button" role="tab" id="ag-tab-response" aria-controls="ag-panel-response" class="ag-tab" x-bind:class="tab === 'response' ? 'is-active' : ''" x-on:click="tab = 'response'" x-bind:aria-selected="tab === 'response'">
                            Response
                        </button>
                        <button type="button" role="tab" id="ag-tab-headers" aria-controls="ag-panel-headers" class="ag-tab" x-bind:class="tab === 'headers' ? 'is-active' : ''" x-on:click="tab = 'headers'" x-bind:aria-selected="tab === 'headers'">
                            Headers
                        </button>
                        <button type="button" role="tab" id="ag-tab-context" aria-controls="ag-panel-context" class="ag-tab" x-bind:class="tab === 'context' ? 'is-active' : ''" x-on:click="tab = 'context'" x-bind:aria-selected="tab === 'context'">
                            Context
                        </button>
                        <button type="button" role="tab" id="ag-tab-replays" aria-controls="ag-panel-replays" class="ag-tab" x-bind:class="tab === 'replays' ? 'is-active' : ''" x-on:click="tab = 'replays'" x-bind:aria-selected="tab === 'replays'">
                            Replays
                            <span class="ag-tab__count">{{ $history->count() }}</span>
                        </button>
                    </div>

                    <div class="ag-tabpanel" id="ag-panel-request" role="tabpanel" aria-labelledby="ag-tab-request" x-show="tab === 'request'" x-cloak>
                        @include('api-guard::pages.partials.request-panel', ['record' => $record, 'has' => $has])
                    </div>

                    <div class="ag-tabpanel" id="ag-panel-response" role="tabpanel" aria-labelledby="ag-tab-response" x-show="tab === 'response'" x-cloak>
                        @include('api-guard::pages.partials.response-panel', ['record' => $record, 'has' => $has])
                    </div>

                    <div class="ag-tabpanel" id="ag-panel-headers" role="tabpanel" aria-labelledby="ag-tab-headers" x-show="tab === 'headers'" x-cloak>
                        @include('api-guard::pages.partials.headers-panel', ['record' => $record, 'has' => $has])
                    </div>

                    <div class="ag-tabpanel" id="ag-panel-context" role="tabpanel" aria-labelledby="ag-tab-context" x-show="tab === 'context'" x-cloak>
                        @include('api-guard::pages.partials.context-panel', ['record' => $record, 'has' => $has])
                    </div>

                    <div class="ag-tabpanel" id="ag-panel-replays" role="tabpanel" aria-labelledby="ag-tab-replays" x-show="tab === 'replays'" x-cloak>
                        @include('api-guard::pages.partials.replays-panel', ['record' => $record, 'history' => $history])
                    </div>
                </section>
            </div>

            <aside class="ag-detail__rail">
                @include('api-guard::pages.partials.replay-console', ['record' => $record, 'result' => $result])
                @include('api-guard::pages.partials.share-card', ['record' => $record, 'curl' => $curl])
            </aside>
        </div>
    </div>
</x-filament-panels::page>
