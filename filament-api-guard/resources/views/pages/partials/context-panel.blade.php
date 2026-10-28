<div class="ag-section">
    <div class="ag-facts ag-facts--two">
        <div class="ag-fact">
            <span class="ag-fact__label">Route name</span>
            <span class="ag-fact__value ag-mono">{{ $record->route_name ?: '—' }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">Controller</span>
            <span class="ag-fact__value ag-mono ag-break">{{ $record->controller_action ?: '—' }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">Authenticated user</span>
            <span class="ag-fact__value">{{ $record->user_label ?: 'Guest' }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">User ID</span>
            <span class="ag-fact__value ag-mono">{{ $record->user_id ?: '—' }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">Client IP</span>
            <span class="ag-fact__value ag-mono">{{ $record->ip ?: '—' }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">Session ID</span>
            <span class="ag-fact__value ag-mono ag-break">{{ $record->session_id ?: 'Not tracked' }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">Tracked at</span>
            <span class="ag-fact__value">{{ $record->captured_at?->format('M j, Y H:i:s.u') }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">Stored at</span>
            <span class="ag-fact__value">{{ $record->created_at?->format('M j, Y H:i:s') }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">Peak memory</span>
            <span class="ag-fact__value">{{ $record->memory_bytes ? $record->sizeForHumans($record->memory_bytes) : '—' }}</span>
        </div>
        <div class="ag-fact">
            <span class="ag-fact__label">Last replay</span>
            <span class="ag-fact__value">{{ $record->last_replayed_at?->format('M j, Y H:i:s') ?? 'Never' }}</span>
        </div>
    </div>

    @if ($record->guard_action)
        <div class="ag-subsection">
            <h4 class="ag-subsection__title">API Guard decision</h4>
            <div class="ag-kv">
                <div class="ag-kv__row">
                    <span class="ag-kv__key">Action</span>
                    <span class="ag-kv__value">
                        <span class="ag-status ag-status--{{ $record->guardColor() }}">
                            <span class="ag-status__dot"></span>
                            {{ $record->guardLabel() }}
                        </span>
                    </span>
                </div>
                <div class="ag-kv__row">
                    <span class="ag-kv__key">Rule</span>
                    <span class="ag-kv__value">{{ $record->guard_rule_name ?: ($record->guard_rule_id ?: 'Deleted rule') }}</span>
                </div>
            </div>
        </div>
    @endif

    @if (! empty($record->route_middleware))
        <div class="ag-subsection">
            <h4 class="ag-subsection__title">Middleware</h4>
            <div class="ag-chips">
                @foreach ($record->route_middleware as $middleware)
                    <span class="ag-chip ag-chip--mono">{{ $middleware }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($record->exception_class)
        <div class="ag-subsection">
            <h4 class="ag-subsection__title">Exception</h4>
            <div class="ag-kv">
                <div class="ag-kv__row">
                    <span class="ag-kv__key">Class</span>
                    <span class="ag-kv__value ag-mono">{{ $record->exception_class }}</span>
                </div>
                <div class="ag-kv__row">
                    <span class="ag-kv__key">Message</span>
                    <span class="ag-kv__value">{{ $record->exception_message }}</span>
                </div>
            </div>
        </div>
    @endif
</div>
