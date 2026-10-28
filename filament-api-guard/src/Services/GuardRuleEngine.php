<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Spiggle\ApiGuard\Enums\GuardRuleType;
use Spiggle\ApiGuard\Http\ReplayContext;
use Spiggle\ApiGuard\Models\GuardRule;
use Spiggle\ApiGuard\Support\Settings;
use Throwable;

final class GuardRuleEngine
{
    public const CACHE_KEY = 'api-guard.rules.v1';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('guard.enabled', config('api-guard.guard.enabled', true));
    }

    /**
     * @return Collection<int, GuardRule>
     */
    public function rules(): Collection
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn (): Collection => GuardRule::query()
                ->where('enabled', true)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get());
        } catch (Throwable) {
            return collect();
        }
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function shouldSkip(Request $request): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (ReplayContext::isRunning() && (bool) config('api-guard.guard.bypass_replays', true)) {
            return true;
        }

        $path = ltrim($request->path(), '/');
        $patterns = array_values(array_filter(array_map(
            static fn (mixed $pattern): string => ltrim((string) $pattern, '/'),
            (array) config('api-guard.guard.ignore_paths', []),
        )));

        return $patterns !== [] && Str::is($patterns, $path);
    }

    /**
     * Evaluate the active guard rules. In dry-run mode no rate limiter hits or
     * database counters are written; this powers replay plans.
     *
     * @return array{action: string, rule: GuardRule|null, status: int|null, message: string|null, headers: array<string, string>, remaining: int|null, retry_after: int|null}|null
     */
    public function evaluate(Request $request, bool $dryRun = false, bool $ignoreReplayContext = false): ?array
    {
        if (! $ignoreReplayContext && $this->shouldSkip($request)) {
            return null;
        }

        if ($ignoreReplayContext && ! $this->enabled()) {
            return null;
        }

        $matching = $this->rules()->filter(fn (GuardRule $rule): bool => $rule->matches($request));

        if ($matching->isEmpty()) {
            return null;
        }

        foreach ($matching as $rule) {
            if ($rule->typeEnum() === GuardRuleType::Block) {
                return [
                    'action' => 'blocked',
                    'rule' => $rule,
                    'status' => $rule->responseStatus(),
                    'message' => $rule->responseMessage(),
                    'headers' => $this->headers($rule, 'blocked'),
                    'remaining' => null,
                    'retry_after' => null,
                ];
            }
        }

        foreach ($matching as $rule) {
            if ($rule->typeEnum() !== GuardRuleType::Throttle || ! $rule->matches($request)) {
                continue;
            }

            $key = $rule->throttleKeyFor($request);
            $limit = $rule->throttleLimit();

            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $retryAfter = RateLimiter::availableIn($key);

                return [
                    'action' => 'throttled',
                    'rule' => $rule,
                    'status' => $rule->responseStatus(),
                    'message' => $rule->responseMessage(),
                    'headers' => $this->headers($rule, 'throttled', $limit, 0, $retryAfter),
                    'remaining' => 0,
                    'retry_after' => $retryAfter,
                ];
            }

            if (! $dryRun) {
                RateLimiter::hit($key, $rule->throttleWindow());
            }

            $remaining = max(0, RateLimiter::remaining($key, $limit));

            if ($dryRun) {
                return [
                    'action' => 'allowed',
                    'rule' => $rule,
                    'status' => null,
                    'message' => null,
                    'headers' => $this->headers($rule, 'allowed', $limit, $remaining, null),
                    'remaining' => $remaining,
                    'retry_after' => null,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(
        GuardRule $rule,
        string $action,
        int $limit = 0,
        int $remaining = 0,
        ?int $retryAfter = null,
    ): array {
        $headers = [
            (string) config('api-guard.guard.headers.action', 'X-API-Guard-Action') => $action,
            (string) config('api-guard.guard.headers.rule', 'X-API-Guard-Rule') => (string) $rule->getKey(),
        ];

        if ($limit > 0) {
            $headers['X-RateLimit-Limit'] = (string) $limit;
            $headers['X-RateLimit-Remaining'] = (string) max(0, $remaining);
        }

        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) max(1, $retryAfter);
        }

        return $headers;
    }
}
