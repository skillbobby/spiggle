@php
    $payload = $record->decodedPayload();
    $query = (array) ($record->query ?? []);
    $headers = \Spiggle\ApiInspector\Support\Body::flattenHeaders($record->request_headers ?? []);
    $contentType = $headers['content-type'] ?? $headers['Content-Type'] ?? null;
    $maxPayloadKb = (int) app(\Spiggle\ApiInspector\Support\Settings::class)->get('max_payload_kb', 64);
@endphp

<div class="ai-section">
    <div class="ai-section__header">
        <div>
            <h3 class="ai-section__title">Request payload</h3>
            <p class="ai-section__hint">
                @if ($payload !== null)
                    {{ $record->payload_type === 'form' ? 'Form' : (is_array($payload) ? 'JSON' : 'Raw body') }} · {{ $record->sizeForHumans($record->request_size) }}
                    @if ($record->payload_truncated)
                        · <span class="ai-text-warning">truncated</span>
                    @endif
                @else
                    No body was captured for this request.
                @endif
            </p>
        </div>
        @if ($payload !== null)
            <button type="button" class="ai-icon-btn" data-ai-copy-target="ai-payload-{{ $record->id }}" title="Copy payload">
                <x-filament::icon icon="heroicon-o-clipboard-document" class="ai-icon" />
                <span>Copy</span>
            </button>
        @endif
    </div>

    @if (! $has('payload'))
        <div class="ai-empty-note">
            <x-filament::icon icon="heroicon-o-eye-slash" class="ai-icon" />
            <div>
                <strong>Payload capture is off.</strong>
                <p>Turn on “Request payload” in settings to store bodies with future requests.</p>
            </div>
        </div>
    @elseif ($payload === null)
        <div class="ai-empty-note">
            <x-filament::icon icon="heroicon-o-document" class="ai-icon" />
            <div>
                <strong>Empty body.</strong>
                <p>This request did not include a payload, or it was not a content type the inspector stores.</p>
            </div>
        </div>
    @elseif ($record->payload_truncated)
        <div class="ai-warning">
            The stored body hit the {{ $maxPayloadKb }} KB cap. The beginning is shown below.
        </div>
        <pre class="ai-code" @if (is_array($payload)) data-ai-json @endif id="ai-payload-{{ $record->id }}">{{ \Spiggle\ApiInspector\Support\Body::pretty($payload) }}</pre>
    @else
        <pre class="ai-code" @if (is_array($payload)) data-ai-json @endif id="ai-payload-{{ $record->id }}">{{ \Spiggle\ApiInspector\Support\Body::pretty($payload) }}</pre>
    @endif

    @if ($query !== [])
        <div class="ai-subsection">
            <h4 class="ai-subsection__title">Query string</h4>
            <div class="ai-kv">
                @foreach ($query as $key => $value)
                    <div class="ai-kv__row">
                        <span class="ai-kv__key">{{ $key }}</span>
                        <span class="ai-kv__value">{{ is_array($value) ? json_encode($value) : $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ai-subsection">
        <h4 class="ai-subsection__title">Request details</h4>
        <div class="ai-facts">
            <div class="ai-fact">
                <span class="ai-fact__label">Method</span>
                <span class="ai-fact__value ai-mono">{{ $record->method }}</span>
            </div>
            <div class="ai-fact ai-fact--wide">
                <span class="ai-fact__label">URI</span>
                <span class="ai-fact__value ai-mono ai-break">{{ $record->uri }}</span>
                @if ($record->uri)
                    <button type="button" class="ai-copy-link" data-ai-copy="{{ $record->uri }}">Copy</button>
                @endif
            </div>
            <div class="ai-fact">
                <span class="ai-fact__label">Content type</span>
                <span class="ai-fact__value ai-mono">{{ $contentType ?: '—' }}</span>
            </div>
            <div class="ai-fact">
                <span class="ai-fact__label">Request size</span>
                <span class="ai-fact__value">{{ $record->sizeForHumans($record->request_size) }}</span>
            </div>
            <div class="ai-fact">
                <span class="ai-fact__label">User agent</span>
                <span class="ai-fact__value ai-break">{{ $record->user_agent ?: '—' }}</span>
            </div>
            <div class="ai-fact">
                <span class="ai-fact__label">Referer</span>
                <span class="ai-fact__value ai-break">{{ $record->referer ?: '—' }}</span>
            </div>
        </div>
    </div>
</div>
