@php
    $responseHeaders = \Spiggle\ApiInspector\Support\Body::flattenHeaders($record->response_headers ?? []);
    $contentType = $responseHeaders['content-type'] ?? $responseHeaders['Content-Type'] ?? null;
    $body = $record->decodedResponse();
    $maxResponseKb = (int) app(\Spiggle\ApiInspector\Support\Settings::class)->get('max_response_kb', 128);
@endphp

<div class="ai-section">
    <div class="ai-section__header">
        <div>
            <h3 class="ai-section__title">Response</h3>
            <p class="ai-section__hint">
                {{ $record->status_code ?? '—' }} {{ $record->status_text }}
                · {{ $record->durationForHumans() }}
                · {{ $record->sizeForHumans($record->response_size) }}
            </p>
        </div>
        <span class="ai-status ai-status--{{ $record->statusColor() }}">
            <span class="ai-status__dot"></span>
            {{ $record->status_code ?? '—' }}
        </span>
    </div>

    @if ($record->exception_class)
        <div class="ai-exception">
            <div class="ai-exception__head">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="ai-icon" />
                <span class="ai-mono">{{ $record->exception_class }}</span>
            </div>
            <p class="ai-exception__message">{{ $record->exception_message }}</p>
            @if ($record->exception_trace)
                <details class="ai-details">
                    <summary>Stack trace</summary>
                    <pre class="ai-code ai-code--compact">{{ $record->exception_trace }}</pre>
                </details>
            @endif
        </div>
    @endif

    <div class="ai-subsection">
        <div class="ai-section__header ai-section__header--tight">
            <h4 class="ai-subsection__title">Body</h4>
            @if ($body !== null)
                <button type="button" class="ai-icon-btn" data-ai-copy-target="ai-response-{{ $record->id }}" title="Copy response">
                    <x-filament::icon icon="heroicon-o-clipboard-document" class="ai-icon" />
                    <span>Copy</span>
                </button>
            @endif
        </div>

        @if (! $has('response_body'))
            <div class="ai-empty-note">
                <x-filament::icon icon="heroicon-o-eye-slash" class="ai-icon" />
                <div>
                    <strong>Response body capture is off.</strong>
                    <p>Enable “Response body” in settings to keep payloads for future requests.</p>
                </div>
            </div>
        @elseif ($record->response_binary)
            <div class="ai-empty-note">
                <x-filament::icon icon="heroicon-o-photo" class="ai-icon" />
                <div>
                    <strong>Response body skipped.</strong>
                    <p>{{ $contentType ?: 'This content' }} is binary or excluded by the content-type rules. Status, headers and size were captured.</p>
                </div>
            </div>
        @elseif ($body === null)
            <div class="ai-empty-note">
                <x-filament::icon icon="heroicon-o-document" class="ai-icon" />
                <div>
                    <strong>Empty response body.</strong>
                    <p>Streamed responses and redirects without content are not buffered.</p>
                </div>
            </div>
        @else
            @if ($record->response_truncated)
                <div class="ai-warning">The response hit the {{ $maxResponseKb }} KB cap and was truncated.</div>
            @endif
            <pre class="ai-code" @if (is_array($body)) data-ai-json @endif id="ai-response-{{ $record->id }}">{{ \Spiggle\ApiInspector\Support\Body::pretty($body) }}</pre>
        @endif
    </div>

    @if ($responseHeaders !== [])
        <div class="ai-subsection">
            <h4 class="ai-subsection__title">Response headers</h4>
            <div class="ai-kv">
                @foreach ($responseHeaders as $name => $value)
                    <div class="ai-kv__row">
                        <span class="ai-kv__key">{{ $name }}</span>
                        <span class="ai-kv__value ai-mono ai-break">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
