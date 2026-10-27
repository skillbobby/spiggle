<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Support;

use Spiggle\ApiInspector\Models\ApiRequest;

final class CurlCommand
{
    public static function from(ApiRequest $request): string
    {
        $method = strtoupper((string) $request->method);
        $url = (string) ($request->uri ?: $request->path);
        $headers = Body::flattenHeaders($request->request_headers ?? []);

        $skip = [
            'host',
            'content-length',
            'connection',
            'accept-encoding',
            'cookie',
            'set-cookie',
            'authorization',
            'proxy-authorization',
        ];

        $parts = ['curl'];

        if (! in_array($method, ['GET', 'HEAD'], true)) {
            $parts[] = '-X ' . escapeshellarg($method);
        }

        $parts[] = escapeshellarg($url);

        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $skip, true) || $value === Redactor::PLACEHOLDER) {
                continue;
            }

            $parts[] = '-H ' . escapeshellarg($name . ': ' . $value);
        }

        $payload = $request->decodedPayload();
        $body = is_string($payload) ? $payload : Body::encode($payload);

        if (is_string($body) && $body !== '' && ! $request->payload_truncated) {
            $parts[] = '--data-raw ' . escapeshellarg($body);
        }

        return implode(" \\\n  ", $parts);
    }
}
