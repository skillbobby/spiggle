<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Enums;

enum GuardRuleType: string
{
    case Block = 'block';
    case Throttle = 'throttle';

    public function label(): string
    {
        return match ($this) {
            self::Block => 'Block',
            self::Throttle => 'Throttle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Block => 'Reject matching requests before they reach the application.',
            self::Throttle => 'Allow matching requests up to a limit, then return 429 responses.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Block => 'danger',
            self::Throttle => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Block => 'heroicon-o-no-symbol',
            self::Throttle => 'heroicon-o-clock',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
