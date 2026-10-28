<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spiggle\ApiGuard\Enums\GuardRuleType;
use Spiggle\ApiGuard\Services\GuardRuleEngine;

/**
 * @property string $id
 * @property string $name
 * @property string $type
 * @property bool $enabled
 * @property int $priority
 * @property string|null $path
 * @property array<int, string>|null $methods
 * @property string|null $ip
 * @property string|null $user_id
 * @property int|null $throttle_limit
 * @property int $throttle_window
 * @property string $throttle_key
 * @property int|null $response_status
 * @property string|null $response_message
 * @property int $hit_count
 * @property \Illuminate\Support\Carbon|null $last_hit_at
 */
class GuardRule extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
        'methods' => 'array',
        'throttle_limit' => 'integer',
        'throttle_window' => 'integer',
        'response_status' => 'integer',
        'hit_count' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('api-guard.database.rules_table', 'api_guard_rules');
    }

    public function getConnectionName(): ?string
    {
        return config('api-guard.database.connection');
    }

    public function typeEnum(): GuardRuleType
    {
        return GuardRuleType::tryFrom($this->type) ?? GuardRuleType::Block;
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeType(Builder $query, GuardRuleType $type): Builder
    {
        return $query->where('type', $type->value);
    }


    public function matches(Request $request): bool
    {
        if ($this->typeEnum() === GuardRuleType::Throttle && $this->throttle_limit === null) {
            return false;
        }

        $path = ltrim($request->path(), '/');

        if (filled($this->path)) {
            $pattern = ltrim((string) $this->path, '/');

            if (! Str::is($pattern, $path)) {
                return false;
            }
        }

        $methods = array_values(array_filter((array) ($this->methods ?? [])));

        if ($methods !== [] && ! in_array(strtoupper($request->method()), array_map('strtoupper', $methods), true)) {
            return false;
        }

        if (filled($this->ip) && ! $this->ipMatches($request->ip())) {
            return false;
        }

        if (filled($this->user_id) && (string) $request->user()?->getAuthIdentifier() !== (string) $this->user_id) {
            return false;
        }

        return true;
    }

    public function throttleKeyFor(Request $request): string
    {
        $identity = $this->throttle_key === 'user'
            ? (string) ($request->user()?->getAuthIdentifier() ?? $request->ip())
            : (string) $request->ip();

        return 'api-guard:rule:' . $this->getKey() . ':' . sha1($identity);
    }

    public function throttleLimit(): int
    {
        return max(1, (int) ($this->throttle_limit ?? 60));
    }

    public function throttleWindow(): int
    {
        return max(1, (int) ($this->throttle_window ?? 60));
    }

    public function responseStatus(): int
    {
        return (int) ($this->response_status ?: ($this->typeEnum() === GuardRuleType::Throttle
            ? config('api-guard.guard.throttle_status', 429)
            : config('api-guard.guard.block_status', 403)));
    }

    public function responseMessage(): string
    {
        return (string) ($this->response_message ?: ($this->typeEnum() === GuardRuleType::Throttle
            ? config('api-guard.guard.throttle_message', 'Too many requests. Please retry later.')
            : config('api-guard.guard.block_message', 'Request blocked by API Guard.')));
    }

    public function recordHit(): void
    {
        $this->newQuery()->whereKey($this->getKey())->update([
            'hit_count' => $this->hit_count + 1,
            'last_hit_at' => now(),
        ]);
    }

    public function summary(): string
    {
        $parts = [];

        if (filled($this->path)) {
            $parts[] = ltrim((string) $this->path, '/');
        }

        if (filled($this->methods)) {
            $parts[] = implode('|', (array) $this->methods);
        }

        if (filled($this->ip)) {
            $parts[] = 'IP ' . $this->ip;
        }

        if (filled($this->user_id)) {
            $parts[] = 'User ' . $this->user_id;
        }

        return $parts === [] ? 'Every request' : implode(' · ', $parts);
    }

    protected static function booted(): void
    {
        static::saved(fn (): mixed => Cache::forget(GuardRuleEngine::CACHE_KEY));
        static::deleted(fn (): mixed => Cache::forget(GuardRuleEngine::CACHE_KEY));
    }

    private function ipMatches(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        $rule = (string) $this->ip;

        if (str_contains($rule, '/')) {
            return $this->cidrContains($rule, $ip);
        }

        return Str::is($rule, $ip);
    }

    private function cidrContains(string $cidr, string $ip): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        if ($bits === null || ! is_numeric($bits)) {
            return false;
        }

        $subnetPacked = @inet_pton((string) $subnet);
        $ipPacked = @inet_pton($ip);

        if ($subnetPacked === false || $ipPacked === false || strlen($subnetPacked) !== strlen($ipPacked)) {
            return false;
        }

        $bits = max(0, min((int) $bits, strlen($subnetPacked) * 8));
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($subnetPacked, 0, $bytes) !== substr($ipPacked, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainder);

        return (ord($subnetPacked[$bytes]) & $mask) === (ord($ipPacked[$bytes]) & $mask);
    }
}
