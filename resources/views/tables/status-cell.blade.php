@php
    $color = $record->statusColor();
    $label = $record->statusLabel();
@endphp

<div class="ag-status-cell">
    <span class="ag-status ag-status--{{ $color }}">
        <span class="ag-status__dot"></span>
        {{ $record->status_code ?? '—' }}
    </span>
    @if ($record->statusFamily() !== 'success')
        <span class="ag-status-cell__label">{{ $label }}</span>
    @endif
</div>
