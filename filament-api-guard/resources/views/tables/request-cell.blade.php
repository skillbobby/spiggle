<div class="ag-request-cell">
    <div class="ag-request-cell__top">
        <span class="ag-method ag-method--{{ strtolower($record->method) }}">{{ $record->method }}</span>
        <span class="ag-request-cell__path" title="{{ $record->uri }}">{{ $record->path ?: '/' }}</span>
        @if ($record->replay_count > 0)
            <span class="ag-request-cell__flag" title="Replayed {{ $record->replay_count }} time(s)">
                replay {{ $record->replay_count }}
            </span>
        @endif
        @if ($record->exception_class)
            <span class="ag-request-cell__flag ag-request-cell__flag--danger" title="{{ $record->exception_class }}">
                <span class="ag-request-cell__flag-mark" aria-hidden="true">!</span>
            </span>
        @endif
        @if ($record->guard_action)
            <span class="ag-request-cell__guard ag-request-cell__guard--{{ $record->guard_action }}" title="{{ $record->guard_rule_name ?: 'API Guard rule' }}">
                {{ $record->guardLabel() }}
            </span>
        @endif
    </div>
    <div class="ag-request-cell__meta">
        <span>{{ $record->host }}</span>
        @if ($record->route_name)
            <span class="ag-request-cell__dot ag-request-cell__route-dot">·</span>
            <span class="ag-request-cell__route">{{ $record->route_name }}</span>
        @endif
        @if ($record->user_label)
            <span class="ag-request-cell__dot">·</span>
            <span>{{ $record->user_label }}</span>
        @endif
    </div>
    <div class="ag-request-cell__mobile">
        <span class="ag-status ag-status--{{ $record->statusColor() }}">
            <span class="ag-status__dot"></span>
            {{ $record->status_code ?? '—' }} {{ $record->statusLabel() }}
        </span>
        <span class="ag-request-cell__mobile-item">{{ $record->durationForHumans() }}</span>
        <span class="ag-request-cell__mobile-item">{{ $record->captured_at?->diffForHumans(short: true) }}</span>
    </div>
</div>
