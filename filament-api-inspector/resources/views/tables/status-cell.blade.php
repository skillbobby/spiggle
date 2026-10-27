@php
    $color = $record->statusColor();
    $label = $record->status_text ?: match ($record->statusFamily()) {
        'success' => 'Success',
        'redirect' => 'Redirect',
        'client_error' => 'Client error',
        'server_error' => 'Server error',
        default => 'Unknown',
    };
@endphp

<div class="ai-status-cell">
    <span class="ai-status ai-status--{{ $color }}">
        <span class="ai-status__dot"></span>
        {{ $record->status_code ?? '—' }}
    </span>
    <span class="ai-status-cell__label">{{ $label }}</span>
</div>
