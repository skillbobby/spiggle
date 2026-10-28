@php
    $ms = (float) ($record->duration_ms ?? 0);
    $width = $ms <= 0 ? 3 : min(100, max(10, (log10($ms + 1) / 3.2) * 100));
    $tone = $ms >= 1000 ? 'danger' : ($ms >= 300 ? 'warning' : 'success');
@endphp

<div class="ag-latency" title="Peak memory: {{ $record->memory_bytes ? $record->sizeForHumans($record->memory_bytes) : 'not tracked' }}">
    <span class="ag-latency__value">{{ $record->durationForHumans() }}</span>
    <span class="ag-latency__track">
        <span class="ag-latency__bar ag-latency__bar--{{ $tone }}" style="width: {{ $width }}%"></span>
    </span>
</div>
