<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Tests;

use Spiggle\ApiGuard\Models\ApiRequest;
use Spiggle\ApiGuard\Support\Settings;

final class CaptureTest extends TestCase
{
    public function test_it_captures_an_incoming_api_request_with_all_core_data(): void
    {
        $response = $this->getJson('/api/orders?include=items');

        $response->assertOk();

        $this->assertDatabaseCount('api_guard_requests', 1);

        $request = ApiRequest::query()->firstOrFail();

        $this->assertSame('GET', $request->method);
        $this->assertSame('/api/orders', $request->path);
        $this->assertStringContainsString('/api/orders?include=items', (string) $request->uri);
        $this->assertSame(200, $request->status_code);
        $this->assertSame('OK', $request->status_text);
        $this->assertSame(['include' => 'items'], $request->query);
        $this->assertSame('127.0.0.1', $request->ip);
        $this->assertNotNull($request->duration_ms);
        $this->assertGreaterThan(0, $request->duration_ms);
        $this->assertNotNull($request->response_body);
        $this->assertStringContainsString('ORD-1', (string) $request->response_body);
        $this->assertNotNull($request->request_headers);
        $this->assertArrayHasKey('accept', $request->request_headers);
        $this->assertSame('api', $request->route_middleware[0] ?? null);
        $this->assertNull($request->exception_class);
    }

    public function test_it_captures_json_payloads_and_links_the_authenticated_user(): void
    {
        $this->actingAs($this->user)->postJson('/api/orders', [
            'customer_id' => 42,
            'total' => 1999,
        ])->assertCreated();

        $request = ApiRequest::query()->firstOrFail();

        $this->assertSame('POST', $request->method);
        $this->assertSame('json', $request->payload_type);
        $this->assertSame('42', (string) $request->decodedPayload()['customer_id']);
        $this->assertSame(201, $request->status_code);
        $this->assertSame($this->user->id, (int) $request->user_id);
        $this->assertSame('ada@example.com', $request->user_label);
        $this->assertSame($this->user::class, $request->user_type);
        $this->assertStringContainsString('"id":99', (string) $request->response_body);
    }

    public function test_it_redacts_sensitive_headers_and_payload_keys(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer super-secret-token',
            'X-Api-Key' => 'key-123',
            'Cookie' => 'session=secret-session',
        ])->postJson('/api/echo?token=query-secret', [
            'email' => 'ada@example.com',
            'password' => 'hunter2',
            'nested' => [
                'api_key' => 'nested-key',
                'safe' => 'keep-me',
            ],
        ])->assertOk();

        $request = ApiRequest::query()->firstOrFail();

        $this->assertSame('[redacted]', $request->request_headers['authorization'][0]);
        $this->assertSame('[redacted]', $request->request_headers['x-api-key'][0]);
        $this->assertSame('[redacted]', $request->request_headers['cookie'][0]);
        $this->assertSame('[redacted]', $request->query['token']);
        $this->assertStringNotContainsString('query-secret', (string) $request->uri);
        $this->assertStringContainsString('redacted', strtolower((string) $request->uri));

        $payload = $request->decodedPayload();

        $this->assertSame('[redacted]', $payload['password']);
        $this->assertSame('[redacted]', $payload['nested']['api_key']);
        $this->assertSame('keep-me', $payload['nested']['safe']);
        $this->assertSame('ada@example.com', $payload['email']);
        $this->assertStringNotContainsString('hunter2', (string) $request->response_body);
        $this->assertStringContainsString('[redacted]', (string) $request->response_body);
    }

    public function test_it_respects_the_capture_toggle(): void
    {
        $this->configure(['enabled' => false]);

        $this->getJson('/api/orders')->assertOk();

        $this->assertDatabaseCount('api_guard_requests', 0);
    }

    public function test_it_respects_only_and_ignore_path_rules(): void
    {
        $this->configure([
            'only_paths' => ['api/orders*'],
            'ignore_paths' => ['api/orders/secret*'],
        ]);

        $this->getJson('/api/orders')->assertOk();
        $this->getJson('/api/ip')->assertOk();

        $this->assertDatabaseCount('api_guard_requests', 1);
        $this->assertSame('/api/orders', ApiRequest::query()->firstOrFail()->path);
    }

    public function test_it_respects_method_filters(): void
    {
        $this->configure(['methods' => ['POST']]);

        $this->getJson('/api/orders')->assertOk();
        $this->postJson('/api/echo', ['ok' => true])->assertOk();

        $this->assertDatabaseCount('api_guard_requests', 1);
        $this->assertSame('POST', ApiRequest::query()->firstOrFail()->method);
    }

    public function test_it_does_not_capture_its_own_traffic_or_replays(): void
    {
        $this->get('/livewire/update');
        $this->get('/livewire-abc123/update');
        $this->get('/api-guard/anything')->assertNotFound();
        $this->getJson('/api/orders', ['X-Api-Guard-Replay' => 'test'])->assertOk();

        $this->assertDatabaseCount('api_guard_requests', 0);
    }

    public function test_it_can_disable_individual_capture_fields(): void
    {
        $this->configure([
            'capture' => ['method', 'uri', 'status', 'timestamp'],
        ]);

        $this->postJson('/api/orders', ['secret' => 'value'])->assertCreated();

        $request = ApiRequest::query()->firstOrFail();

        $this->assertSame('POST', $request->method);
        $this->assertSame(201, $request->status_code);
        $this->assertNull($request->request_payload);
        $this->assertNull($request->request_headers);
        $this->assertNull($request->response_body);
        $this->assertNull($request->user_id);
        $this->assertNull($request->ip);
    }

    public function test_it_truncates_oversized_payloads(): void
    {
        $this->configure([
            'max_payload_kb' => 1,
        ]);

        $this->post('/api/echo', [
            'blob' => str_repeat('a', 4000),
        ])->assertOk();

        $request = ApiRequest::query()->firstOrFail();

        $this->assertTrue($request->payload_truncated);
        $this->assertStringContainsString('truncated by API Guard', (string) $request->request_payload);
        $this->assertLessThan(1500, strlen((string) $request->request_payload));
    }

    public function test_it_captures_exceptions(): void
    {
        $this->getJson('/api/explode')->assertStatus(500);

        $request = ApiRequest::query()->firstOrFail();

        $this->assertSame(500, $request->status_code);
        $this->assertSame(\RuntimeException::class, $request->exception_class);
        $this->assertStringContainsString('Kaboom', (string) $request->exception_message);
        $this->assertNotEmpty($request->exception_trace);
        $this->assertStringContainsString('api/explode', (string) $request->exception_trace);
    }

    public function test_it_skips_binary_response_bodies_but_keeps_metadata(): void
    {
        $this->get('/api/binary')->assertOk();

        $request = ApiRequest::query()->firstOrFail();

        $this->assertTrue($request->response_binary);
        $this->assertNull($request->response_body);
        $this->assertSame(200, $request->status_code);
    }

    public function test_it_can_skip_response_bodies_by_content_type(): void
    {
        $this->configure([
            'ignore_content_types' => ['application/json'],
        ]);

        $this->getJson('/api/orders')->assertOk();

        $request = ApiRequest::query()->firstOrFail();

        $this->assertTrue($request->response_binary);
        $this->assertNull($request->response_body);
        $this->assertSame(200, $request->status_code);
    }

    public function test_it_captures_ip_addresses_and_user_agents(): void
    {
        $this->withHeaders(['User-Agent' => 'ApiGuardTest/1.0'])
            ->getJson('/api/ip')
            ->assertOk();

        $request = ApiRequest::query()->firstOrFail();

        $this->assertSame('127.0.0.1', $request->ip);
        $this->assertSame('ApiGuardTest/1.0', $request->user_agent);
    }

    public function test_capture_fields_always_keep_the_core_fields(): void
    {
        $this->configure([
            'capture' => [],
        ]);

        $this->getJson('/api/orders')->assertOk();

        $request = ApiRequest::query()->firstOrFail();

        $this->assertSame('GET', $request->method);
        $this->assertSame(200, $request->status_code);
        $this->assertNotNull($request->uri);
        $this->assertNotNull($request->captured_at);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function configure(array $settings): void
    {
        app(Settings::class)->save($settings);
    }
}

