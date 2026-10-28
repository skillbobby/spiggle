<x-filament-panels::page>
    @php
        $enabled = (bool) ($this->data['enabled'] ?? true);
        $capture = (array) ($this->data['capture'] ?? []);
        $enabledFields = \Spiggle\ApiGuard\Enums\CaptureField::cases();
        $sample = [];

        foreach ($enabledFields as $field) {
            if (! in_array($field->value, $capture, true)) {
                continue;
            }

            $sample[$field->value] = match ($field->value) {
                'method' => 'POST',
                'uri' => 'https://app.test/api/orders?include=items',
                'status' => 201,
                'timestamp' => now()->toIso8601String(),
                'duration' => 42.7,
                'memory' => '12.4 MB',
                'ip' => '203.0.113.24',
                'headers' => ['accept' => 'application/json', 'authorization' => '[redacted]'],
                'query' => ['include' => 'items'],
                'payload' => ['customer_id' => 42, 'total' => 1999],
                'response_headers' => ['content-type' => 'application/json'],
                'response_body' => ['id' => 'ord_9f2', 'status' => 'paid'],
                'user' => ['id' => 7, 'label' => 'ada@example.com'],
                'user_agent' => 'Mozilla/5.0',
                'referer' => 'https://app.test/checkout',
                'route' => ['name' => 'api.orders.store', 'controller' => 'OrderController@store'],
                'session' => 'session_ab12…',
                'exception' => null,
                'replay' => ['count' => 2, 'last_mode' => 'simulated'],
            };
        }
    @endphp

    <div class="ag-settings">
        @unless (app(\Spiggle\ApiGuard\Support\Settings::class)->storageReady())
            <div class="ag-danger-note">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="ag-icon" />
                The guard tables are not available yet. Run <code>php artisan migrate</code> (or <code>php artisan api-guard:install --migrate</code>) before saving settings.
            </div>
        @endunless

        <section class="ag-settings-hero">
            <div class="ag-settings-hero__main">
                <span class="ag-settings-hero__eyebrow">Traffic control</span>
                <h2 class="ag-settings-hero__title">Everything your app hears.</h2>
                <p class="ag-settings-hero__copy">
                    Track the calls that matter, hide the ones that don’t, and replay them without leaving the panel.
                    Settings are stored in the database and take effect on the next request.
                </p>
                <div class="ag-settings-hero__meta">
                    <span class="ag-pill {{ $enabled ? 'ag-pill--success' : 'ag-pill--muted' }}">
                        <span class="ag-dot {{ $enabled ? 'ag-dot--pulse' : '' }}"></span>
                        {{ $enabled ? 'Tracking is on' : 'Tracking is paused' }}
                    </span>
                    <span class="ag-pill ag-pill--muted">
                        {{ count($capture) }} fields stored
                    </span>
                    <span class="ag-pill ag-pill--muted">
                        {{ (int) ($this->data['live']['interval'] ?? 5) }}s live refresh
                    </span>
                </div>
            </div>
            <div class="ag-settings-hero__ring" aria-hidden="true">
                <span class="ag-ping"></span>
                <x-filament::icon icon="heroicon-o-signal" class="ag-settings-hero__icon" />
            </div>
        </section>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="ag-settings-actions">
                <div class="ag-settings-actions__hint">
                    <x-filament::icon icon="heroicon-o-information-circle" class="ag-icon" />
                    Overrides are stored in the database and applied immediately.
                </div>
                <div class="ag-settings-actions__buttons">
                    <x-filament::button
                        type="button"
                        color="gray"
                        outlined
                        wire:click="resetSettings"
                        wire:confirm="Restore the package defaults? Tracked requests will be kept."
                    >
                        Reset
                    </x-filament::button>
                    <x-filament::button type="submit" icon="heroicon-m-check">
                        Save changes
                    </x-filament::button>
                </div>
            </div>
        </form>

        <section class="ag-preview">
            <div class="ag-preview__header">
                <div>
                    <span class="ag-settings-hero__eyebrow">Live preview</span>
                    <h3 class="ag-preview__title">What a tracked request will look like</h3>
                    <p class="ag-preview__copy">Built from the fields you have checked. Sensitive values are redacted before storage.</p>
                </div>
                <button type="button" class="ag-icon-btn" data-ag-copy-target="ag-capture-preview" title="Copy JSON">
                    <x-filament::icon icon="heroicon-o-clipboard-document" class="ag-icon" />
                    <span>Copy</span>
                </button>
            </div>

            <div class="ag-preview__chips">
                @foreach ($enabledFields as $field)
                    @if (in_array($field->value, $capture, true))
                        <span class="ag-chip {{ $field->isCore() ? 'ag-chip--core' : '' }}">
                            {{ $field->label() }}
                        </span>
                    @endif
                @endforeach
            </div>

            <pre class="ag-code ag-code--preview" data-ag-json id="ag-capture-preview">{{ json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </section>
    </div>
</x-filament-panels::page>
