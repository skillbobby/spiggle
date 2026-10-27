@php
    $ms = (float) ($record->duration_ms ?? 0);
    $width = $ms <= 0 ? 3 : min(100, max(6, $ms / 12));
    $tone = $ms >= 1000 ? 'danger' : ($ms >= 300 ? 'warning' : 'success');
@endphp

<div class="ai-latency" title="Peak memory: {{ $record->memory_bytes ? $record->sizeForHumans($record->memory_bytes) : 'not captured' }}">
    <span class="ai-latency__value">{{ $record->durationForHumans() }}</span>
    <span class="ai-latency__track">
        <span class="ai-latency__bar ai-latency__bar--{{ $tone }}" style="width: {{ $width }}%"></span>
    </span>
</div>
