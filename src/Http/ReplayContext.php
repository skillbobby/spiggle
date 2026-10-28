<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Http;

/**
 * Tracks whether the current PHP process is executing an internal replay.
 *
 * The HTTP kernel re-enters the global middleware stack when a replay is
 * dispatched, so both the marker header and this counter guard against
 * recording a replay as a brand new request.
 */
final class ReplayContext
{
    private static int $depth = 0;

    public static function run(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth = max(0, self::$depth - 1);
        }
    }

    public static function isRunning(): bool
    {
        return self::$depth > 0;
    }
}
