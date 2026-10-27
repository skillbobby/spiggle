@php
    $color = $record->statusColor();
    $label = $record->statusLabel();
@endphp

<div class="ai-status-cell">
    <span class="ai-status ai-status--{{ $color }}">
        <span class="ai-status__dot"></span>
        {{ $record->status_code ?? '—' }}
    </span>
    <span class="ai-status-cell__label">{{ $label }}</span>
</div>
