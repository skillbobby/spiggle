<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spiggle\ApiGuard\Enums\CaptureField;
use Spiggle\ApiGuard\Models\GuardSetting;
use Throwable;

final class Settings
{
    public const RECORD_KEY = 'default';

    public const CACHE_KEY = 'api-guard.settings.v1';

    /**
     * The full, merged settings array (config defaults + UI overrides).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->readStored());

            return $this->merge((array) config('api-guard', []), $stored);
        } catch (Throwable $exception) {
            $this->logFailure($exception);

            return (array) config('api-guard', []);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->get('enabled', true);
    }

    public function capture(): array
    {
        return CaptureField::normalize((array) $this->get('capture', []));
    }

    public function captures(string|CaptureField $field): bool
    {
        $value = $field instanceof CaptureField ? $field->value : $field;

        return in_array($value, $this->capture(), true);
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $method): string => strtoupper(trim((string) $method)),
            (array) $this->get('methods', []),
        )));
    }

    /**
     * @return list<string>
     */
    public function ignorePaths(): array
    {
        return $this->stringList($this->get('ignore_paths', []));
    }

    /**
     * @return list<string>
     */
    public function onlyPaths(): array
    {
        return $this->stringList($this->get('only_paths', []));
    }

    /**
     * @return list<string>
     */
    public function ignoreContentTypes(): array
    {
        return $this->stringList($this->get('ignore_content_types', []));
    }

    /**
     * @return list<string>
     */
    public function redactHeaders(): array
    {
        return $this->stringList($this->get('redact_headers', []));
    }

    /**
     * @return list<string>
     */
    public function redactKeys(): array
    {
        return $this->stringList($this->get('redact_keys', []));
    }

    public function sampleRate(): int
    {
        return max(1, min(100, (int) $this->get('sample_rate', 100)));
    }

    public function maxPayloadBytes(): int
    {
        return max(1, (int) $this->get('max_payload_kb', 64)) * 1024;
    }

    public function maxResponseBytes(): int
    {
        return max(1, (int) $this->get('max_response_kb', 128)) * 1024;
    }

    public function maxExceptionBytes(): int
    {
        return max(1, (int) $this->get('max_exception_kb', 32)) * 1024;
    }

    public function guardEnabled(): bool
    {
        return (bool) $this->get('guard.enabled', true);
    }

    public function liveEnabled(): bool
    {
        return (bool) $this->get('live.enabled', true);
    }

    public function liveInterval(): int
    {
        return max(2, min(60, (int) $this->get('live.interval', 5)));
    }

    public function replayEnabled(): bool
    {
        return (bool) $this->get('replay.enabled', true);
    }

    public function liveReplayEnabled(): bool
    {
        return $this->replayEnabled() && (bool) $this->get('replay.allow_live', true);
    }

    /**
     * @return list<string>
     */
    public function replayMethods(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $method): string => strtoupper(trim((string) $method)),
            (array) $this->get('replay.allowed_methods', []),
        )));
    }

    public function rollbackByDefault(): bool
    {
        return (bool) $this->get('replay.rollback_by_default', true);
    }

    public function retentionDays(): int
    {
        return max(0, (int) $this->get('retention_days', 14));
    }

    public function maxRecords(): int
    {
        return max(0, (int) $this->get('max_records', 25000));
    }

    public function userLabelAttribute(): string
    {
        return (string) $this->get('user_label_attribute', 'email');
    }

    /**
     * Values displayed and edited on the settings page.
     *
     * @return array<string, mixed>
     */
    public function formData(): array
    {
        $all = $this->all();

        return [
            'enabled' => (bool) ($all['enabled'] ?? true),
            'capture' => $this->capture(),
            'only_paths' => $this->stringList($all['only_paths'] ?? []),
            'ignore_paths' => $this->stringList($all['ignore_paths'] ?? []),
            'methods' => $this->methods(),
            'sample_rate' => $this->sampleRate(),
            'ignore_content_types' => $this->ignoreContentTypes(),
            'max_payload_kb' => (int) ($all['max_payload_kb'] ?? 64),
            'max_response_kb' => (int) ($all['max_response_kb'] ?? 128),
            'redact_headers' => $this->redactHeaders(),
            'redact_keys' => $this->redactKeys(),
            'retention_days' => $this->retentionDays(),
            'max_records' => $this->maxRecords(),
            'live' => [
                'enabled' => $this->liveEnabled(),
                'interval' => $this->liveInterval(),
                'notify_on_new' => (bool) data_get($all, 'live.notify_on_new', true),
                'notify_on_error' => (bool) data_get($all, 'live.notify_on_error', true),
            ],
            'guard' => [
                'enabled' => $this->guardEnabled(),
            ],
            'replay' => [
                'enabled' => $this->replayEnabled(),
                'allow_live' => $this->liveReplayEnabled(),
                'allowed_methods' => $this->replayMethods(),
                'rollback_by_default' => $this->rollbackByDefault(),
            ],
        ];
    }

    /**
     * Persist UI settings. Unknown keys are discarded.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        $sanitized = $this->merge($this->readStored(), $this->sanitize($values));

        GuardSetting::query()->updateOrCreate(
            ['key' => self::RECORD_KEY],
            ['payload' => $sanitized],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function reset(): void
    {
        GuardSetting::query()->whereKey(self::RECORD_KEY)->delete();

        Cache::forget(self::CACHE_KEY);
    }

    public function storageReady(): bool
    {
        try {
            GuardSetting::query()->limit(1)->get();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readStored(): array
    {
        try {
            $record = GuardSetting::query()->find(self::RECORD_KEY);

            return is_array($record?->payload) ? $record->payload : [];
        } catch (Throwable $exception) {
            $this->logFailure($exception);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $existing = $base[$key] ?? null;

            if (
                is_array($value)
                && is_array($existing)
                && ! array_is_list($value)
                && ! array_is_list($existing)
            ) {
                $base[$key] = $this->merge($existing, $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }


    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $sanitized = [];
        $allowed = [
            'enabled',
            'capture',
            'only_paths',
            'ignore_paths',
            'methods',
            'sample_rate',
            'ignore_content_types',
            'max_payload_kb',
            'max_response_kb',
            'redact_headers',
            'redact_keys',
            'retention_days',
            'max_records',
            'live',
            'guard',
            'replay',
        ];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];

            $sanitized[$key] = match ($key) {
                'enabled' => (bool) $value,
                'capture' => CaptureField::normalize((array) $value),
                'only_paths', 'ignore_paths', 'ignore_content_types', 'redact_headers', 'redact_keys' => $this->stringList($value),
                'methods' => $this->httpMethods($value),
                'sample_rate' => max(1, min(100, (int) $value)),
                'max_payload_kb' => max(1, min(4096, (int) $value)),
                'max_response_kb' => max(1, min(4096, (int) $value)),
                'retention_days' => max(0, min(3650, (int) $value)),
                'max_records' => max(0, min(1000000, (int) $value)),
                'guard' => [
                    'enabled' => (bool) data_get($value, 'enabled', true),
                ],
                'live' => [
                    'enabled' => (bool) data_get($value, 'enabled', true),
                    'interval' => max(2, min(60, (int) data_get($value, 'interval', 5))),
                    'notify_on_new' => (bool) data_get($value, 'notify_on_new', true),
                    'notify_on_error' => (bool) data_get($value, 'notify_on_error', true),
                ],
                'replay' => [
                    'enabled' => (bool) data_get($value, 'enabled', true),
                    'allow_live' => (bool) data_get($value, 'allow_live', true),
                    'allowed_methods' => $this->httpMethods(data_get($value, 'allowed_methods', [])),
                    'rollback_by_default' => (bool) data_get($value, 'rollback_by_default', true),
                ],
                default => $value,
            };
        }

        return $sanitized;
    }

    /**
     * @return list<string>
     */
    private function httpMethods(mixed $value): array
    {
        $valid = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

        return array_values(array_unique(array_intersect(
            $valid,
            array_map(
                static fn (mixed $method): string => strtoupper(trim((string) $method)),
                (array) $value,
            ),
        )));
    }

    /**
     * Accept arrays or newline/comma separated strings (handy for tests and
     * programmatic saves).
     *
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $item = trim((string) $item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private function logFailure(Throwable $exception): void
    {
        if (! config('api-guard.log_failures', false)) {
            return;
        }

        Log::warning('API Guard could not read its settings.', [
            'exception' => $exception,
        ]);
    }
}

