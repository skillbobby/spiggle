<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Tests;

use Spiggle\ApiInspector\Services\Pruner;
use Spiggle\ApiInspector\Support\Settings;

final class SettingsTest extends TestCase
{
    public function test_it_merges_config_defaults_with_saved_overrides(): void
    {
        $settings = app(Settings::class);

        $this->assertTrue($settings->isEnabled());
        $this->assertSame(100, $settings->sampleRate());
        $this->assertContains('payload', $settings->capture());
        $this->assertNotContains('session', $settings->capture());
        $this->assertSame([
            'api-inspector/*',
            'api-requests',
            'api-requests/*',
            '*/api-inspector',
            '*/api-inspector/*',
            '*/api-requests',
            '*/api-requests/*',
            'livewire/*',
            'livewire-*',
            'livewire-*/*',
            '_ignition/*',
            'telescope/*',
            'horizon/*',
            'sanctum/csrf-cookie',
            'up',
            'health',
            'healthz',
        ], $settings->ignorePaths());

        $settings->save([
            'enabled' => false,
            'sample_rate' => 25,
            'ignore_paths' => ['api/internal/*'],
        ]);

        $fresh = app(Settings::class);

        $this->assertFalse($fresh->isEnabled());
        $this->assertSame(25, $fresh->sampleRate());
        $this->assertSame(['api/internal/*'], $fresh->ignorePaths());
    }

    public function test_it_sanitizes_saved_values_and_keeps_core_capture_fields(): void
    {
        $settings = app(Settings::class);

        $settings->save([
            'capture' => ['ip', 'bogus-field'],
            'methods' => ['get', 'post', 'trace'],
            'sample_rate' => 900,
            'max_payload_kb' => 0,
            'max_response_kb' => 99999,
            'retention_days' => -10,
            'max_records' => 999999999,
            'live' => ['interval' => 1, 'enabled' => true],
            'replay' => [
                'enabled' => true,
                'allow_live' => false,
                'allowed_methods' => ['get', 'delete', 'nope'],
            ],
        ]);

        $this->assertSame(
            ['method', 'uri', 'status', 'timestamp', 'ip'],
            $settings->capture(),
        );
        $this->assertSame(['GET', 'POST'], $settings->methods());
        $this->assertSame(100, $settings->sampleRate());
        $this->assertSame(1, $settings->get('max_payload_kb'));
        $this->assertSame(4096, $settings->get('max_response_kb'));
        $this->assertSame(0, $settings->retentionDays());
        $this->assertSame(1000000, $settings->maxRecords());
        $this->assertSame(2, $settings->liveInterval());
        $this->assertFalse($settings->liveReplayEnabled());
        $this->assertSame(['GET', 'DELETE'], $settings->replayMethods());
    }

    public function test_it_accepts_newline_separated_lists(): void
    {
        app(Settings::class)->save([
            'only_paths' => "api/*\nwebhooks/*\n\n",
            'redact_headers' => "authorization\nx-custom-secret",
        ]);

        $settings = app(Settings::class);

        $this->assertSame(['api/*', 'webhooks/*'], $settings->onlyPaths());
        $this->assertSame(['authorization', 'x-custom-secret'], $settings->redactHeaders());
    }

    public function test_it_can_reset_to_config_defaults(): void
    {
        $settings = app(Settings::class);

        $settings->save(['enabled' => false, 'sample_rate' => 10]);
        $this->assertFalse($settings->isEnabled());

        $settings->reset();

        $this->assertTrue($settings->isEnabled());
        $this->assertSame(100, $settings->sampleRate());
        $this->assertNull(\Spiggle\ApiInspector\Models\InspectorSetting::query()->find('default'));
    }

    public function test_settings_can_be_partially_updated_without_losing_other_overrides(): void
    {
        $settings = app(Settings::class);

        $settings->save(['enabled' => false, 'sample_rate' => 30]);
        $settings->save(['retention_days' => 3]);

        $fresh = app(Settings::class);

        $this->assertFalse($fresh->isEnabled());
        $this->assertSame(30, $fresh->sampleRate());
        $this->assertSame(3, $fresh->retentionDays());
    }

    public function test_it_reports_when_storage_is_ready(): void
    {
        $this->assertTrue(app(Settings::class)->storageReady());
    }
}
