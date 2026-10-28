<section class="ag-card ag-share">
    <h3 class="ag-console__title">Share & debug</h3>
    <p class="ag-console__copy">Grab the exact request as a cURL command, or jump back to guard settings.</p>

    <div class="ag-share__actions">
        <button type="button" class="ag-share__btn" data-ag-copy="{{ $curl }}" data-ag-copy-label="cURL copied">
            <x-filament::icon icon="heroicon-o-clipboard-document" class="ag-icon" />
            <span>
                <strong>Copy as cURL</strong>
                <small>Redacted headers omitted</small>
            </span>
        </button>

        @if ($record->uri)
            <button type="button" class="ag-share__btn" data-ag-copy="{{ $record->uri }}" data-ag-copy-label="URL copied">
                <x-filament::icon icon="heroicon-o-link" class="ag-icon" />
                <span>
                    <strong>Copy URL</strong>
                    <small>{{ $record->host }}</small>
                </span>
            </button>
        @endif
    </div>

    <details class="ag-details ag-details--share">
        <summary>Preview cURL</summary>
        <pre class="ag-code ag-code--compact" id="ag-curl-{{ $record->id }}">{{ $curl }}</pre>
    </details>

    <div class="ag-share__footer">
        <a href="{{ \Spiggle\ApiGuard\Filament\Pages\SettingsPage::getUrl() }}" class="ag-copy-link">
            Guard settings
        </a>
        <span class="ag-muted">ID {{ $record->id }}</span>
    </div>
</section>
