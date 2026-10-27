# Implementation plan — Spiggle API Inspector

## Product goal

Give a Filament panel a first-class HTTP observability workspace:

1. Capture every relevant incoming call with configurable detail.
2. Let an admin find a request quickly, understand it deeply and replay it safely.
3. Keep the UI live, calm and useful without manual refreshes.
4. Work identically on Filament 4 and Filament 5.

## Architecture

| Layer | Responsibility |
| --- | --- |
| `ApiInspectorServiceProvider` | Config, migrations, views, assets, global middleware, exception tracking, live bar, prune schedule |
| `Http\Middleware\CaptureApiRequest` | Request-scoped timing and termination hook |
| `Http\CaptureState` | Shares state between the handle and terminate middleware instances (WeakMap, Octane-safe) |
| `Http\RequestFilter` | Master switch, allow/deny paths, methods, sample rate, self/Livewire/static exclusion |
| `Http\RequestCapturer` | Field selection, redaction, payload/response decoding, truncation, storage |
| `Support\Settings` | Cached config + database overrides, sanitisation, form data |
| `Services\ReplayService` | Route planning, guardrails, simulated records, internal live execution, auth restoration, DB rollback |
| `Services\Pruner` | Retention by age and max record count |
| Filament resource/pages | Feed, detail workspace, replay console, settings |
| `Livewire\LiveBar` | Background status dock, counts, new-request detection, toasts |

## Data model

- `api_inspector_requests` — one row per capture; ULID key, request/response/context/performance columns, replay counters, exception details.
- `api_inspector_replays` — one row per simulated/live attempt; mode, target, sanitized headers/body, plan, response, error, rollback flag.
- `api_inspector_settings` — one JSON payload row (`default`) overriding config defaults.

Indexes cover the feed's common access paths: `captured_at`, `status_code`, `method`, `path`, `ip`, `route_name`, `duration_ms`.

## Capture flow

1. Global middleware evaluates `RequestFilter`.
2. State (timestamp, high-resolution start, exception bucket) is stored against the request.
3. The application handles the request unchanged.
4. After the response is sent, `terminate()` builds the selected fields, redacts secrets in headers/query/URI/body, caps sizes, and writes the record.
5. Storage failures are swallowed (optionally logged) so observability cannot break the app.
6. Route exceptions are also fed into the request state through the Laravel exception handler's `reportable()` hook.

## Replay flow

1. Normalise target method/URI/payload/headers.
2. Match the target against the route collection and build a plan: route/controller/middleware, auth guard/user, writes/destructive flag, CSRF/throttle/host warnings, blockers.
3. **Simulated** — store the plan and show the original captured response as a reference; execute nothing.
4. **Live** — bind the current request/user, execute through the Laravel HTTP kernel with a recursion guard. Optionally wrap in a DB transaction and roll back afterwards. Response, duration and status are persisted.
5. Every attempt increments the source request's replay counter and appears in history.

## UI plan

- **Feed** — method/status badges, path/context cell, latency meter, status family labels, caller/date filters, search, bulk delete, stats overview with 24h traffic/errors/latency/replays.
- **Live dock** — fixed bottom status, capture state, last update, today/errors, new-request chip, indeterminate loader, accessible live region; polls independently of the table.
- **Detail workspace** — summary hero, tabs for Request / Response / Headers / Context / Replays, JSON highlighting, copy actions, cURL export, sticky replay console with plan timeline and response preview.
- **Settings** — capture switch, field checklist (core fields marked always captured), path/method/sample rules, size/content-type limits, redaction, replay policy, live interval/toasts, retention. Live preview of the record shape.
- **Visual system** — self-contained `api-inspector.css` using Filament colour variables, light/dark support, responsive grid, reduced-motion support and scoped `ai-` classes.

## Security & safety

- Redaction before persistence; URI query values redacted too.
- Live replay restrictions: allowed methods/hosts, self-route blocking, recursion guard, optional DB rollback, warnings for redacted auth/CSRF/truncated payload/files.
- Panel access through a gate/email allow list.
- No public package routes.

## Verification plan

| Area | Coverage |
| --- | --- |
| Capture | methods, status, URI/query, payloads, headers, IP, user agent, user, exceptions, binary/content-type skip, truncation, toggles, allow/ignore paths, method filters, self/Livewire/replay exclusion |
| Settings | defaults, merge, sanitisation, clamping, partial updates, reset, storage readiness |
| Replay | simulation plan, original response reference, live GET, live mutation rollback, mutation persistence, auth restoration, payload/header overrides, method/host/inspector blockers, missing route, nested replay, history link |
| Retention | age pruning, max-record pruning, dry run, replay cleanup, command |
| UI | feed render, detail render, settings render, settings save, access restriction, live bar counts/poll, simulated/live replay from detail |
| Support | cURL escaping/redaction, wildcard redaction, UTF-8 truncation, binary detection |

Run against both dependency branches:

- Filament 5.11 / Livewire 4.4 / Laravel 12.70 — 49 tests, 251 assertions.
- Filament 4.16 / Livewire 3.8 / Laravel 12.70 — 49 tests, 251 assertions.
