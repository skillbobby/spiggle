@php
    $requestHeaders = \Spiggle\ApiGuard\Support\Body::flattenHeaders($record->request_headers ?? []);
    $responseHeaders = \Spiggle\ApiGuard\Support\Body::flattenHeaders($record->response_headers ?? []);
@endphp

<div class="ag-section">
    <div class="ag-headers-grid">
        <div class="ag-headers">
            <h3 class="ag-section__title">Request headers</h3>
            @if (! $has('headers'))
                <div class="ag-empty-note ag-empty-note--compact">
                    <x-filament::icon icon="heroicon-o-eye-slash" class="ag-icon" />
                    <p>Request header tracking is off.</p>
                </div>
            @elseif ($requestHeaders === [])
                <p class="ag-muted">No headers tracked.</p>
            @else
                <div class="ag-kv ag-kv--headers">
                    @foreach ($requestHeaders as $name => $value)
                        <div class="ag-kv__row">
                            <span class="ag-kv__key">{{ $name }}</span>
                            <span class="ag-kv__value ag-mono ag-break">
                                @if ($value === \Spiggle\ApiGuard\Support\Redactor::PLACEHOLDER)
                                    <span class="ag-redacted">{{ $value }}</span>
                                @else
                                    {{ $value }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="ag-headers">
            <h3 class="ag-section__title">Response headers</h3>
            @if (! $has('response_headers'))
                <div class="ag-empty-note ag-empty-note--compact">
                    <x-filament::icon icon="heroicon-o-eye-slash" class="ag-icon" />
                    <p>Response header tracking is off.</p>
                </div>
            @elseif ($responseHeaders === [])
                <p class="ag-muted">No response headers tracked.</p>
            @else
                <div class="ag-kv ag-kv--headers">
                    @foreach ($responseHeaders as $name => $value)
                        <div class="ag-kv__row">
                            <span class="ag-kv__key">{{ $name }}</span>
                            <span class="ag-kv__value ag-mono ag-break">
                                @if ($value === \Spiggle\ApiGuard\Support\Redactor::PLACEHOLDER)
                                    <span class="ag-redacted">{{ $value }}</span>
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
