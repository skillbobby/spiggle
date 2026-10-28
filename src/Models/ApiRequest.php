<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spiggle\ApiGuard\Enums\CaptureField;

/**
 * @property string $id
 * @property string $method
 * @property string|null $scheme
 * @property string|null $host
 * @property string|null $path
 * @property string|null $uri
 * @property string|null $route_name
 * @property string|null $controller_action
 * @property array<int, string>|null $route_middleware
 * @property int|null $status_code
 * @property string|null $status_text
 * @property string|null $guard_action
 * @property string|null $guard_rule_id
 * @property string|null $guard_rule_name
 * @property float|null $duration_ms
 * @property int|null $memory_bytes
 * @property int|null $request_size
 * @property int|null $response_size
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $referer
 * @property string|null $user_id
 * @property string|null $user_type
 * @property string|null $user_label
 * @property string|null $session_id
 * @property array<string, mixed>|null $request_headers
 * @property array<string, mixed>|null $query
 * @property string|null $request_payload
 * @property string|null $payload_type
 * @property bool $payload_truncated
 * @property array<string, mixed>|null $response_headers
 * @property string|null $response_body
 * @property bool $response_truncated
 * @property bool $response_binary
 * @property string|null $exception_class
 * @property string|null $exception_message
 * @property string|null $exception_trace
 * @property bool $is_replay
 * @property string|null $replayed_from_id
 * @property int $replay_count
 * @property \Illuminate\Support\Carbon|null $last_replayed_at
 * @property \Illuminate\Support\Carbon $captured_at
 */
class ApiRequest extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'status_code' => 'integer',
        'duration_ms' => 'float',
        'memory_bytes' => 'integer',
        'request_size' => 'integer',
        'response_size' => 'integer',
        'request_headers' => 'array',
        'query' => 'array',
        'payload_truncated' => 'boolean',
        'response_headers' => 'array',
        'route_middleware' => 'array',
        'response_truncated' => 'boolean',
        'response_binary' => 'boolean',
        'is_replay' => 'boolean',
        'replay_count' => 'integer',
        'captured_at' => 'datetime',
        'last_replayed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('api-guard.database.requests_table', 'api_guard_requests');
    }

    public function getConnectionName(): ?string
    {
        return config('api-guard.database.connection');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function guardRule(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GuardRule::class, 'guard_rule_id');
    }

    public function replays(): HasMany
    {
        return $this->hasMany(ApiReplay::class, 'api_request_id')->latest();
    }

    public function latestReplay(): HasMany
    {
        return $this->replays()->limit(1);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('captured_at')->orderByDesc('id');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 500);
    }

    public function scopeClientErrors(Builder $query): Builder
    {
        return $query->whereBetween('status_code', [400, 499]);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereBetween('status_code', [200, 399]);
    }

    public function scopeSlowerThan(Builder $query, float $milliseconds): Builder
    {
        return $query->where('duration_ms', '>=', $milliseconds);
    }

    public function scopeReplayed(Builder $query): Builder
    {
        return $query->where('replay_count', '>', 0);
    }

    public function scopeCapturedBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('captured_at', [$from, $to]);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';

        return $query->where(function (Builder $query) use ($term): void {
            $query
                ->where('uri', 'like', $term)
                ->orWhere('path', 'like', $term)
                ->orWhere('route_name', 'like', $term)
                ->orWhere('controller_action', 'like', $term)
                ->orWhere('user_label', 'like', $term)
                ->orWhere('ip', 'like', $term);
        });
    }

    public function isBlocked(): bool
    {
        return $this->guard_action === 'blocked';
    }

    public function isThrottled(): bool
    {
        return $this->guard_action === 'throttled';
    }

    public function guardLabel(): ?string
    {
        return match ($this->guard_action) {
            'blocked' => 'Blocked',
            'throttled' => 'Throttled',
            default => null,
        };
    }

    public function guardColor(): string
    {
        return $this->guard_action === 'blocked' ? 'danger' : 'warning';
    }

    public function isSuccessful(): bool
    {
        return $this->status_code !== null && $this->status_code >= 200 && $this->status_code < 400;
    }

    public function isFailed(): bool
    {
        return $this->status_code !== null && $this->status_code >= 500;
    }

    public function statusColor(): string
    {
        return match (true) {
            $this->status_code === null => 'gray',
            $this->status_code >= 500 => 'danger',
            $this->status_code >= 400 => 'warning',
            $this->status_code >= 300 => 'info',
            $this->status_code >= 200 => 'success',
            default => 'gray',
        };
    }

    public function statusFamily(): string
    {
        return match (true) {
            $this->status_code === null => 'unknown',
            $this->status_code >= 500 => 'server_error',
            $this->status_code >= 400 => 'client_error',
            $this->status_code >= 300 => 'redirect',
            $this->status_code >= 200 => 'success',
            default => 'informational',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->statusFamily()) {
            'success' => 'Success',
            'redirect' => 'Redirect',
            'client_error' => 'Client error',
            'server_error' => 'Server error',
            'informational' => 'Info',
            default => 'Pending',
        };
    }

    public function methodColor(): string
    {
        return match (strtoupper((string) $this->method)) {
            'GET', 'HEAD' => 'success',
            'POST' => 'info',
            'PUT' => 'warning',
            'PATCH' => 'primary',
            'DELETE' => 'danger',
            default => 'gray',
        };
    }

    public function durationForHumans(): string
    {
        if ($this->duration_ms === null) {
            return '—';
        }

        $milliseconds = (float) $this->duration_ms;

        if ($milliseconds < 1) {
            return '< 1 ms';
        }

        if ($milliseconds < 1000) {
            return number_format($milliseconds, $milliseconds < 10 ? 2 : 1) . ' ms';
        }

        return number_format($milliseconds / 1000, 2) . ' s';
    }

    public function sizeForHumans(?int $bytes = null): string
    {
        $bytes ??= $this->response_size;

        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }

    /**
     * The decoded payload when it is JSON, raw text otherwise.
     */
    public function decodedPayload(): mixed
    {
        return $this->decode($this->request_payload, $this->payload_type);
    }

    public function decodedResponse(): mixed
    {
        if ($this->response_body === null || $this->response_body === '') {
            return null;
        }

        $type = $this->response_headers['content-type'][0]
            ?? $this->response_headers['content-type']
            ?? null;

        if (is_array($type)) {
            $type = $type[0] ?? null;
        }

        $looksJson = is_string($this->response_body)
            && (str_starts_with(ltrim($this->response_body), '{') || str_starts_with(ltrim($this->response_body), '['));

        return $this->decode(
            $this->response_body,
            (is_string($type) && str_contains(strtolower($type), 'json')) || $looksJson ? 'json' : 'text',
        );
    }

    private function decode(?string $body, ?string $type): mixed
    {
        if ($body === null || $body === '') {
            return null;
        }

        if ($type === 'json' || $type === 'form' || $type === null) {
            $decoded = json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $body;
    }

    public function hasField(CaptureField $field): bool
    {
        return app(\Spiggle\ApiGuard\Support\Settings::class)->captures($field);
    }

    /**
     * A compact label for navigation / breadcrumbs.
     */
    public function summary(): string
    {
        $path = $this->path ?: (parse_url((string) $this->uri, PHP_URL_PATH) ?: '/');

        return strtoupper((string) $this->method) . ' ' . $path;
    }
}
