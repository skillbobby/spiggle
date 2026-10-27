<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Enums;

enum ReplayMode: string
{
    case Simulated = 'simulated';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Simulated => 'Simulated',
            self::Live => 'Live',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Simulated => 'Resolve the route and show exactly what would happen. Nothing is executed.',
            self::Live => 'Send the request through your app again. Optionally roll back database writes.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Simulated => 'info',
            self::Live => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Simulated => 'heroicon-o-beaker',
            self::Live => 'heroicon-o-play',
        };
    }

    public function executesRequest(): bool
    {
        return $this === self::Live;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $mode) {
            $options[$mode->value] = $mode->label();
        }

        return $options;
    }
}
