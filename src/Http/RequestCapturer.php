<?php

declare(strict_types=1);

namespace Spiggle\ApiGuard\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Spiggle\ApiGuard\Enums\CaptureField;
use Spiggle\ApiGuard\Models\ApiRequest;
use Spiggle\ApiGuard\Support\Body;
use Spiggle\ApiGuard\Support\Redactor;
use Spiggle\ApiGuard\Support\Settings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class RequestCapturer
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function capture(
        Request $request,
        Response $response,
        float $durationMs,
        ?Throwable $exception = null,
        ?Carbon $capturedAt = null,
    ): ?ApiRequest {
        try {
            $fields = $this->settings->capture();
            $capturedAt ??= Carbon::now();

            $requestContent = $this->rawContent($request);
            $responseContent = $this->rawResponseContent($response);

            $data = [
                'method' => strtoupper($request->method()),
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'path' => '/' . ltrim($request->path(), '/'),
                'uri' => $this->safeUri($request),
                'status_code' => $response->getStatusCode(),
                'status_text' => Response::$statusTexts[$response->getStatusCode()] ?? null,
                'request_size' => strlen($requestContent),
                'response_size' => $this->responseSize($response, $responseContent),
                'captured_at' => $capturedAt,
            ];

            if ($request->attributes->has('api_guard_action')) {
                $data['guard_action'] = $request->attributes->get('api_guard_action');
                $data['guard_rule_id'] = $request->attributes->get('api_guard_rule_id');
                $data['guard_rule_name'] = $request->attributes->get('api_guard_rule_name');
            }

            if (in_array(CaptureField::Duration->value, $fields, true)) {
                $data['duration_ms'] = round($durationMs, 3);
            }

            if (in_array(CaptureField::Memory->value, $fields, true)) {
                $data['memory_bytes'] = memory_get_peak_usage(true);
            }

            if (in_array(CaptureField::Ip->value, $fields, true)) {
                $data['ip'] = $request->ip();
            }

            if (in_array(CaptureField::UserAgent->value, $fields, true)) {
                $data['user_agent'] = $request->userAgent();
            }

            if (in_array(CaptureField::Referer->value, $fields, true)) {
                $data['referer'] = $request->headers->get('referer');
            }

            if (in_array(CaptureField::Headers->value, $fields, true)) {
                $data['request_headers'] = Redactor::headers(
                    $request->headers->all(),
                    $this->settings->redactHeaders(),
                );
            }

            if (in_array(CaptureField::Query->value, $fields, true)) {
                $data['query'] = Redactor::data(
                    $request->query->all(),
                    $this->settings->redactKeys(),
                );
            }

            if (in_array(CaptureField::Payload->value, $fields, true)) {
                [$payload, $payloadType] = $this->payload($request);
                $truncated = false;

                $data['request_payload'] = Body::truncate(
                    Body::encode($payload),
                    $this->settings->maxPayloadBytes(),
                    $truncated,
                );
                $data['payload_type'] = $payloadType;
                $data['payload_truncated'] = $truncated;
            }

            if (in_array(CaptureField::ResponseHeaders->value, $fields, true)) {
                $data['response_headers'] = Redactor::headers(
                    $response->headers->all(),
                    $this->settings->redactHeaders(),
                );
            }

            if (in_array(CaptureField::ResponseBody->value, $fields, true)) {
                $contentType = $response->headers->get('Content-Type');
                $ignoredContentType = Body::contentTypeMatches(
                    $contentType,
                    $this->settings->ignoreContentTypes(),
                );
                $isBinary = $responseContent !== null
                    && (Body::isBinary($contentType, $responseContent) || $ignoredContentType);

                $data['response_binary'] = $isBinary;

                if (! $isBinary && $responseContent !== null) {
                    $storedResponse = $responseContent;

                    $looksJson = str_starts_with(ltrim($responseContent), '{') || str_starts_with(ltrim($responseContent), '[');

                    if ((is_string($contentType) && str_contains(strtolower($contentType), 'json')) || $looksJson) {
                        $decodedResponse = json_decode($responseContent, true);

                        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedResponse)) {
                            $storedResponse = Body::encode(
                                Redactor::data($decodedResponse, $this->settings->redactKeys()),
                            ) ?? $responseContent;
                        }
                    }

                    $truncated = false;
                    $data['response_body'] = Body::truncate(
                        $storedResponse,
                        $this->settings->maxResponseBytes(),
                        $truncated,
                    );
                    $data['response_truncated'] = $truncated;
                }
            }

            if (in_array(CaptureField::User->value, $fields, true)) {
                $data += $this->user($request);
            }

            if (in_array(CaptureField::Session->value, $fields, true)) {
                try {
                    $data['session_id'] = $request->hasSession() ? $request->session()->getId() : null;
                } catch (Throwable) {
                    $data['session_id'] = null;
                }
            }

            if (in_array(CaptureField::Route->value, $fields, true)) {
                $data += $this->route($request);
            }

            if (in_array(CaptureField::Exception->value, $fields, true) && $exception !== null) {
                $data['exception_class'] = $exception::class;
                $data['exception_message'] = Body::truncate(
                    $exception->getMessage(),
                    $this->settings->maxExceptionBytes(),
                );
                $data['exception_trace'] = Body::truncate(
                    $exception->getTraceAsString(),
                    $this->settings->maxExceptionBytes(),
                );
            }

            return ApiRequest::query()->create($data);
        } catch (Throwable $exception) {
            if (config('api-guard.log_failures', false)) {
                Log::warning('API Guard failed to capture a request.', [
                    'exception' => $exception,
                    'uri' => $request->fullUrl(),
                ]);
            }

            return null;
        }
    }


    /**
     * @return array{0: mixed, 1: string|null}
     */
    private function payload(Request $request): array
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));
        $raw = $this->rawContent($request);

        if ($request->isJson() || str_contains($contentType, '+json')) {
            if ($raw === '') {
                return [null, null];
            }

            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return [Redactor::data($decoded, $this->settings->redactKeys()), 'json'];
            }

            return [$raw, 'text'];
        }

        if ($request->request->count() > 0 || $request->allFiles() !== []) {
            $payload = Redactor::data($request->request->all(), $this->settings->redactKeys());

            if ($request->allFiles() !== []) {
                $payload['__files'] = $this->files($request);
            }

            return [$payload, 'form'];
        }

        if ($raw !== '') {
            return [$raw, 'text'];
        }

        return [null, null];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function files(Request $request): array
    {
        $files = [];

        foreach ($request->allFiles() as $key => $file) {
            $items = is_array($file) ? $file : [$file];

            foreach ($items as $index => $upload) {
                if (! $upload instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    continue;
                }

                $fileKey = is_array($file) ? $key . '.' . $index : $key;

                $files[$fileKey] = [
                    'name' => $upload->getClientOriginalName(),
                    'mime' => $upload->getClientMimeType(),
                    'size' => $upload->getSize(),
                ];
            }
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function user(Request $request): array
    {
        try {
            $user = $request->user();
        } catch (Throwable) {
            $user = null;
        }

        if (! $user instanceof Authenticatable) {
            return [];
        }

        $label = data_get($user, $this->settings->userLabelAttribute());

        return [
            'user_id' => (string) $user->getAuthIdentifier(),
            'user_type' => $user::class,
            'user_label' => is_scalar($label) ? (string) $label : (string) $user->getAuthIdentifier(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function route(Request $request): array
    {
        $route = $request->route();

        if ($route === null) {
            return [];
        }

        $action = $route->getActionName();

        return [
            'route_name' => $route->getName(),
            'controller_action' => is_string($action) ? $action : null,
            'route_middleware' => array_values(array_filter(
                $route->gatherMiddleware(),
                static fn (mixed $middleware): bool => is_string($middleware),
            )),
        ];
    }

    private function rawContent(Request $request): string
    {
        $content = $request->getContent();

        return is_string($content) ? $content : '';
    }

    private function safeUri(Request $request): string
    {
        $query = Redactor::data($request->query->all(), $this->settings->redactKeys());

        if ($query === []) {
            return $request->url();
        }

        return $request->url() . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function rawResponseContent(Response $response): ?string
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return null;
        }

        $content = $response->getContent();

        return is_string($content) ? $content : null;
    }

    private function responseSize(Response $response, ?string $content): ?int
    {
        $length = $response->headers->get('Content-Length');

        if (is_numeric($length)) {
            return (int) $length;
        }

        return $content === null ? null : strlen($content);
    }
}

