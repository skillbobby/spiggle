<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Tests;

use Illuminate\Support\Facades\Auth;
use Spiggle\ApiInspector\Enums\ReplayMode;
use Spiggle\ApiInspector\Http\ReplayContext;
use Spiggle\ApiInspector\Models\ApiReplay;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Services\ReplayService;
use Spiggle\ApiInspector\Support\Settings;

final class ReplayTest extends TestCase
{
    public function test_a_simulated_replay_returns_a_plan_without_executing(): void
    {
        $source = $this->captureProbe('original');

        $replay = $this->service()->replay($source, ReplayMode::Simulated);

        $this->assertSame(ReplayMode::Simulated, $replay->modeEnum());
        $this->assertNull($replay->status_code);
        $this->assertNull($replay->error);
        $this->assertSame('simulated', $replay->mode);
        $this->assertSame(1, $source->fresh()->replay_count);
        $this->assertNotNull($source->fresh()->last_replayed_at);
        $this->assertNotEmpty($replay->planSteps());
        $this->assertSame('POST', $replay->plan['target']['method']);
        $this->assertTrue($replay->plan['route']['matched']);
        $this->assertDatabaseCount('replay_probes', 1);
        $this->assertDatabaseCount('api_inspector_requests', 1);
    }

    public function test_a_live_get_replay_executes_the_route_without_recapturing_it(): void
    {
        $this->getJson('/api/orders')->assertOk();
        $source = ApiRequest::query()->firstOrFail();

        $replay = $this->service()->replay($source, ReplayMode::Live);

        $this->assertNull($replay->error);
        $this->assertSame(200, $replay->status_code);
        $this->assertStringContainsString('ORD-1', (string) $replay->response_body);
        $this->assertGreaterThan(0, (float) $replay->duration_ms);
        $this->assertDatabaseCount('api_inspector_requests', 1);
        $this->assertDatabaseCount('api_inspector_replays', 1);
    }

    public function test_live_mutations_can_be_rolled_back(): void
    {
        $source = $this->captureProbe('original');

        $replay = $this->service()->replay($source, ReplayMode::Live, [
            'payload' => ['note' => 'replayed'],
            'rollback' => true,
        ]);

        $this->assertNull($replay->error);
        $this->assertTrue($replay->rolled_back);
        $this->assertSame(201, $replay->status_code);
        $this->assertDatabaseCount('replay_probes', 1);
        $this->assertDatabaseHas('replay_probes', ['note' => 'original']);
        $this->assertDatabaseMissing('replay_probes', ['note' => 'replayed']);
    }

    public function test_live_mutations_persist_when_rollback_is_off(): void
    {
        $source = $this->captureProbe('original');

        $replay = $this->service()->replay($source, ReplayMode::Live, [
            'payload' => ['note' => 'replayed'],
            'rollback' => false,
        ]);

        $this->assertFalse($replay->rolled_back);
        $this->assertSame(201, $replay->status_code);
        $this->assertDatabaseCount('replay_probes', 2);
        $this->assertDatabaseHas('replay_probes', ['note' => 'replayed']);
    }

    public function test_live_replay_restores_the_original_user(): void
    {
        $this->actingAs($this->user)->getJson('/api/me')->assertOk();

        $source = ApiRequest::query()->firstOrFail();
        Auth::logout();

        $replay = $this->service()->replay($source, ReplayMode::Live);

        $this->assertNull($replay->error);
        $this->assertSame(200, $replay->status_code);
        $this->assertStringContainsString('ada@example.com', (string) $replay->response_body);
    }

    public function test_replay_can_override_payload_and_headers(): void
    {
        $source = $this->capturePostOrder();

        $replay = $this->service()->replay($source, ReplayMode::Live, [
            'method' => 'POST',
            'uri' => 'http://localhost/api/echo',
            'payload' => ['hello' => 'world'],
            'headers' => ['X-Trace' => 'replay-test'],
            'rollback' => false,
        ]);

        $this->assertNull($replay->error);
        $this->assertSame(200, $replay->status_code);
        $this->assertStringContainsString('"hello":"world"', (string) $replay->response_body);
        $this->assertSame('replay-test', $replay->request_headers['X-Trace'] ?? null);
    }

    public function test_live_replay_is_blocked_when_the_method_is_not_allowed(): void
    {
        $source = $this->captureProbe('original');

        app(Settings::class)->save([
            'replay' => [
                'enabled' => true,
                'allow_live' => true,
                'allowed_methods' => ['GET'],
                'rollback_by_default' => true,
            ],
        ]);

        $replay = $this->service()->replay($source, ReplayMode::Live);

        $this->assertNotNull($replay->error);
        $this->assertStringContainsString('not allowed', (string) $replay->error);
        $this->assertNull($replay->status_code);
        $this->assertDatabaseCount('replay_probes', 1);
    }

    public function test_live_replay_is_blocked_for_external_hosts_and_inspector_routes(): void
    {
        $source = $this->capturePostOrder();

        $external = $this->service()->replay($source, ReplayMode::Live, [
            'uri' => 'https://evil.example/api/orders',
        ]);

        $this->assertNotNull($external->error);
        $this->assertStringContainsString('not allowed', (string) $external->error);

        $inspector = $this->service()->replay($source, ReplayMode::Live, [
            'uri' => 'http://localhost/api-inspector/loop',
        ]);

        $this->assertNotNull($inspector->error);
        $this->assertStringContainsString('inspector', strtolower((string) $inspector->error));
    }

    public function test_missing_routes_return_a_useful_404_replay(): void
    {
        $source = $this->capturePostOrder();

        $replay = $this->service()->replay($source, ReplayMode::Live, [
            'uri' => 'http://localhost/api/does-not-exist',
        ]);

        $this->assertNull($replay->error);
        $this->assertSame(404, $replay->status_code);
        $this->assertTrue(collect($replay->warnings())->contains(
            fn (string $warning): bool => str_contains($warning, 'No route matched'),
        ));
    }

    public function test_nested_replays_are_blocked(): void
    {
        $source = $this->capturePostOrder();

        $replay = ReplayContext::run(fn (): ApiReplay => $this->service()->replay($source, ReplayMode::Live));

        $this->assertNotNull($replay->error);
        $this->assertStringContainsString('Nested replays are blocked', (string) $replay->error);
    }

    public function test_replay_records_are_linked_back_to_the_request(): void
    {
        $source = $this->capturePostOrder();

        $replay = $this->service()->replay($source, ReplayMode::Simulated);

        $this->assertSame($source->id, $replay->api_request_id);
        $this->assertTrue($source->fresh()->replays->contains($replay));
        $this->assertSame($replay->id, $source->fresh()->replays()->first()?->id);
    }

    private function capturePostOrder(): ApiRequest
    {
        $this->postJson('/api/orders', [
            'customer_id' => 42,
            'note' => 'original',
        ])->assertCreated();

        return ApiRequest::query()->firstOrFail();
    }

    private function captureProbe(string $note): ApiRequest
    {
        $this->postJson('/api/probes', ['note' => $note])->assertCreated();

        return ApiRequest::query()->firstOrFail();
    }

    private function service(): ReplayService
    {
        return app(ReplayService::class);
    }
}

