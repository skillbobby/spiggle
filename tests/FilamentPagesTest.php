<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Tests;

use Livewire\Livewire;
use Spiggle\ApiGuard\Filament\Resources\ApiRequestResource\Pages\ViewApiRequest;
use Spiggle\ApiGuard\Livewire\LiveBar;
use Spiggle\ApiGuard\Filament\Resources\ApiRequestResource\Pages\ListApiRequests;
use Spiggle\ApiGuard\Models\ApiRequest;
use Spiggle\ApiGuard\Models\GuardRule;

final class FilamentPagesTest extends TestCase
{
    public function test_the_request_feed_renders_an_empty_state(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/api-guard/requests');

        $response->assertOk();
        $response->assertSee('Listening for traffic');
        $response->assertSee('Configure tracking');
    }

    public function test_the_request_feed_renders_captured_traffic(): void
    {
        $this->actingAs($this->user)->getJson('/api/orders')->assertOk();

        $response = $this->get('/admin/api-guard/requests');

        $response->assertOk();
        $response->assertSee('Request feed');
        $response->assertSee('/api/orders');
        $response->assertSee('Live tracking');

        $this->assertDatabaseCount('api_guard_requests', 1);
    }

    public function test_the_request_detail_page_renders_the_replay_console(): void
    {
        $this->actingAs($this->user)->postJson('/api/orders', ['total' => 1999])->assertCreated();

        $request = ApiRequest::query()->firstOrFail();

        $response = $this->get(ViewApiRequest::getUrl(['record' => $request]));

        $response->assertOk();
        $response->assertSee('Replay console');
        $response->assertSee('Simulated');
        $response->assertSee('Request payload');
        $response->assertSee('/api/orders');
        $this->assertDatabaseCount('api_guard_requests', 1);
    }

    public function test_the_settings_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/api-guard/settings');

        $response->assertOk();
        $response->assertSee('API Guard settings');
        $response->assertSee('Track incoming requests');
        $response->assertSee('What to track');
        $response->assertSee('Save changes');
        $this->assertDatabaseCount('api_guard_requests', 0);
    }

    public function test_guard_rules_pages_render_and_list_rules(): void
    {
        \Spiggle\ApiGuard\Models\GuardRule::query()->create([
            'name' => 'Block checkout abuse',
            'type' => 'block',
            'enabled' => true,
            'path' => 'api/checkout*',
            'methods' => ['POST'],
            'response_status' => 403,
        ]);

        $this->actingAs($this->user)
            ->get('/admin/api-guard/rules')
            ->assertOk()
            ->assertSee('Guard rules')
            ->assertSee('Block checkout abuse');

        $this->actingAs($this->user)
            ->get('/admin/api-guard/rules/create')
            ->assertOk()
            ->assertSee('Create guard rule');
    }

    public function test_access_can_be_restricted_by_email(): void
    {
        config()->set('api-guard.authorization.emails', ['someone-else@example.com']);

        $this->actingAs($this->user)->get('/admin/api-guard/requests')->assertForbidden();
    }

    public function test_the_live_bar_component_reports_todays_activity(): void
    {
        $this->actingAs($this->user)->getJson('/api/orders')->assertOk();
        $this->getJson('/api/boom')->assertStatus(500);

        Livewire::actingAs($this->user)
            ->test(LiveBar::class)
            ->assertSet('authorized', true)
            ->assertSet('total', 2)
            ->assertSet('errors', 1)
            ->assertSee('Live tracking');
    }

    public function test_a_block_rule_can_be_created_from_the_feed(): void
    {
        $this->getJson('/api/orders')->assertOk();
        $request = ApiRequest::query()->firstOrFail();

        Livewire::actingAs($this->user)
            ->test(ListApiRequests::class)
            ->callTableAction('blockRequest', $request, data: [
                'name' => 'Block feed orders',
                'path' => 'api/orders',
                'methods' => ['GET'],
                'response_status' => 403,
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('api_guard_rules', [
            'name' => 'Block feed orders',
            'type' => 'block',
            'path' => 'api/orders',
        ]);
    }

    public function test_settings_can_be_saved_from_the_page(): void
    {
        Livewire::actingAs($this->user)
            ->test(\Spiggle\ApiGuard\Filament\Pages\SettingsPage::class)
            ->set('data.enabled', false)
            ->assertSet('data.enabled', false)
            ->set('data.sample_rate', 40)
            ->set('data.capture', ['method', 'uri', 'status', 'timestamp', 'payload'])
            ->set('data.live.interval', 10)
            ->set('data.guard.enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(\Spiggle\ApiGuard\Support\Settings::class);

        $this->assertFalse($settings->isEnabled());
        $this->assertSame(40, $settings->sampleRate());
        $this->assertContains('payload', $settings->capture());
        $this->assertSame(10, $settings->liveInterval());
        $this->assertFalse($settings->guardEnabled());
    }

    public function test_the_live_bar_detects_new_requests_on_poll(): void
    {
        $component = Livewire::actingAs($this->user)->test(LiveBar::class);

        ApiRequest::query()->create([
            'method' => 'POST',
            'scheme' => 'http',
            'host' => 'localhost',
            'path' => '/api/webhooks/test',
            'uri' => 'http://localhost/api/webhooks/test',
            'status_code' => 500,
            'status_text' => 'Internal Server Error',
            'duration_ms' => 10,
            'ip' => '127.0.0.1',
            'captured_at' => now()->addSecond(),
        ]);

        $component->call('poll')
            ->assertSet('newCount', 1)
            ->assertSet('newErrorCount', 1)
            ->assertSee('+1 new');
    }

    public function test_a_live_replay_can_be_triggered_from_the_detail_page(): void
    {
        $this->actingAs($this->user)->getJson('/api/orders')->assertOk();

        $request = ApiRequest::query()->firstOrFail();

        Livewire::actingAs($this->user)
            ->test(ViewApiRequest::class, ['record' => $request->id])
            ->set('replayMode', 'live')
            ->set('replayRollback', false)
            ->call('runReplay')
            ->assertHasNoErrors();

        $replay = \Spiggle\ApiGuard\Models\ApiReplay::query()->firstOrFail();

        $this->assertSame('live', $replay->mode);
        $this->assertSame(200, $replay->status_code);
        $this->assertNull($replay->error);
    }

    public function test_a_replay_can_be_simulated_from_the_detail_page(): void
    {
        $this->actingAs($this->user)->postJson('/api/orders', ['total' => 1999])->assertCreated();

        $request = ApiRequest::query()->firstOrFail();

        Livewire::actingAs($this->user)
            ->test(ViewApiRequest::class, ['record' => $request->id])
            ->set('replayMode', 'simulated')
            ->call('runReplay')
            ->assertHasNoErrors()
            ->assertSet('replayResultId', fn (?string $id): bool => $id !== null)
            ->assertSee('Original response');

        $this->assertDatabaseCount('api_guard_replays', 1);
        $this->assertSame(1, $request->fresh()->replay_count);
    }
}
