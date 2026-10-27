<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class Access
{
    public static function user(): ?Authenticatable
    {
        try {
            if (Filament::getCurrentPanel() !== null) {
                $user = Filament::auth()->user();

                if ($user instanceof Authenticatable) {
                    return $user;
                }
            }
        } catch (\Throwable) {
            // Filament is not booted (console, tests, standalone usage).
        }

        $user = Auth::user();

        return $user instanceof Authenticatable ? $user : null;
    }

    public static function allowed(?Authenticatable $user = null): bool
    {
        $user ??= self::user();

        if ($user === null) {
            return false;
        }

        $gate = config('api-inspector.authorization.gate');

        if (is_string($gate) && $gate !== '' && Gate::forUser($user)->denies($gate)) {
            return false;
        }

        $emails = (array) config('api-inspector.authorization.emails', []);

        if ($emails !== []) {
            $email = strtolower((string) data_get($user, 'email', ''));

            $allowed = array_map(
                static fn (mixed $value): string => strtolower(trim((string) $value)),
                $emails,
            );

            return $email !== '' && in_array($email, $allowed, true);
        }

        return true;
    }
}
