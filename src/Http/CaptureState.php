<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;
use WeakMap;

/**
 * Request-scoped state shared between the middleware instance that handles a
 * request and the (separate) instance Laravel resolves for terminate().
 *
 * The WeakMap keeps state keyed by the request object and releases it as soon
 * as the request is garbage-collected, which is safe under Octane.
 */
final class CaptureState
{
    /** @var WeakMap<Request, array<string, mixed>> */
    private WeakMap $states;

    public function __construct()
    {
        $this->states = new WeakMap();
    }

    public function begin(Request $request, bool $capturing): void
    {
        if (! $capturing) {
            $this->states[$request] = ['capture' => false];

            return;
        }

        $this->states[$request] = [
            'capture' => true,
            'captured_at' => Carbon::now(),
            'started_at' => hrtime(true),
            'duration_ms' => 0.0,
            'exception' => null,
        ];
    }

    public function isCapturing(Request $request): bool
    {
        return (bool) ($this->states[$request]['capture'] ?? false);
    }

    public function recordException(Request $request, Throwable $exception): void
    {
        if (isset($this->states[$request])) {
            $state = $this->states[$request];
            $state['exception'] = $exception;
            $this->states[$request] = $state;
        }
    }

    public function stopTiming(Request $request): void
    {
        if (! isset($this->states[$request]) || ! $this->isCapturing($request)) {
            return;
        }

        $state = $this->states[$request];
        $start = $state['started_at'] ?? null;

        if (is_int($start)) {
            $state['duration_ms'] = (hrtime(true) - $start) / 1_000_000;
        }

        $this->states[$request] = $state;
    }

    /**
     * @return array{capture: bool, captured_at: Carbon, duration_ms: float, exception: Throwable|null}|null
     */
    public function pull(Request $request): ?array
    {
        if (! isset($this->states[$request])) {
            return null;
        }

        $state = $this->states[$request];
        unset($this->states[$request]);

        if (! ($state['capture'] ?? false)) {
            return null;
        }

        return [
            'capture' => true,
            'captured_at' => $state['captured_at'],
            'duration_ms' => (float) $state['duration_ms'],
            'exception' => $state['exception'],
        ];
    }
}
