<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spiggle\ApiGuard\Http\CaptureState;
use Spiggle\ApiGuard\Http\RequestCapturer;
use Spiggle\ApiGuard\Http\RequestFilter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CaptureApiRequest
{
    public function __construct(
        private readonly RequestFilter $filter,
        private readonly RequestCapturer $capturer,
        private readonly CaptureState $state,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->state->begin($request, $this->filter->shouldCapture($request));

        if (! $this->state->isCapturing($request)) {
            return $next($request);
        }

        try {
            return $next($request);
        } catch (Throwable $exception) {
            $this->state->recordException($request, $exception);

            throw $exception;
        } finally {
            $this->state->stopTiming($request);
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        $state = $this->state->pull($request);

        if ($state === null) {
            return;
        }

        $this->capturer->capture(
            $request,
            $response,
            $state['duration_ms'],
            $state['exception'],
            $state['captured_at'],
        );
    }
}
