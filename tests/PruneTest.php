<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Tests;

use Spiggle\ApiGuard\Models\ApiReplay;
use Spiggle\ApiGuard\Models\ApiRequest;
use Spiggle\ApiGuard\Services\Pruner;

final class PruneTest extends TestCase
{
    public function test_it_prunes_requests_older_than_the_retention_window(): void
    {
        $old = $this->makeRequest('/api/old', now()->subDays(30));
        $fresh = $this->makeRequest('/api/fresh', now()->subDay());

        $result = app(Pruner::class)->prune(days: 7, maxRecords: 0);

        $this->assertSame(1, $result['expired']);
        $this->assertSame(0, $result['overflow']);
        $this->assertNull(ApiRequest::query()->find($old->id));
        $this->assertNotNull(ApiRequest::query()->find($fresh->id));
    }

    public function test_it_caps_the_number_of_stored_requests(): void
    {
        $first = $this->makeRequest('/api/first', now()->subMinutes(3));
        $second = $this->makeRequest('/api/second', now()->subMinutes(2));
        $third = $this->makeRequest('/api/third', now()->subMinute());

        $result = app(Pruner::class)->prune(days: 0, maxRecords: 2);

        $this->assertSame(1, $result['overflow']);
        $this->assertNull(ApiRequest::query()->find($first->id));
        $this->assertNotNull(ApiRequest::query()->find($second->id));
        $this->assertNotNull(ApiRequest::query()->find($third->id));
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        $this->makeRequest('/api/old', now()->subDays(30));

        $result = app(Pruner::class)->prune(days: 7, maxRecords: 0, dryRun: true);

        $this->assertSame(1, $result['expired']);
        $this->assertDatabaseCount('api_guard_requests', 1);
    }

    public function test_it_removes_replay_history_for_pruned_requests(): void
    {
        $request = $this->makeRequest('/api/old', now()->subDays(30));

        ApiReplay::query()->create([
            'api_request_id' => $request->id,
            'mode' => 'simulated',
            'method' => 'GET',
            'uri' => 'http://localhost/api/old',
            'plan' => ['steps' => []],
        ]);

        app(Pruner::class)->prune(days: 7, maxRecords: 0);

        $this->assertDatabaseCount('api_guard_replays', 0);
    }

    public function test_the_prune_command_runs(): void
    {
        $this->makeRequest('/api/old', now()->subDays(30));

        $this->artisan('api-guard:prune', ['--days' => 7])
            ->expectsOutputToContain('Pruned 1 record')
            ->assertSuccessful();

        $this->assertDatabaseCount('api_guard_requests', 0);
    }

    private function makeRequest(string $path, \DateTimeInterface $capturedAt): ApiRequest
    {
        return ApiRequest::query()->create([
            'method' => 'GET',
            'scheme' => 'http',
            'host' => 'localhost',
            'path' => $path,
            'uri' => 'http://localhost' . $path,
            'status_code' => 200,
            'status_text' => 'OK',
            'duration_ms' => 12.5,
            'ip' => '127.0.0.1',
            'captured_at' => $capturedAt,
        ]);
    }
}
