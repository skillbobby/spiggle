<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spiggle\ApiInspector\Enums\ReplayMode;
use Spiggle\ApiInspector\Http\ReplayContext;
use Spiggle\ApiInspector\Models\ApiReplay;
use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Support\Body;
use Spiggle\ApiInspector\Support\Redactor;
use Spiggle\ApiInspector\Support\Settings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ReplayService
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @param  array{method?: string, uri?: string, payload?: mixed, headers?: array<string, string>, rollback?: bool}  $options
     */
    public function replay(ApiRequest $source, ReplayMode $mode, array $options = []): ApiReplay
    {
        $options = $this->normalizeOptions($source, $options);
        $plan = $this->buildPlan($source, $options);

        if ($mode === ReplayMode::Simulated) {
            return $this->recordSimulation($source, $options, $plan);
        }

        return $this->executeLive($source, $options, $plan);
    }

    /**
     * Build the inspection plan without executing anything.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function plan(ApiRequest $source, array $options = []): array
    {
        $options = $this->normalizeOptions($source, $options);

        return $this->buildPlan($source, $options);
    }

    /**
     * @param  array{method?: string, uri?: string, payload?: mixed, headers?: array<string, string>, rollback?: bool}  $options
     * @return array<string, mixed>
     */
    private function normalizeOptions(ApiRequest $source, array $options): array
    {
        $method = strtoupper(trim((string) ($options['method'] ?? $source->method)));
        $uri = $this->targetUrl($source, $options['uri'] ?? null);
        $payloadProvided = array_key_exists('payload', $options) && $options['payload'] !== null;
        $payload = $this->resolvePayload($source, $options['payload'] ?? null, $payloadProvided);

        $headers = $this->resolveHeaders($source, $options['headers'] ?? [], $payload['type'], $payloadProvided);

        return [
            'method' => $method,
            'uri' => $uri,
            'path' => parse_url($uri, PHP_URL_PATH) ?: '/',
            'host' => strtolower((string) (parse_url($uri, PHP_URL_HOST) ?: '')),
            'payload' => $payload['value'],
            'payload_type' => $payload['type'],
            'payload_provided' => $payloadProvided,
            'headers' => $headers,
            'rollback' => (bool) ($options['rollback'] ?? false),
        ];
    }

    private function targetUrl(ApiRequest $source, ?string $uri): string
    {
        $uri = trim((string) ($uri ?? $source->uri ?? $source->path));

        if ($uri === '') {
            $uri = (string) $source->path;
        }

        if (Str::startsWith($uri, ['http://', 'https://'])) {
            return $uri;
        }

        $host = $source->host ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $scheme = $source->scheme ?: 'http';

        return $scheme . '://' . $host . '/' . ltrim($uri, '/');
    }

    /**
     * @return array{value: mixed, type: string|null}
     */
    private function resolvePayload(ApiRequest $source, mixed $override, bool $provided): array
    {
        if ($provided) {
            if (is_array($override)) {
                return [
                    'value' => Redactor::data($override, $this->settings->redactKeys()),
                    'type' => $source->payload_type === 'form' ? 'form' : 'json',
                ];
            }

            $raw = trim((string) $override);

            if ($raw === '') {
                return ['value' => null, 'type' => null];
            }

            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return [
                    'value' => Redactor::data($decoded, $this->settings->redactKeys()),
                    'type' => 'json',
                ];
            }

            return ['value' => $raw, 'type' => 'text'];
        }

        return [
            'value' => $source->decodedPayload(),
            'type' => $source->payload_type,
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function resolveHeaders(ApiRequest $source, array $overrides, ?string $payloadType, bool $payloadProvided = false): array
    {
        $headers = [];
        $allowed = [
            'accept',
            'accept-language',
            'content-type',
            'user-agent',
            'x-requested-with',
            'x-http-method-override',
            'x-api-version',
        ];

        foreach (Body::flattenHeaders($source->request_headers ?? []) as $name => $value) {
            if (
                in_array(strtolower($name), $allowed, true)
                && $value !== Redactor::PLACEHOLDER
                && $value !== ''
            ) {
                $headers[$name] = $value;
            }
        }

        foreach ($overrides as $name => $value) {
            if (! is_string($name) || ! is_scalar($value)) {
                continue;
            }

            $name = trim($name);

            if ($name === '' || ! preg_match('/^[A-Za-z0-9-]+$/', $name)) {
                continue;
            }

            $headers[$name] = (string) $value;
        }

        if (! $this->hasHeader($headers, 'accept')) {
            $headers['Accept'] = 'application/json';
        }

        if ($payloadProvided && ! $this->hasHeader($overrides, 'content-type')) {
            if ($payloadType === 'json') {
                $headers = $this->withHeader($headers, 'Content-Type', 'application/json');
            } elseif ($payloadType === 'form') {
                $headers = $this->withHeader($headers, 'Content-Type', 'application/x-www-form-urlencoded');
            } elseif ($payloadType === 'text') {
                $headers = $this->withHeader($headers, 'Content-Type', 'text/plain');
            }
        }

        if (! $this->hasHeader($headers, 'content-type')) {
            if ($payloadType === 'json') {
                $headers['Content-Type'] = 'application/json';
            } elseif ($payloadType === 'form') {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function withHeader(array $headers, string $name, string $value): array
    {
        foreach (array_keys($headers) as $key) {
            if (strtolower($key) === strtolower($name)) {
                unset($headers[$key]);
            }
        }

        $headers[$name] = $value;

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $key) {
            if (strtolower($key) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function buildPlan(ApiRequest $source, array $options): array
    {
        $method = (string) $options['method'];
        $path = ltrim((string) $options['path'], '/');
        $route = $this->matchRoute((string) $options['uri'], $method);
        $sourceUser = $this->sourceUser($source);

        $warnings = [];
        $blockers = [];

        if (! $this->settings->replayEnabled()) {
            $blockers[] = 'Replay is disabled in the inspector settings.';
        }

        if (ReplayContext::isRunning()) {
            $blockers[] = 'A replay is already running. Nested replays are blocked.';
        }

        if (! in_array($method, $this->settings->replayMethods(), true)) {
            $blockers[] = $method . ' requests are not allowed by the replay settings.';
        }

        if (Str::is(['api-inspector', 'api-inspector/*', 'livewire', 'livewire/*'], $path)) {
            $blockers[] = 'The target belongs to the inspector itself and cannot be replayed.';
        }

        $allowedHosts = array_values(array_filter(array_map(
            static fn (mixed $host): string => strtolower(trim((string) $host)),
            array_merge(
                [(string) $source->host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)],
                (array) config('api-inspector.replay.allowed_hosts', []),
            ),
        )));

        if ($options['host'] !== '' && ! in_array((string) $options['host'], $allowedHosts, true)) {
            $blockers[] = 'The target host [' . $options['host'] . '] is not allowed. Add it to replay.allowed_hosts.';
        }

        if (! $route['matched']) {
            $warnings[] = 'No route matched this request. A live replay would return a 404 response.';
        }

        if ($route['auth_required'] && $sourceUser === null) {
            $warnings[] = 'The route is authenticated and the original user could not be restored; a live replay may be redirected to login.';
        }

        if ($route['csrf']) {
            $warnings[] = 'The route uses CSRF protection. Live replay of web routes can return 419 because tokens and cookies are not forwarded.';
        }

        if ($route['throttled']) {
            $warnings[] = 'The route is rate limited; repeated replays may be throttled.';
        }

        if ($source->payload_truncated) {
            $warnings[] = 'The captured payload was truncated. A live replay would send only the stored portion unless you edit the body.';
        }

        if (str_contains((string) $source->request_payload, '__files')) {
            $warnings[] = 'Uploaded files are not stored or replayed. Only the form fields will be sent.';
        }

        if (str_contains(strtolower((string) $source->uri), 'redacted')) {
            $warnings[] = 'The captured URI contains redacted query values. Edit the target URI before a live replay if those values are still required.';
        }

        $sourceHeaders = Body::flattenHeaders($source->request_headers ?? []);
        $authorization = $sourceHeaders['authorization'] ?? $sourceHeaders['Authorization'] ?? null;

        if ($authorization === Redactor::PLACEHOLDER) {
            $warnings[] = 'The original authorization header was redacted and cannot be reused. Live replays rely on the restored user session instead.';
        }

        $writes = ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
        $destructive = $method === 'DELETE'
            || str_contains(strtolower((string) $route['controller']), 'destroy')
            || str_contains(strtolower((string) $route['controller']), 'delete');

        if ($writes) {
            $warnings[] = 'This request can modify application data.';
        }

        if ($options['rollback']) {
            $warnings[] = 'Database writes will be rolled back after the replay. External side effects (mail, queues, HTTP calls) cannot be undone.';
        }

        $steps = [
            [
                'label' => 'Target request',
                'status' => 'info',
                'detail' => $method . ' ' . $options['uri'],
            ],
            [
                'label' => 'Route resolution',
                'status' => $route['matched'] ? 'passed' : 'warning',
                'detail' => $route['matched']
                    ? ($route['name'] ?: ($route['controller'] ?: $route['uri']))
                    : 'No matching route',
            ],
            [
                'label' => 'Authentication',
                'status' => $route['auth_required'] && $sourceUser === null ? 'warning' : 'passed',
                'detail' => $route['auth_required']
                    ? (($sourceUser ? 'Replays as ' . $this->userLabel($sourceUser) : 'Guest (original user unavailable)')
                        . ($route['auth_guard'] ? ' via ' . $route['auth_guard'] : ''))
                    : 'No authentication required',
            ],
            [
                'label' => 'Side effects',
                'status' => $writes ? 'warning' : 'passed',
                'detail' => $writes
                    ? ($destructive ? 'Destructive write' : 'May write data') . ($options['rollback'] ? ' · database changes rolled back' : '')
                    : 'Read-only request',
            ],
            [
                'label' => 'Execution',
                'status' => $blockers === [] ? 'passed' : 'blocked',
                'detail' => $blockers === []
                    ? 'Ready to send through your application'
                    : implode(' ', $blockers),
            ],
        ];

        return [
            'target' => [
                'method' => $method,
                'uri' => $options['uri'],
                'host' => $options['host'],
                'path' => '/' . $path,
            ],
            'route' => $route,
            'auth' => [
                'required' => $route['auth_required'],
                'guard' => $route['auth_guard'],
                'resolved' => $sourceUser !== null,
                'user' => $sourceUser ? $this->userLabel($sourceUser) : null,
            ],
            'effects' => [
                'writes' => $writes,
                'destructive' => $destructive,
                'rollback' => (bool) $options['rollback'],
            ],
            'warnings' => array_values(array_unique($warnings)),
            'blockers' => array_values(array_unique($blockers)),
            'steps' => $steps,
            'request' => [
                'payload_type' => $options['payload_type'],
                'headers' => array_keys($options['headers']),
            ],
        ];
    }


    /**
     * @return array{matched: bool, name: string|null, controller: string|null, uri: string|null, middleware: array<int, string>, auth_required: bool, auth_guard: string|null, csrf: bool, throttled: bool, allowed_methods: array<int, string>}
     */
    private function matchRoute(string $uri, string $method): array
    {
        $result = [
            'matched' => false,
            'name' => null,
            'controller' => null,
            'uri' => null,
            'middleware' => [],
            'auth_required' => false,
            'auth_guard' => null,
            'csrf' => false,
            'throttled' => false,
            'allowed_methods' => [],
        ];

        try {
            $route = Route::getRoutes()->match(Request::create($uri, $method));
        } catch (MethodNotAllowedHttpException $exception) {
            $result['allowed_methods'] = $exception->getAllowedMethods();

            return $result;
        } catch (NotFoundHttpException) {
            return $result;
        } catch (Throwable) {
            return $result;
        }

        $action = $route->getActionName();
        $middleware = [];

        try {
            $middleware = array_values(array_filter(
                $route->gatherMiddleware(),
                static fn (mixed $value): bool => is_string($value),
            ));
        } catch (Throwable) {
            // Middleware could not be resolved (e.g. missing controller).
        }

        $authRequired = false;
        $authGuard = null;
        $csrf = false;
        $throttled = false;

        foreach ($middleware as $item) {
            $lower = strtolower($item);

            if (str_starts_with($lower, 'auth') || str_contains($item, 'Authenticate')) {
                $authRequired = true;

                if (preg_match('/^auth:([^,]+)/', $lower, $matches)) {
                    $authGuard = trim($matches[1]);
                } elseif ($authGuard === null) {
                    $authGuard = 'web';
                }
            }

            if (str_contains($lower, 'csrf') || str_contains($item, 'ValidateCsrfToken')) {
                $csrf = true;
            }

            if (str_starts_with($lower, 'throttle')) {
                $throttled = true;
            }
        }

        return [
            'matched' => true,
            'name' => $route->getName(),
            'controller' => is_string($action) && $action !== 'Closure' ? $action : null,
            'uri' => $route->uri(),
            'middleware' => $middleware,
            'auth_required' => $authRequired,
            'auth_guard' => $authGuard,
            'csrf' => $csrf,
            'throttled' => $throttled,
            'allowed_methods' => [],
        ];
    }

    private function sourceUser(ApiRequest $source): ?Authenticatable
    {
        $class = (string) $source->user_type;
        $id = $source->user_id;

        if ($class === '' || $id === null) {
            return null;
        }

        try {
            if (! class_exists($class)) {
                $class = (string) \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($class);
            }

            if ($class === '' || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                return null;
            }

            /** @var Model|null $user */
            $user = $class::query()->find($id);

            return $user instanceof Authenticatable ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function userLabel(Authenticatable $user): string
    {
        $label = data_get($user, $this->settings->userLabelAttribute());

        return is_scalar($label) && (string) $label !== ''
            ? (string) $label
            : (string) $user->getAuthIdentifier();
    }


    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $plan
     */
    private function recordSimulation(ApiRequest $source, array $options, array $plan): ApiReplay
    {
        $replay = $this->makeReplay($source, ReplayMode::Simulated, $options, $plan, (string) Str::ulid());

        if ($plan['blockers'] !== []) {
            $replay->error = 'Blocked: ' . implode(' ', $plan['blockers']);
        }

        $replay->save();

        $this->touchSource($source);

        return $replay->refresh();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $plan
     */
    private function executeLive(ApiRequest $source, array $options, array $plan): ApiReplay
    {
        $replay = $this->makeReplay($source, ReplayMode::Live, $options, $plan, (string) Str::ulid());

        if ($plan['blockers'] !== []) {
            $replay->error = 'Blocked: ' . implode(' ', $plan['blockers']);
            $replay->save();
            $this->touchSource($source);

            return $replay->refresh();
        }

        $headers = $options['headers'];
        $headers['X-Api-Inspector-Replay'] = $replay->id;
        $replay->request_headers = Redactor::headers($headers, $this->settings->redactHeaders());

        $sourceUser = $this->sourceUser($source);
        $guardName = $plan['auth']['guard'] ?? null;
        $guard = Auth::guard();

        if (is_string($guardName) && $guardName !== '') {
            try {
                $guard = Auth::guard($guardName);
            } catch (Throwable) {
                // Fall back to the default guard if the route guard is not configured.
            }
        }

        $previousUser = null;

        try {
            $previousUser = $guard->user();
        } catch (Throwable) {
            $previousUser = null;
        }

        if ($sourceUser !== null) {
            $guard->setUser($sourceUser);
        }

        $httpRequest = $this->makeHttpRequest($options, $headers, $sourceUser);
        $previousRequest = app('request');
        $kernel = app(HttpKernel::class);

        $transactionStarted = false;

        if ($options['rollback']) {
            try {
                DB::beginTransaction();
                $transactionStarted = true;
            } catch (Throwable) {
                $transactionStarted = false;
            }
        }

        $response = null;
        $error = null;
        $start = hrtime(true);

        try {
            app()->instance('request', $httpRequest);

            $response = ReplayContext::run(fn (): Response => $kernel->handle($httpRequest));
        } catch (Throwable $exception) {
            $error = $exception::class . ': ' . $exception->getMessage();
        } finally {
            $duration = (hrtime(true) - $start) / 1_000_000;

            if ($transactionStarted) {
                try {
                    DB::rollBack();
                } catch (Throwable) {
                    // The transaction may already have been closed by the app.
                }
            }

            if ($sourceUser !== null) {
                if ($previousUser instanceof Authenticatable) {
                    $guard->setUser($previousUser);
                } elseif (method_exists($guard, 'forgetUser')) {
                    $guard->forgetUser();
                }
            }

            app()->instance('request', $previousRequest);
        }

        $replay->duration_ms = round($duration, 3);
        $replay->rolled_back = $transactionStarted;

        if ($response !== null) {
            $replay->status_code = $response->getStatusCode();
            $replay->response_headers = Redactor::headers(
                $response->headers->all(),
                $this->settings->redactHeaders(),
            );

            $this->storeResponseBody($replay, $response);
        }

        if ($error !== null) {
            $replay->error = Body::truncate($error, $this->settings->maxExceptionBytes());
        }

        $replay->save();

        $this->touchSource($source);

        return $replay->refresh();
    }


    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $plan
     */
    private function makeReplay(
        ApiRequest $source,
        ReplayMode $mode,
        array $options,
        array $plan,
        string $id,
    ): ApiReplay {
        $actor = Auth::user();
        $payload = $options['payload'];
        $encoded = is_string($payload) && $options['payload_type'] === 'text'
            ? $payload
            : Body::encode($payload);

        $truncated = false;
        $encoded = Body::truncate($encoded, $this->settings->maxPayloadBytes(), $truncated);

        return new ApiReplay([
            'id' => $id,
            'api_request_id' => $source->getKey(),
            'mode' => $mode->value,
            'method' => $options['method'],
            'uri' => $options['uri'],
            'host' => $options['host'],
            'request_headers' => Redactor::headers($options['headers'], $this->settings->redactHeaders()),
            'request_payload' => $encoded,
            'payload_type' => $options['payload_type'],
            'plan' => $plan,
            'user_id' => $actor?->getAuthIdentifier() !== null ? (string) $actor->getAuthIdentifier() : null,
            'user_label' => $actor !== null ? $this->userLabel($actor) : null,
            'rolled_back' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $headers
     */
    private function makeHttpRequest(array $options, array $headers, ?Authenticatable $user): Request
    {
        $host = (string) $options['host'];
        $scheme = parse_url((string) $options['uri'], PHP_URL_SCHEME) ?: 'http';

        $server = [
            'HTTP_HOST' => $host !== '' ? $host : 'localhost',
            'SERVER_NAME' => $host !== '' ? $host : 'localhost',
            'SERVER_PORT' => $scheme === 'https' ? '443' : '80',
            'REQUEST_SCHEME' => $scheme,
        ];

        if ($scheme === 'https') {
            $server['HTTPS'] = 'on';
        }

        $parameters = $options['payload_type'] === 'form' && is_array($options['payload'])
            ? $options['payload']
            : [];

        $content = null;

        if ($options['payload_type'] === 'json') {
            $content = Body::encode($options['payload']);
        } elseif ($options['payload_type'] === 'text') {
            $content = is_string($options['payload'])
                ? $options['payload']
                : Body::encode($options['payload']);
        }

        $request = Request::create(
            (string) $options['uri'],
            (string) $options['method'],
            $parameters,
            [],
            [],
            $server,
            $content,
        );

        $request->headers->replace($headers);
        $request->setUserResolver(static fn (): ?Authenticatable => $user);

        return $request;
    }

    private function storeResponseBody(ApiReplay $replay, Response $response): void
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return;
        }

        $contentType = $response->headers->get('Content-Type');

        if (Body::isBinary($contentType, $content)) {
            return;
        }

        $maxBytes = $this->settings->maxResponseBytes();
        $configured = (int) config('api-inspector.replay.max_body_kb', 256) * 1024;

        if ($configured > 0) {
            $maxBytes = min($maxBytes, $configured);
        }

        $truncated = false;
        $replay->response_body = Body::truncate($content, $maxBytes, $truncated);
        $replay->response_truncated = $truncated;
    }

    private function touchSource(ApiRequest $source): void
    {
        $source->replay_count = ((int) $source->replay_count) + 1;
        $source->last_replayed_at = now();
        $source->save();
    }
}

