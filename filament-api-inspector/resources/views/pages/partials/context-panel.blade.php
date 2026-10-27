<div class="ai-section">
    <div class="ai-facts ai-facts--two">
        <div class="ai-fact">
            <span class="ai-fact__label">Route name</span>
            <span class="ai-fact__value ai-mono">{{ $record->route_name ?: '—' }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">Controller</span>
            <span class="ai-fact__value ai-mono ai-break">{{ $record->controller_action ?: '—' }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">Authenticated user</span>
            <span class="ai-fact__value">{{ $record->user_label ?: 'Guest' }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">User ID</span>
            <span class="ai-fact__value ai-mono">{{ $record->user_id ?: '—' }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">Client IP</span>
            <span class="ai-fact__value ai-mono">{{ $record->ip ?: '—' }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">Session ID</span>
            <span class="ai-fact__value ai-mono ai-break">{{ $record->session_id ?: 'Not captured' }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">Captured at</span>
            <span class="ai-fact__value">{{ $record->captured_at?->format('M j, Y H:i:s.u') }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">Stored at</span>
            <span class="ai-fact__value">{{ $record->created_at?->format('M j, Y H:i:s') }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">Peak memory</span>
            <span class="ai-fact__value">{{ $record->memory_bytes ? $record->sizeForHumans($record->memory_bytes) : '—' }}</span>
        </div>
        <div class="ai-fact">
            <span class="ai-fact__label">Last replay</span>
            <span class="ai-fact__value">{{ $record->last_replayed_at?->format('M j, Y H:i:s') ?? 'Never' }}</span>
        </div>
    </div>

    @if (! empty($record->route_middleware))
        <div class="ai-subsection">
            <h4 class="ai-subsection__title">Middleware</h4>
            <div class="ai-chips">
                @foreach ($record->route_middleware as $middleware)
                    <span class="ai-chip ai-chip--mono">{{ $middleware }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($record->exception_class)
        <div class="ai-subsection">
            <h4 class="ai-subsection__title">Exception</h4>
            <div class="ai-kv">
                <div class="ai-kv__row">
                    <span class="ai-kv__key">Class</span>
                    <span class="ai-kv__value ai-mono">{{ $record->exception_class }}</span>
                </div>
                <div class="ai-kv__row">
                    <span class="ai-kv__key">Message</span>
                    <span class="ai-kv__value">{{ $record->exception_message }}</span>
                </div>
            </div>
        </div>
    @endif
</div>
