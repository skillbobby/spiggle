<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spiggle\ApiInspector\Enums\ReplayMode;
use Spiggle\ApiInspector\Models\ApiReplay;
use Spiggle\ApiInspector\Models\ApiRequest;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'ada@example.com'],
            [
                'name' => 'Ada Lovelace',
                'password' => Hash::make('password'),
            ],
        );

        ApiReplay::query()->delete();
        ApiRequest::query()->delete();

        $samples = [
            ['GET', '/api/orders', 200, 42.8, ['include' => 'items'], null, ['data' => [['id' => 1], ['id' => 2]], 'meta' => ['count' => 2]], 2],
            ['POST', '/api/orders', 201, 86.4, [], ['customer_id' => 42, 'total' => 1999], ['id' => 1044, 'status' => 'created'], 8],
            ['GET', '/api/orders/1042', 200, 18.2, [], null, ['id' => 1042, 'status' => 'paid', 'items' => [['sku' => 'SPG-1', 'qty' => 2]]], 14],
            ['GET', '/api/reports/revenue', 200, 312.7, ['period' => '2026-10'], null, ['currency' => 'USD', 'revenue' => 184250], 21],
            ['POST', '/api/orders', 422, 14.1, [], ['customer_id' => null, 'total' => -1], ['message' => 'The given data was invalid.', 'errors' => ['total' => ['The total must be positive.']]], 37],
            ['GET', '/api/orders', 500, 1241.9, [], null, ['message' => 'Server Error'], 53],
            ['DELETE', '/api/orders/1041', 204, 55.5, [], null, null, 68],
            ['PATCH', '/api/orders/1040', 200, 74.3, [], ['status' => 'shipped'], ['id' => 1040, 'status' => 'shipped'], 84],
            ['GET', '/api/orders/1039', 404, 11.8, [], null, ['message' => 'Not Found'], 101],
            ['POST', '/api/webhooks/stripe', 200, 138.6, [], ['type' => 'payment_intent.succeeded', 'data' => ['amount' => 12900]], ['received' => true], 127],
            ['GET', '/api/orders', 200, 38.9, ['page' => 2], null, ['data' => [['id' => 3]], 'meta' => ['count' => 1]], 149],
            ['PUT', '/api/orders/1038', 200, 92.2, [], ['status' => 'cancelled'], ['id' => 1038, 'status' => 'cancelled'], 176],
            ['GET', '/api/reports/revenue', 200, 287.4, ['period' => '2026-09'], null, ['currency' => 'USD', 'revenue' => 172900], 211],
            ['POST', '/api/orders', 201, 67.3, [], ['customer_id' => 77, 'total' => 5600], ['id' => 1045, 'status' => 'created'], 246],
            ['GET', '/api/orders/1042', 200, 16.9, [], null, ['id' => 1042, 'status' => 'paid'], 302],
            ['GET', '/api/health', 200, 2.4, [], null, ['ok' => true], 358],
            ['POST', '/api/webhooks/stripe', 500, 2013.4, [], ['type' => 'charge.failed'], ['message' => 'Webhook handler failed'], 411],
            ['GET', '/api/orders/1031', 429, 3.2, [], null, ['message' => 'Too many requests'], 455],
            ['PATCH', '/api/orders/1030', 200, 81.1, [], ['note' => 'Express delivery'], ['id' => 1030, 'note' => 'Express delivery'], 502],
            ['GET', '/api/reports/revenue', 200, 301.8, ['period' => '2026-08'], null, ['currency' => 'USD', 'revenue' => 160400], 564],
        ];

        $created = [];

        foreach ($samples as $index => [$method, $path, $status, $duration, $query, $payload, $response, $minutesAgo]) {
            $capturedAt = Carbon::now()->subMinutes($minutesAgo);
            $responseBody = $response === null ? null : json_encode($response, JSON_UNESCAPED_SLASHES);
            $statusText = match ($status) {
                200 => 'OK',
                201 => 'Created',
                204 => 'No Content',
                404 => 'Not Found',
                422 => 'Unprocessable Content',
                429 => 'Too Many Requests',
                default => 'Internal Server Error',
            };
            $hasUser = $index % 4 !== 0;

            $created[] = ApiRequest::query()->create([
                'method' => $method,
                'scheme' => 'https',
                'host' => 'app.spiggle.test',
                'path' => $path,
                'uri' => 'https://app.spiggle.test' . $path . ($query ? '?' . http_build_query($query) : ''),
                'route_name' => 'api.' . trim(str_replace(['/api/', '/'], ['', '.'], $path), '.'),
                'controller_action' => 'App\\Http\\Controllers\\Api\\Controller@handle',
                'route_middleware' => ['api', 'auth:sanctum', 'throttle:api'],
                'status_code' => $status,
                'status_text' => $statusText,
                'duration_ms' => $duration,
                'memory_bytes' => random_int(8, 42) * 1024 * 1024,
                'request_size' => $payload === null ? 0 : strlen((string) json_encode($payload)),
                'response_size' => $responseBody === null ? 0 : strlen($responseBody),
                'ip' => '203.0.113.' . random_int(10, 240),
                'user_agent' => 'Spiggle-Workbench/1.0 (api-client)',
                'user_id' => $hasUser ? (string) $user->id : null,
                'user_type' => $hasUser ? $user::class : null,
                'user_label' => $hasUser ? 'ada@example.com' : null,
                'request_headers' => [
                    'accept' => ['application/json'],
                    'authorization' => ['[redacted]'],
                    'user-agent' => ['Spiggle-Workbench/1.0 (api-client)'],
                ],
                'query' => $query ?: null,
                'request_payload' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES),
                'payload_type' => $payload === null ? null : 'json',
                'response_headers' => [
                    'content-type' => ['application/json'],
                    'cache-control' => ['no-cache, private'],
                ],
                'response_body' => $responseBody,
                'exception_class' => $status === 500 ? 'RuntimeException' : null,
                'exception_message' => $status === 500 ? 'Server exploded while processing the request.' : null,
                'exception_trace' => $status === 500 ? "#0 /app/Http/Controllers/Api/Controller.php(84): handle()\n#1 {main}" : null,
                'is_replay' => false,
                'replay_count' => 0,
                'captured_at' => $capturedAt,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ]);
        }

        $this->seedReplay($created[0], 'ada@example.com');
    }

    private function seedReplay(ApiRequest $request, string $label): void
    {
        ApiReplay::query()->create([
            'api_request_id' => $request->id,
            'mode' => ReplayMode::Simulated->value,
            'method' => 'GET',
            'uri' => $request->uri,
            'host' => $request->host,
            'request_headers' => ['Accept' => 'application/json'],
            'status_code' => null,
            'plan' => [
                'target' => ['method' => 'GET', 'uri' => $request->uri, 'host' => $request->host, 'path' => $request->path],
                'warnings' => [],
                'blockers' => [],
                'steps' => [
                    ['label' => 'Target request', 'status' => 'info', 'detail' => 'GET ' . $request->uri],
                    ['label' => 'Route resolution', 'status' => 'passed', 'detail' => 'api.orders.index'],
                    ['label' => 'Authentication', 'status' => 'passed', 'detail' => 'Replays as ada@example.com'],
                    ['label' => 'Side effects', 'status' => 'passed', 'detail' => 'Read-only request'],
                    ['label' => 'Execution', 'status' => 'passed', 'detail' => 'Ready to send through your application'],
                ],
            ],
            'user_label' => $label,
            'rolled_back' => false,
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        $request->forceFill([
            'replay_count' => 1,
            'last_replayed_at' => now()->subMinutes(3),
        ])->save();
    }
}

