<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spiggle\ApiGuard\Enums\ReplayMode;

/**
 * @property string $id
 * @property string|null $api_request_id
 * @property string $mode
 * @property string $method
 * @property string $uri
 * @property string|null $host
 * @property array<string, mixed>|null $request_headers
 * @property string|null $request_payload
 * @property string|null $payload_type
 * @property int|null $status_code
 * @property float|null $duration_ms
 * @property array<string, mixed>|null $response_headers
 * @property string|null $response_body
 * @property bool $response_truncated
 * @property array<string, mixed>|null $plan
 * @property string|null $error
 * @property string|null $user_id
 * @property string|null $user_label
 * @property bool $rolled_back
 * @property \Illuminate\Support\Carbon $created_at
 */
class ApiReplay extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'plan' => 'array',
        'status_code' => 'integer',
        'duration_ms' => 'float',
        'response_truncated' => 'boolean',
        'response_truncated' => 'boolean',
        'rolled_back' => 'boolean',
    ];

    public function getTable(): string
    {
        return (string) config('api-guard.database.replays_table', 'api_guard_replays');
    }

    public function getConnectionName(): ?string
    {
        return config('api-guard.database.connection');
    }

    public function apiRequest(): BelongsTo
    {
        return $this->belongsTo(ApiRequest::class, 'api_request_id');
    }

    public function modeEnum(): ReplayMode
    {
        return ReplayMode::tryFrom($this->mode) ?? ReplayMode::Simulated;
    }

    public function statusColor(): string
    {
        return match (true) {
            $this->error !== null => 'danger',
            $this->status_code === null => 'gray',
            $this->status_code >= 500 => 'danger',
            $this->status_code >= 400 => 'warning',
            $this->status_code >= 300 => 'info',
            $this->status_code >= 200 => 'success',
            default => 'gray',
        };
    }

    public function durationForHumans(): string
    {
        if ($this->duration_ms === null) {
            return '—';
        }

        return $this->duration_ms < 1000
            ? number_format($this->duration_ms, 1) . ' ms'
            : number_format($this->duration_ms / 1000, 2) . ' s';
    }

    public function decodedResponse(): mixed
    {
        if ($this->response_body === null || $this->response_body === '') {
            return null;
        }

        $decoded = json_decode($this->response_body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->response_body;
    }

    /**
     * @return list<array{label: string, status: string, detail: string}>
     */
    public function planSteps(): array
    {
        $steps = $this->plan['steps'] ?? [];

        return is_array($steps) ? $steps : [];
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = $this->plan['warnings'] ?? [];

        return is_array($warnings) ? array_values(array_map('strval', $warnings)) : [];
    }
}
