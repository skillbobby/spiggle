# Spiggle API Inspector

**Capture, inspect, filter and replay every API call in your Filament panel or Laravel app — without leaving the admin UI.**

![Request feed](art/list.png)

API Inspector listens to the global HTTP stack, records the request and response data you choose, and turns the feed into a live, searchable debugging workspace. Simulated replays explain exactly what a request will do before anything runs. Live replays can even roll back database writes afterwards.

Built and tested against **Filament 4 and Filament 5**. See [`docs/PLAN.md`](docs/PLAN.md) for the architecture, replay design and full verification matrix.

## Features

- **Capture everything that matters** — method, URI, route, controller, status, duration, memory, request/response headers, query string, JSON/form payloads, response body, IP, user, user agent, referer, session and exceptions.
- **Choose the fields** — a checklist in the panel decides exactly what is stored. Core identifiers are always kept so the feed stays navigable.
- **Privacy first** — authorization headers, cookies, CSRF tokens and configurable body/query keys are redacted before storage. Payload and response sizes are capped.
- **Powerful capture rules** — allow/ignore paths with wildcards, method filters, sampling, static asset and content-type exclusion, and separate request/response limits.
- **Live by default** — the table, stats and bottom status dock poll in the background. New traffic appears without refreshing; new errors raise a toast; a loader runs while updates are in flight.
- **Designed for triage** — method and status badges, latency bars, filters for errors/slow/replayed requests, a date range filter and global search.
- **Deep inspection** — request payload, response body, headers, middleware, route, user and exception details in one tabbed view with JSON highlighting and copy buttons.
- **Replay console** — simulated mode resolves the route and shows auth, side effects and warnings without executing. Live mode sends the request through the real HTTP kernel, optionally rolling back database writes.
- **Retention built in** — daily pruning by age and total record cap, plus an on-demand `api-inspector:prune` command with dry-run support.
- **Safe by default** — replay guardrails for allowed methods/hosts, recursion protection, inspector self-exclusion and configurable access control.

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | 8.2+ |
| Laravel | 11, 12 or 13 |
| Filament | 4.x or 5.x |
| Database | MySQL, PostgreSQL or SQLite |

## Installation

```bash
composer require spiggle/filament-api-inspector

# Publishes config/migrations, publishes Filament assets and optionally migrates
php artisan api-inspector:install --migrate
```

> **Developing from this repository:** the package lives in `filament-api-inspector/`. Add a path repository and require the dev branch until the package is tagged on Packagist:
>
> ```bash
> composer config repositories.api-inspector path ./filament-api-inspector
> composer require spiggle/filament-api-inspector:@dev
> ```

If you prefer to do it manually:

```bash
php artisan vendor:publish --tag=api-inspector-config
php artisan vendor:publish --tag=api-inspector-migrations
php artisan migrate
php artisan filament:assets
```

Register the plugin in your panel provider:

```php
use Spiggle\ApiInspector\ApiInspectorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ApiInspectorPlugin::make());
}
```

Open **API Inspector → Settings** and check the data you want to capture.

> Assets are served from `public/css/spiggle/filament-api-inspector` and `public/js/spiggle/filament-api-inspector`. Run `php artisan filament:assets` after installing or updating the package.

## How capture works

`Spiggle\ApiInspector\Http\Middleware\CaptureApiRequest` is pushed onto the global HTTP stack. It:

1. Runs your capture rules before the request reaches the application.
2. Starts a timer and remembers the request state.
3. Lets the application handle the request untouched.
4. Writes the record after the response has been sent (`terminate()`), so observability never slows the response.

The listener is fail-safe: a storage error or missing migration will never break the host application. Set `API_INSPECTOR_LOG_FAILURES=true` (or `log_failures` in config) to log capture failures.

Inspector pages, Livewire update traffic, health checks and static assets are excluded by default to keep the feed useful.

## What gets stored

The settings page exposes every field as a checklist:

| Group | Fields |
| --- | --- |
| Request | HTTP method, URI & path, query string, request payload, headers, user agent, referer |
| Response | status, duration, response headers, response body, exception details |
| Context | client IP, authenticated user, session ID, timestamp, route/controller/middleware, peak memory, replay history |

**Core fields** (method, URI, status, captured at) are always retained. Everything else can be toggled at runtime; changes take effect on the next request.

Uploaded files are never stored — only their name, MIME type and size metadata. Binary and configured content types skip body storage while keeping status, headers and size.

## Using the feed

The request feed is designed to stay out of your way:

- **Live dock** — a fixed bottom bar shows whether capture is live, the last update, today's total and server error count. It polls on the interval selected in settings (2–60 seconds) and shows a progress loader while fetching.
- **Automatic refresh** — the table and the 24-hour stats refresh on the same interval. No manual reloads.
- **Toasts** — new traffic raises a subtle success toast; new 5xx responses raise a danger toast. Toggle both in **Settings → Live feed**.
- **Search & filters** — search by path, URI, route, caller or IP. Filter by method, status family, date range, slow requests, server errors or replayed requests.
- **Quick actions** — inspect a request or jump straight to its replay console from the row action buttons.
- **Detail view** — Request, Response, Headers, Context and Replays tabs; request/response bodies are syntax-highlighted with copy buttons; cURL export is one click away.

## Replay

Every captured request has a replay console with two modes.

### Simulated

A simulated replay never executes anything. It:

- Resolves the target against the application's route collection.
- Shows the matched route, controller and middleware.
- Reports whether authentication is required and which user would be restored.
- Flags writes, destructive actions, CSRF-protected routes, throttling, truncated payloads and external hosts.
- Produces a step-by-step plan that is stored in the replay history.

Use this to answer “what would happen if I sent this again?” without risk.

### Live

A live replay dispatches an internal request through the real Laravel HTTP kernel:

- The original authenticated user is restored for the replay.
- The request can be edited first (method, URI, headers, body).
- **Safe mode** wraps execution in a database transaction and rolls it back. Database writes are undone; emails, queued jobs, cache writes and third-party API calls cannot be rolled back.
- Internal replays are marked and excluded from the capture feed, so they never create feedback loops.
- Replays are blocked for exotic methods, external hosts and inspector routes. Configure limits in **Settings → Replay** or `config/api-inspector.php`.

The replay result shows status, duration, warnings, the execution plan, whether the transaction was rolled back and the response body.

## Live updates

```php
'live' => [
    'enabled' => true,
    'interval' => 5,             // seconds
    'notify_on_new' => true,
    'notify_on_error' => true,
],
```

The live bar only appears on request feed/detail pages. Settings stays focused on configuration.

## Capture rules

```php
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
'methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'sample_rate' => 100,
'ignore_content_types' => ['image/*', 'video/*', 'audio/*', 'font/*', 'application/octet-stream', 'application/zip', 'application/gzip'],
```

Paths support Laravel wildcards (`api/*`, `webhooks/stripe*`). The allow list is evaluated first, then the ignore list. Method names are normalised to uppercase.

## Privacy

Sensitive header names and JSON/query keys are redacted before a record is written:

```php
'redact_headers' => [
    'authorization', 'proxy-authorization', 'cookie', 'set-cookie',
    'x-api-key', 'x-auth-token', 'x-csrf-token', 'x-xsrf-token', 'php-auth-pw',
],
'redact_keys' => [
    'password', 'password_confirmation', 'current_password', 'secret', 'token',
    'api_key', 'apikey', 'access_token', 'refresh_token', 'private_key',
    'card_number', 'card_cvc', 'cvv', 'ssn',
],
```

Wildcards are supported (`*_token`, `*_secret`). URI query strings are redacted too; secrets do not leak through the stored URI.

> The inspector stores request/response bodies when enabled. Treat its database table with the same care as application logs, apply your own retention policy and restrict access in production.


## Authorization

By default, any authenticated user who can access the Filament panel can use the inspector. Restrict it with a gate and/or email allow list:

```php
'authorization' => [
    'gate' => 'viewApiInspector', // Gate::forUser($user)->allows(...)
    'emails' => ['ops@example.com'],
],
```

Enforce it in a service provider:

```php
Gate::define('viewApiInspector', fn (User $user): bool => $user->isAdmin());
```

The resource and settings page both honour the same policy. API capture itself is independent of panel access.

## Retention

```php
'retention_days' => 14,
'max_records' => 25000,
'schedule_prune' => true,
```

The package schedules `api-inspector:prune` daily at 03:15. Run it manually at any time:

```bash
php artisan api-inspector:prune --days=7 --max=10000
php artisan api-inspector:prune --dry-run
```

Replay history is removed with its parent request.

## Configuration reference

| Key | Default | Purpose |
| --- | --- | --- |
| `enabled` | `true` | Master capture switch. |
| `register_global_middleware` | `true` | Capture panel, API and web traffic automatically. |
| `only_paths` / `ignore_paths` | `[]` / sensible ignores | Capture allow/deny list. |
| `methods` | GET…OPTIONS | Methods eligible for capture. |
| `sample_rate` | `100` | Capture percentage (1–100). |
| `capture` | all fields except session | Stored fields (core fields are forced; session is opt-in). |
| `max_payload_kb` | `64` | Request body cap. |
| `max_response_kb` | `128` | Response body cap. |
| `redact_headers` / `redact_keys` | see above | Sensitive value redaction. |
| `retention_days` / `max_records` | `14` / `25000` | Retention policy. |
| `live.interval` | `5` | Poll interval in seconds. |
| `replay.allow_live` | `true` | Enable live replays. |
| `replay.allowed_methods` | GET…DELETE | Methods a live replay may use. |
| `replay.rollback_by_default` | `true` | Default safe mode for replay UI. |
| `replay.allowed_hosts` | `[]` | Additional hosts permitted as replay targets. |
| `authorization` | any panel user | Gate/email policy. |
| `user_label_attribute` | `email` | Attribute shown as the caller label. |
| `log_failures` | `false` | Log storage failures instead of staying silent. |

Every UI-editable setting is stored in `api_inspector_settings`; config values are the defaults. Reset overrides from the settings page at any time.

## Testing

```bash
composer test
```

The suite covers capture, filtering, redaction, truncation, exceptions, binary responses, replay planning, live replay, rollback, auth restoration, blocking rules, settings, pruning and the Filament pages/live bar.

Verified against both supported branches:

| Filament | Livewire | Laravel | Result |
| --- | --- | --- | --- |
| 5.11 | 4.4 | 12.70 | 49 tests / 251 assertions |
| 4.16 | 3.8 | 12.70 | 49 tests / 251 assertions |

### Workbench demo

A small workbench app is included for local UI work:

```bash
php vendor/bin/testbench migrate:fresh --seed --seeder='Workbench\Database\Seeders\DatabaseSeeder'
php vendor/bin/testbench filament:assets
php vendor/bin/testbench serve --port=8001
```

Open [http://127.0.0.1:8001/admin/api-requests](http://127.0.0.1:8001/admin/api-requests). The demo auto-logs in as `ada@example.com` and seeds 20 realistic requests. Demo-only routes live under `/api/*`.

![Settings](art/settings.png)

## Security notes

- Live replay executes requests inside your application. Protect the inspector with a gate/email list and restrict `replay.allowed_methods` in production.
- Redaction runs before persistence, but request bodies can still contain business data. Use the payload/response caps, narrow the field checklist and prune regularly.
- Replay of web (non-API) routes can fail CSRF checks; the plan warns you before executing.
- The inspector UI is served through your panel; no public routes are registered by the package.

## License

MIT © Spiggle. See [LICENSE](LICENSE).

