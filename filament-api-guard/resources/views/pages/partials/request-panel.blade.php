@php
    $payload = $record->decodedPayload();
    $query = (array) ($record->query ?? []);
    $headers = \Spiggle\ApiGuard\Support\Body::flattenHeaders($record->request_headers ?? []);
    $contentType = $headers['content-type'] ?? $headers['Content-Type'] ?? null;
    $maxPayloadKb = (int) app(\Spiggle\ApiGuard\Support\Settings::class)->get('max_payload_kb', 64);
@endphp

<div class="ag-section">
    <div class="ag-section__header">
        <div>
            <h3 class="ag-section__title">Request payload</h3>
            <p class="ag-section__hint">
                @if ($payload !== null)
                    {{ $record->payload_type === 'form' ? 'Form' : (is_array($payload) ? 'JSON' : 'Raw body') }} · {{ $record->sizeForHumans($record->request_size) }}
                    @if ($record->payload_truncated)
                        · <span class="ag-text-warning">truncated</span>
                    @endif
                @else
                    No body was tracked for this request.
                @endif
            </p>
        </div>
        @if ($payload !== null)
            <button type="button" class="ag-icon-btn" data-ag-copy-target="ag-payload-{{ $record->id }}" title="Copy payload">
                <x-filament::icon icon="heroicon-o-clipboard-document" class="ag-icon" />
                <span>Copy</span>
            </button>
        @endif
    </div>

    @if (! $has('payload'))
        <div class="ag-empty-note">
            <x-filament::icon icon="heroicon-o-eye-slash" class="ag-icon" />
            <div>
                <strong>Payload tracking is off.</strong>
                <p>Turn on “Request payload” in settings to store bodies with future requests.</p>
            </div>
        </div>
    @elseif ($payload === null)
        <div class="ag-empty-note">
            <x-filament::icon icon="heroicon-o-document" class="ag-icon" />
            <div>
                <strong>Empty body.</strong>
                <p>This request did not include a payload, or it was not a content type the guard stores.</p>
            </div>
        </div>
    @elseif ($record->payload_truncated)
        <div class="ag-warning">
            The stored body hit the {{ $maxPayloadKb }} KB cap. The beginning is shown below.
        </div>
        <pre class="ag-code" @if (is_array($payload)) data-ag-json @endif id="ag-payload-{{ $record->id }}">{{ \Spiggle\ApiGuard\Support\Body::pretty($payload) }}</pre>
    @else
        <pre class="ag-code" @if (is_array($payload)) data-ag-json @endif id="ag-payload-{{ $record->id }}">{{ \Spiggle\ApiGuard\Support\Body::pretty($payload) }}</pre>
    @endif

    @if ($query !== [])
        <div class="ag-subsection">
            <h4 class="ag-subsection__title">Query string</h4>
            <div class="ag-kv">
                @foreach ($query as $key => $value)
                    <div class="ag-kv__row">
                        <span class="ag-kv__key">{{ $key }}</span>
                        <span class="ag-kv__value">{{ is_array($value) ? json_encode($value) : $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ag-subsection">
        <h4 class="ag-subsection__title">Request details</h4>
        <div class="ag-facts">
            <div class="ag-fact">
                <span class="ag-fact__label">Method</span>
                <span class="ag-fact__value ag-mono">{{ $record->method }}</span>
            </div>
            <div class="ag-fact ag-fact--wide">
                <span class="ag-fact__label">URI</span>
                <span class="ag-fact__value ag-mono ag-break">{{ $record->uri }}</span>
                @if ($record->uri)
                    <button type="button" class="ag-copy-link" data-ag-copy="{{ $record->uri }}">Copy</button>
                @endif
            </div>
            <div class="ag-fact">
                <span class="ag-fact__label">Content type</span>
                <span class="ag-fact__value ag-mono">{{ $contentType ?: '—' }}</span>
            </div>
            <div class="ag-fact">
                <span class="ag-fact__label">Request size</span>
                <span class="ag-fact__value">{{ $record->sizeForHumans($record->request_size) }}</span>
            </div>
            <div class="ag-fact">
                <span class="ag-fact__label">User agent</span>
                <span class="ag-fact__value ag-break">{{ $record->user_agent ?: '—' }}</span>
            </div>
            <div class="ag-fact">
                <span class="ag-fact__label">Referer</span>
                <span class="ag-fact__value ag-break">{{ $record->referer ?: '—' }}</span>
            </div>
        </div>
    </div>
</div>
