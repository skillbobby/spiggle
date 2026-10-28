@php
    $responseHeaders = \Spiggle\ApiGuard\Support\Body::flattenHeaders($record->response_headers ?? []);
    $contentType = $responseHeaders['content-type'] ?? $responseHeaders['Content-Type'] ?? null;
    $body = $record->decodedResponse();
    $maxResponseKb = (int) app(\Spiggle\ApiGuard\Support\Settings::class)->get('max_response_kb', 128);
@endphp

<div class="ag-section">
    <div class="ag-section__header">
        <div>
            <h3 class="ag-section__title">Response</h3>
            <p class="ag-section__hint">
                {{ $record->status_code ?? '—' }} {{ $record->status_text }}
                · {{ $record->durationForHumans() }}
                · {{ $record->sizeForHumans($record->response_size) }}
            </p>
        </div>
        <span class="ag-status ag-status--{{ $record->statusColor() }}">
            <span class="ag-status__dot"></span>
            {{ $record->status_code ?? '—' }}
        </span>
    </div>

    @if ($record->exception_class)
        <div class="ag-exception">
            <div class="ag-exception__head">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="ag-icon" />
                <span class="ag-mono">{{ $record->exception_class }}</span>
            </div>
            <p class="ag-exception__message">{{ $record->exception_message }}</p>
            @if ($record->exception_trace)
                <details class="ag-details">
                    <summary>Stack trace</summary>
                    <pre class="ag-code ag-code--compact">{{ $record->exception_trace }}</pre>
                </details>
            @endif
        </div>
    @endif

    <div class="ag-subsection">
        <div class="ag-section__header ag-section__header--tight">
            <h4 class="ag-subsection__title">Body</h4>
            @if ($body !== null)
                <button type="button" class="ag-icon-btn" data-ag-copy-target="ag-response-{{ $record->id }}" title="Copy response">
                    <x-filament::icon icon="heroicon-o-clipboard-document" class="ag-icon" />
                    <span>Copy</span>
                </button>
            @endif
        </div>

        @if (! $has('response_body'))
            <div class="ag-empty-note">
                <x-filament::icon icon="heroicon-o-eye-slash" class="ag-icon" />
                <div>
                    <strong>Response body tracking is off.</strong>
                    <p>Enable “Response body” in settings to keep payloads for future requests.</p>
                </div>
            </div>
        @elseif ($record->response_binary)
            <div class="ag-empty-note">
                <x-filament::icon icon="heroicon-o-photo" class="ag-icon" />
                <div>
                    <strong>Response body skipped.</strong>
                    <p>{{ $contentType ?: 'This content' }} is binary or excluded by the content-type rules. Status, headers and size were captured.</p>
                </div>
            </div>
        @elseif ($body === null)
            <div class="ag-empty-note">
                <x-filament::icon icon="heroicon-o-document" class="ag-icon" />
                <div>
                    <strong>Empty response body.</strong>
                    <p>Streamed responses and redirects without content are not buffered.</p>
                </div>
            </div>
        @else
            @if ($record->response_truncated)
                <div class="ag-warning">The response hit the {{ $maxResponseKb }} KB cap and was truncated.</div>
            @endif
            <pre class="ag-code" @if (is_array($body)) data-ag-json @endif id="ag-response-{{ $record->id }}">{{ \Spiggle\ApiGuard\Support\Body::pretty($body) }}</pre>
        @endif
    </div>

    @if ($responseHeaders !== [])
        <div class="ag-subsection">
            <h4 class="ag-subsection__title">Response headers</h4>
            <div class="ag-kv">
                @foreach ($responseHeaders as $name => $value)
                    <div class="ag-kv__row">
                        <span class="ag-kv__key">{{ $name }}</span>
                        <span class="ag-kv__value ag-mono ag-break">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
