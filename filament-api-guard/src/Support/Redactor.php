<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Support;

use Illuminate\Support\Str;

final class Redactor
{
    public const PLACEHOLDER = '[redacted]';

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<int, string>  $patterns
     * @return array<string, mixed>
     */
    public static function headers(array $headers, array $patterns): array
    {
        $clean = [];

        foreach ($headers as $name => $values) {
            $clean[$name] = self::matches((string) $name, $patterns)
                ? self::redactHeaderValue($values)
                : $values;
        }

        return $clean;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    public static function matches(string $key, array $patterns): bool
    {
        $key = strtolower($key);

        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim((string) $pattern));

            if ($pattern === '') {
                continue;
            }

            if ($pattern === $key || Str::is($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively replace sensitive keys inside arrays. Keys are matched
     * case-insensitively and support wildcards ("*_token").
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<int, string>  $patterns
     * @return array<array-key, mixed>
     */
    public static function data(array $data, array $patterns): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && self::matches($key, $patterns)) {
                $clean[$key] = self::PLACEHOLDER;

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::data($value, $patterns);

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private static function redactHeaderValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (): string => self::PLACEHOLDER, $value);
        }

        return self::PLACEHOLDER;
    }
}
