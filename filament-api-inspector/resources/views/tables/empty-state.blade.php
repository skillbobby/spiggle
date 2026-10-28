<div class="ai-empty-state">
    <div class="ai-empty-state__signal" aria-hidden="true">
        <span class="ai-empty-state__pulse"></span>
        <x-filament::icon icon="heroicon-o-signal" class="ai-empty-state__icon" />
    </div>

    <h3 class="ai-empty-state__title">Listening for traffic</h3>
    <p class="ai-empty-state__copy">
        Requests appear here automatically. Send a request to your app or open the panel — no refresh needed.
    </p>

    <div class="ai-empty-state__command">
        <span class="ai-empty-state__prompt" aria-hidden="true">$</span>
        <code>curl https://your-app.test/api/…</code>
    </div>

    <a href="{{ \Spiggle\ApiInspector\Filament\Pages\SettingsPage::getUrl() }}" class="ai-empty-state__link">
        Configure capture
        <x-filament::icon icon="heroicon-m-arrow-right" class="ai-icon ai-icon--xs" />
    </a>
</div>
