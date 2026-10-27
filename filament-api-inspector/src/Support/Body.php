<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Support;

use Illuminate\Support\Str;

final class Body
{
    public static function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        return $encoded === false ? null : $encoded;
    }

    public static function decode(?string $body): mixed
    {
        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }

    public static function pretty(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return $value;
            }
        }

        if ($value === null) {
            return '';
        }

        $encoded = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        return $encoded === false ? '' : $encoded;
    }

    public static function truncate(?string $value, int $maxBytes, ?bool &$truncated = null): ?string
    {
        $truncated = false;

        if ($value === null || $maxBytes <= 0) {
            return $value;
        }

        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        $truncated = true;

        return mb_strcut($value, 0, $maxBytes, 'UTF-8') . "\n… [truncated by API Inspector]";
    }

    public static function isBinary(?string $contentType, ?string $content = null): bool
    {
        if ($content !== null && str_contains(mb_substr($content, 0, 512, 'UTF-8'), "\0")) {
            return true;
        }

        if ($contentType === null) {
            return false;
        }

        $type = strtolower(trim(explode(';', $contentType)[0]));

        return ! (
            $type === ''
            || str_starts_with($type, 'text/')
            || str_contains($type, 'json')
            || str_contains($type, 'xml')
            || str_contains($type, 'javascript')
            || str_contains($type, 'x-www-form-urlencoded')
            || str_contains($type, 'graphql')
        );
    }

    /**
     * @param  array<int, string>  $patterns
     */
    public static function contentTypeMatches(?string $contentType, array $patterns): bool
    {
        if ($contentType === null || $contentType === '') {
            return false;
        }

        $contentType = strtolower(trim(explode(';', $contentType)[0]));

        foreach ($patterns as $pattern) {
            if (Str::is(strtolower(trim((string) $pattern)), $contentType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flatten a header bag into "Name: value" strings for display and cURL.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    public static function flattenHeaders(array $headers): array
    {
        $flat = [];

        foreach ($headers as $name => $values) {
            $values = is_array($values) ? $values : [$values];

            $flat[(string) $name] = implode(', ', array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : self::pretty($value),
                $values,
            ));
        }

        return $flat;
    }
}
