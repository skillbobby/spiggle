<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Tests;

use Illuminate\Http\Request;
use Spiggle\ApiGuard\Enums\GuardRuleType;
use Spiggle\ApiGuard\Enums\ReplayMode;
use Spiggle\ApiGuard\Models\ApiRequest;
use Spiggle\ApiGuard\Models\GuardRule;
use Spiggle\ApiGuard\Services\GuardRuleEngine;
use Spiggle\ApiGuard\Services\ReplayService;
use Spiggle\ApiGuard\Support\Settings;

final class GuardTest extends TestCase
{
    public function test_a_block_rule_stops_and_tracks_the_request(): void
    {
        $rule = $this->blockRule();

        $response = $this->getJson('/api/orders');

        $response->assertForbidden()
            ->assertJsonPath('action', 'blocked')
            ->assertJsonPath('rule', 'Block orders')
            ->assertHeader('X-API-Guard-Action', 'blocked')
            ->assertHeader('X-API-Guard-Rule', $rule->id);

        $request = ApiRequest::query()->firstOrFail();

        $this->assertSame(403, $request->status_code);
        $this->assertSame('blocked', $request->guard_action);
        $this->assertSame($rule->id, $request->guard_rule_id);
        $this->assertSame('Block orders', $request->guard_rule_name);
        $this->assertSame(1, $rule->fresh()->hit_count);
        $this->assertNotNull($rule->fresh()->last_hit_at);
    }

    public function test_block_rules_respect_method_matching(): void
    {
        $this->blockRule(methods: ['POST']);

        $this->getJson('/api/orders')->assertOk();
        $this->postJson('/api/orders', ['note' => 'blocked'])->assertForbidden();

        $this->assertDatabaseCount('api_guard_requests', 2);
        $this->assertSame('blocked', ApiRequest::query()->latest('captured_at')->firstOrFail()->guard_action);
    }

    public function test_block_rules_can_target_an_authenticated_user(): void
    {
        $rule = GuardRule::query()->create([
            'name' => 'Block Ada',
            'type' => GuardRuleType::Block->value,
            'enabled' => true,
            'path' => 'api/me',
            'user_id' => (string) $this->user->id,
            'response_status' => 403,
        ]);

        $this->actingAs($this->user)->getJson('/api/me')->assertForbidden();
        $this->assertSame($rule->id, ApiRequest::query()->firstOrFail()->guard_rule_id);
        $this->actingAs($this->user)->getJson('/api/orders')->assertOk();
    }

    public function test_throttle_rules_allow_up_to_the_limit_then_return_429(): void
    {
        GuardRule::query()->create([
            'name' => 'Throttle orders',
            'type' => GuardRuleType::Throttle->value,
            'enabled' => true,
            'priority' => 5,
            'path' => 'api/orders',
            'methods' => ['GET'],
            'throttle_limit' => 2,
            'throttle_window' => 60,
            'throttle_key' => 'ip',
            'response_status' => 429,
            'response_message' => 'Slow down.',
        ]);

        $this->getJson('/api/orders')->assertOk();
        $this->getJson('/api/orders')->assertOk();

        $blocked = $this->getJson('/api/orders');

        $blocked->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('action', 'throttled')
            ->assertJsonPath('message', 'Slow down.');

        $this->assertDatabaseCount('api_guard_requests', 3);
        $this->assertSame('throttled', ApiRequest::query()->latest('captured_at')->firstOrFail()->guard_action);
    }

    public function test_guard_can_be_disabled_without_deleting_rules(): void
    {
        $this->blockRule();
        app(Settings::class)->save(['guard' => ['enabled' => false]]);

        $this->getJson('/api/orders')->assertOk();

        $this->assertNull(ApiRequest::query()->firstOrFail()->guard_action);
    }

    public function test_rules_are_ignored_for_the_guard_panel_and_livewire(): void
    {
        GuardRule::query()->create([
            'name' => 'Block everything',
            'type' => GuardRuleType::Block->value,
            'enabled' => true,
        ]);

        $this->get('/livewire-abc123/update');
        $this->actingAs($this->user)->get('/admin/api-guard/requests')->assertOk();
    }

    public function test_a_rule_created_after_rules_were_cached_takes_effect_immediately(): void
    {
        $this->getJson('/api/orders')->assertOk();

        $this->blockRule();

        $this->getJson('/api/orders')->assertForbidden();
    }

    public function test_simulated_replay_predicts_block_rules_and_live_replay_bypasses_them(): void
    {
        $this->getJson('/api/orders')->assertOk();
        $source = ApiRequest::query()->firstOrFail();

        $rule = $this->blockRule();

        $simulated = app(ReplayService::class)->replay($source, ReplayMode::Simulated);

        $this->assertSame('blocked', $simulated->plan['guard']['action']);
        $this->assertSame($rule->name, $simulated->plan['guard']['rule']);
        $this->assertTrue(collect($simulated->warnings())->contains(
            fn (string $warning): bool => str_contains($warning, 'would block'),
        ));

        $live = app(ReplayService::class)->replay($source, ReplayMode::Live);

        $this->assertNull($live->error);
        $this->assertSame(200, $live->status_code);
        $this->assertSame('blocked', $live->plan['guard']['action']);
    }

    public function test_rule_matching_supports_wildcards_methods_and_cidr(): void
    {
        $rule = new GuardRule([
            'name' => 'Scoped',
            'type' => GuardRuleType::Block->value,
            'path' => 'api/orders*',
            'methods' => ['GET'],
            'ip' => '10.0.0.0/8',
        ]);

        $match = Request::create('/api/orders?page=2', 'GET', server: ['REMOTE_ADDR' => '10.4.5.6']);
        $wrongMethod = Request::create('/api/orders', 'POST', server: ['REMOTE_ADDR' => '10.4.5.6']);
        $wrongIp = Request::create('/api/orders', 'GET', server: ['REMOTE_ADDR' => '192.0.2.10']);
        $manual = Request::create('/api/orders', 'GET', server: ['REMOTE_ADDR' => '10.4.5.6']);
        $manual->setUserResolver(fn () => $this->user);

        $this->assertTrue($rule->matches($match));
        $this->assertFalse($rule->matches($wrongMethod));
        $this->assertFalse($rule->matches($wrongIp));

        $userRule = new GuardRule([
            'name' => 'User only',
            'type' => GuardRuleType::Block->value,
            'user_id' => (string) $this->user->id,
        ]);

        $this->assertTrue($userRule->matches($manual));
    }

    public function test_guard_rules_are_cached_and_cache_is_flushed_on_save(): void
    {
        $engine = app(GuardRuleEngine::class);

        $this->assertCount(0, $engine->rules());

        $rule = $this->blockRule();

        $this->assertCount(1, $engine->rules());

        $rule->update(['enabled' => false]);

        $this->assertCount(0, app(GuardRuleEngine::class)->rules());
    }

    /**
     * @param  array<int, string>|null  $methods
     */
    private function blockRule(?array $methods = null): GuardRule
    {
        return GuardRule::query()->create([
            'name' => 'Block orders',
            'type' => GuardRuleType::Block->value,
            'enabled' => true,
            'priority' => 10,
            'path' => 'api/orders',
            'methods' => $methods ?? ['GET'],
            'response_status' => 403,
            'response_message' => 'Not allowed.',
        ]);
    }
}

