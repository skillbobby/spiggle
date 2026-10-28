<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spiggle\ApiGuard\Services\GuardRuleEngine;
use Symfony\Component\HttpFoundation\Response;

final class EnforceApiGuard
{
    public function __construct(private readonly GuardRuleEngine $engine)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $decision = $this->engine->evaluate($request);

        if ($decision === null || ($decision['action'] ?? null) === 'allowed') {
            return $next($request);
        }

        $rule = $decision['rule'];
        $status = (int) ($decision['status'] ?? 403);
        $headers = $decision['headers'] ?? [];

        $request->attributes->set('api_guard_decision', $decision);
        $request->attributes->set('api_guard_action', $decision['action']);
        $request->attributes->set('api_guard_rule_id', $rule?->getKey());
        $request->attributes->set('api_guard_rule_name', $rule?->name);

        $rule?->recordHit();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $decision['message'] ?? 'Request blocked by API Guard.',
                'action' => $decision['action'],
                'rule' => $rule?->name,
            ], $status, $headers);
        }

        return response((string) ($decision['message'] ?? 'Request blocked by API Guard.'), $status, $headers);
    }
}
