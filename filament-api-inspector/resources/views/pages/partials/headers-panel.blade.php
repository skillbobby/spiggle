@php
    $requestHeaders = \Spiggle\ApiInspector\Support\Body::flattenHeaders($record->request_headers ?? []);
    $responseHeaders = \Spiggle\ApiInspector\Support\Body::flattenHeaders($record->response_headers ?? []);
@endphp

<div class="ai-section">
    <div class="ai-headers-grid">
        <div class="ai-headers">
            <h3 class="ai-section__title">Request headers</h3>
            @if (! $has('headers'))
                <div class="ai-empty-note ai-empty-note--compact">
                    <x-filament::icon icon="heroicon-o-eye-slash" class="ai-icon" />
                    <p>Request header capture is off.</p>
                </div>
            @elseif ($requestHeaders === [])
                <p class="ai-muted">No headers captured.</p>
            @else
                <div class="ai-kv ai-kv--headers">
                    @foreach ($requestHeaders as $name => $value)
                        <div class="ai-kv__row">
                            <span class="ai-kv__key">{{ $name }}</span>
                            <span class="ai-kv__value ai-mono ai-break">
                                @if ($value === \Spiggle\ApiInspector\Support\Redactor::PLACEHOLDER)
                                    <span class="ai-redacted">{{ $value }}</span>
                                @else
                                    {{ $value }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="ai-headers">
            <h3 class="ai-section__title">Response headers</h3>
            @if (! $has('response_headers'))
                <div class="ai-empty-note ai-empty-note--compact">
                    <x-filament::icon icon="heroicon-o-eye-slash" class="ai-icon" />
                    <p>Response header capture is off.</p>
                </div>
            @elseif ($responseHeaders === [])
                <p class="ai-muted">No response headers captured.</p>
            @else
                <div class="ai-kv ai-kv--headers">
                    @foreach ($responseHeaders as $name => $value)
                        <div class="ai-kv__row">
                            <span class="ai-kv__key">{{ $name }}</span>
                            <span class="ai-kv__value ai-mono ai-break">
                                @if ($value === \Spiggle\ApiInspector\Support\Redactor::PLACEHOLDER)
                                    <span class="ai-redacted">{{ $value }}</span>
                                @else
                                    {{ $value }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
