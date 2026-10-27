<section class="ai-card ai-share">
    <h3 class="ai-console__title">Share & debug</h3>
    <p class="ai-console__copy">Grab the exact request as a cURL command, or jump back to capture settings.</p>

    <div class="ai-share__actions">
        <button type="button" class="ai-share__btn" data-ai-copy="{{ $curl }}" data-ai-copy-label="cURL copied">
            <x-filament::icon icon="heroicon-o-clipboard-document" class="ai-icon" />
            <span>
                <strong>Copy as cURL</strong>
                <small>Redacted headers omitted</small>
            </span>
        </button>

        @if ($record->uri)
            <button type="button" class="ai-share__btn" data-ai-copy="{{ $record->uri }}" data-ai-copy-label="URL copied">
                <x-filament::icon icon="heroicon-o-link" class="ai-icon" />
                <span>
                    <strong>Copy URL</strong>
                    <small>{{ $record->host }}</small>
                </span>
            </button>
        @endif
    </div>

    <details class="ai-details ai-details--share">
        <summary>Preview cURL</summary>
        <pre class="ai-code ai-code--compact" id="ai-curl-{{ $record->id }}">{{ $curl }}</pre>
    </details>

    <div class="ai-share__footer">
        <a href="{{ \Spiggle\ApiInspector\Filament\Pages\SettingsPage::getUrl() }}" class="ai-copy-link">
            Capture settings
        </a>
        <span class="ai-muted">ID {{ $record->id }}</span>
    </div>
</section>
