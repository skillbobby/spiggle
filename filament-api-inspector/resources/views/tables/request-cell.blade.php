<div class="ai-request-cell">
    <div class="ai-request-cell__top">
        <span class="ai-method ai-method--{{ strtolower($record->method) }}">{{ $record->method }}</span>
        <span class="ai-request-cell__path" title="{{ $record->uri }}">{{ $record->path ?: '/' }}</span>
        @if ($record->replay_count > 0)
            <span class="ai-request-cell__flag" title="Replayed {{ $record->replay_count }} time(s)">
                replay {{ $record->replay_count }}
            </span>
        @endif
        @if ($record->exception_class)
            <span class="ai-request-cell__flag ai-request-cell__flag--danger" title="{{ $record->exception_class }}">
                <span class="ai-request-cell__flag-mark" aria-hidden="true">!</span>
            </span>
        @endif
    </div>
    <div class="ai-request-cell__meta">
        <span>{{ $record->host }}</span>
        @if ($record->route_name)
            <span class="ai-request-cell__dot ai-request-cell__route-dot">·</span>
            <span class="ai-request-cell__route">{{ $record->route_name }}</span>
        @endif
        @if ($record->user_label)
            <span class="ai-request-cell__dot">·</span>
            <span>{{ $record->user_label }}</span>
        @endif
    </div>
    <div class="ai-request-cell__mobile">
        <span class="ai-status ai-status--{{ $record->statusColor() }}">
            <span class="ai-status__dot"></span>
            {{ $record->status_code ?? '—' }} {{ $record->statusLabel() }}
        </span>
        <span class="ai-request-cell__mobile-item">{{ $record->durationForHumans() }}</span>
        <span class="ai-request-cell__mobile-item">{{ $record->captured_at?->diffForHumans(short: true) }}</span>
    </div>
</div>
