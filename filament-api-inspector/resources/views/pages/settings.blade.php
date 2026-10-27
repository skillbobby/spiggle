<x-filament-panels::page>
    @php
        $enabled = (bool) ($this->data['enabled'] ?? true);
        $capture = (array) ($this->data['capture'] ?? []);
        $enabledFields = \Spiggle\ApiInspector\Enums\CaptureField::cases();
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

    <div class="ai-settings">
        @unless (app(\Spiggle\ApiInspector\Support\Settings::class)->storageReady())
            <div class="ai-danger-note">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="ai-icon" />
                The inspector tables are not available yet. Run <code>php artisan migrate</code> (or <code>php artisan api-inspector:install --migrate</code>) before saving settings.
            </div>
        @endunless

        <section class="ai-settings-hero">
            <div class="ai-settings-hero__main">
                <span class="ai-settings-hero__eyebrow">Traffic control</span>
                <h2 class="ai-settings-hero__title">Everything your app hears.</h2>
                <p class="ai-settings-hero__copy">
                    Capture the calls that matter, hide the ones that don’t, and replay them without leaving the panel.
                    Settings are stored in the database and take effect on the next request.
                </p>
                <div class="ai-settings-hero__meta">
                    <span class="ai-pill {{ $enabled ? 'ai-pill--success' : 'ai-pill--muted' }}">
                        <span class="ai-dot {{ $enabled ? 'ai-dot--pulse' : '' }}"></span>
                        {{ $enabled ? 'Capture is on' : 'Capture is paused' }}
                    </span>
                    <span class="ai-pill ai-pill--muted">
                        {{ count($capture) }} fields stored
                    </span>
                    <span class="ai-pill ai-pill--muted">
                        {{ (int) ($this->data['live']['interval'] ?? 5) }}s live refresh
                    </span>
                </div>
            </div>
            <div class="ai-settings-hero__ring" aria-hidden="true">
                <span class="ai-ping"></span>
                <x-filament::icon icon="heroicon-o-signal" class="ai-settings-hero__icon" />
            </div>
        </section>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="ai-settings-actions">
                <div class="ai-settings-actions__hint">
                    <x-filament::icon icon="heroicon-o-information-circle" class="ai-icon" />
                    Config is cached and refreshed instantly for this panel.
                </div>
                <div class="ai-settings-actions__buttons">
                    <x-filament::button
                        type="button"
                        color="gray"
                        outlined
                        wire:click="resetSettings"
                        wire:confirm="Restore the package defaults? Captured requests will be kept."
                    >
                        Reset
                    </x-filament::button>
                    <x-filament::button type="submit" icon="heroicon-m-check">
                        Save changes
                    </x-filament::button>
                </div>
            </div>
        </form>

        <section class="ai-preview">
            <div class="ai-preview__header">
                <div>
                    <span class="ai-settings-hero__eyebrow">Live preview</span>
                    <h3 class="ai-preview__title">What a captured request will look like</h3>
                    <p class="ai-preview__copy">Built from the fields you have checked. Sensitive values are redacted before storage.</p>
                </div>
                <button type="button" class="ai-icon-btn" data-ai-copy-target="ai-capture-preview" title="Copy JSON">
                    <x-filament::icon icon="heroicon-o-clipboard-document" class="ai-icon" />
                    <span>Copy</span>
                </button>
            </div>

            <div class="ai-preview__chips">
                @foreach ($enabledFields as $field)
                    @if (in_array($field->value, $capture, true))
                        <span class="ai-chip {{ $field->isCore() ? 'ai-chip--core' : '' }}">
                            {{ $field->label() }}
                        </span>
                    @endif
                @endforeach
            </div>

            <pre class="ai-code ai-code--preview" data-ai-json id="ai-capture-preview">{{ json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </section>
    </div>
</x-filament-panels::page>
