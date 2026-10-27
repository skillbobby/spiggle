<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Capture can also be toggled from the API Inspector settings page without
    | touching this file. When disabled, the middleware exits immediately.
    |
    */
    'enabled' => env('API_INSPECTOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The capture middleware is pushed onto the global HTTP stack so every
    | route (panel, API and web) is observed. Disable this if you prefer to
    | register Spiggle\ApiInspector\Http\Middleware\CaptureApiRequest yourself.
    |
    */
    'register_global_middleware' => true,

    /*
    |--------------------------------------------------------------------------
    | Which requests should be captured?
    |--------------------------------------------------------------------------
    |
    | Paths use Laravel wildcard syntax (e.g. "api/*"). The inspector's own
    | routes, Livewire traffic and common health endpoints are ignored by
    | default to keep the feed useful. All of these values can be overridden
    | from the settings page.
    |
    */
    'only_paths' => [],

    'ignore_paths' => [
        'api-inspector/*',
        '*/api-inspector',
        '*/api-inspector/*',
        '*/api-requests',
        '*/api-requests/*',
        'livewire/*',
        'livewire-*',
        'livewire-*/*',
        '_ignition/*',
        'telescope/*',
        'horizon/*',
        'sanctum/csrf-cookie',
        'up',
        'health',
        'healthz',
    ],

    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'sample_rate' => 100,

    'ignore_content_types' => [
        'image/*',
        'video/*',
        'audio/*',
        'font/*',
        'application/octet-stream',
        'application/zip',
        'application/gzip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Capture fields
    |--------------------------------------------------------------------------
    |
    | The settings page exposes this list as a checklist. Core fields are
    | always stored because they are required to identify a request; every
    | other field can be toggled on or off at runtime.
    |
    */
    'capture' => [
        'method',
        'uri',
        'status',
        'timestamp',
        'duration',
        'memory',
        'ip',
        'headers',
        'query',
        'payload',
        'response_headers',
        'response_body',
        'user',
        'user_agent',
        'referer',
        'route',
        'session',
        'exception',
        'replay',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => env('API_INSPECTOR_DB_CONNECTION'),
        'requests_table' => 'api_inspector_requests',
        'replays_table' => 'api_inspector_replays',
        'settings_table' => 'api_inspector_settings',
    ],

    'max_payload_kb' => 64,
    'max_response_kb' => 128,
    'max_exception_kb' => 32,

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    |
    | Matching values are replaced with "[redacted]" before anything is sent
    | to storage. Header names and JSON keys are matched case-insensitively.
    |
    */
    'redact_headers' => [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token',
        'x-xsrf-token',
        'php-auth-pw',
    ],

    'redact_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'private_key',
        'card_number',
        'card_cvc',
        'cvv',
        'ssn',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */
    'retention_days' => 14,
    'max_records' => 25000,
    'schedule_prune' => true,

    /*
    |--------------------------------------------------------------------------
    | Live updates
    |--------------------------------------------------------------------------
    |
    | The request feed polls in the background; no manual refreshing needed.
    | Intervals are in seconds and may also be changed from the UI.
    |
    */
    'live' => [
        'enabled' => true,
        'interval' => 5,
        'notify_on_new' => true,
        'notify_on_error' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Replay
    |--------------------------------------------------------------------------
    |
    | Simulated replays never touch your application. Live replays dispatch an
    | internal request, and can optionally roll back database writes.
    |
    */
    'replay' => [
        'enabled' => true,
        'allow_live' => true,
        'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'],
        'allowed_hosts' => [],
        'rollback_by_default' => true,
        'max_body_kb' => 256,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | By default every user who can access the Filament panel can use the
    | inspector. Restrict it with a gate and/or an explicit email allow list.
    |
    */
    'authorization' => [
        'gate' => env('API_INSPECTOR_GATE'),
        'emails' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'API Inspector',
        'sort' => 90,
        'icon' => 'heroicon-o-signal',
        'requests_label' => 'Requests',
        'settings_label' => 'Settings',
    ],

    'user_label_attribute' => 'email',

    'log_failures' => false,
];
