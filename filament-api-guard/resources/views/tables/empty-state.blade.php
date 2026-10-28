<div class="ag-empty-state">
    <div class="ag-empty-state__signal" aria-hidden="true">
        <span class="ag-empty-state__pulse"></span>
        <x-filament::icon icon="heroicon-o-signal" class="ag-empty-state__icon" />
    </div>

    <h3 class="ag-empty-state__title">Listening for traffic</h3>
    <p class="ag-empty-state__copy">
        Requests appear here automatically. Send a request to your app or open the panel — no refresh needed.
    </p>

    <div class="ag-empty-state__command">
        <span class="ag-empty-state__prompt" aria-hidden="true">$</span>
        <code>curl https://your-app.test/api/…</code>
    </div>

    <a href="{{ \Spiggle\ApiGuard\Filament\Pages\SettingsPage::getUrl() }}" class="ag-empty-state__link">
        Configure tracking
        <x-filament::icon icon="heroicon-m-arrow-right" class="ag-icon ag-icon--xs" />
    </a>
</div>
