<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Tests;

use Spiggle\ApiInspector\Models\ApiRequest;
use Spiggle\ApiInspector\Support\Body;
use Spiggle\ApiInspector\Support\CurlCommand;
use Spiggle\ApiInspector\Support\Redactor;

final class SupportTest extends TestCase
{
    public function test_curl_commands_are_safe_and_include_payloads(): void
    {
        $request = new ApiRequest([
            'method' => 'POST',
            'uri' => 'https://app.test/api/orders?include=items',
            'request_headers' => [
                'content-type' => ['application/json'],
                'authorization' => ['[redacted]'],
                'accept' => ['application/json'],
            ],
            'request_payload' => json_encode(['total' => 1999, 'note' => "It's fine"]),
            'payload_type' => 'json',
        ]);

        $curl = CurlCommand::from($request);

        $this->assertStringContainsString('curl', $curl);
        $this->assertStringContainsString("-X 'POST'", $curl);
        $this->assertStringContainsString('https://app.test/api/orders?include=items', $curl);
        $this->assertStringContainsString('content-type', strtolower($curl));
        $this->assertStringNotContainsString('authorization', strtolower($curl));
        $this->assertStringContainsString('--data-raw', $curl);
        $this->assertStringContainsString('s fine', $curl);
    }

    public function test_redaction_supports_wildcards_and_nested_arrays(): void
    {
        $clean = Redactor::data([
            'password' => 'secret',
            'remember_token' => 'secret',
            'nested' => [
                'client_secret' => 'secret',
                'safe' => 'value',
            ],
        ], ['password', '*_token', '*_secret']);

        $this->assertSame('[redacted]', $clean['password']);
        $this->assertSame('[redacted]', $clean['remember_token']);
        $this->assertSame('[redacted]', $clean['nested']['client_secret']);
        $this->assertSame('value', $clean['nested']['safe']);
    }

    public function test_body_truncation_keeps_valid_utf8(): void
    {
        $truncated = false;
        $value = Body::truncate(str_repeat('é', 100), 51, $truncated);

        $this->assertTrue($truncated);
        $this->assertStringContainsString('truncated by API Inspector', (string) $value);
        $this->assertTrue(mb_check_encoding((string) $value, 'UTF-8'));
    }

    public function test_binary_detection_handles_content_types(): void
    {
        $this->assertTrue(Body::isBinary('image/png', 'png'));
        $this->assertFalse(Body::isBinary('application/json', '{"ok":true}'));
        $this->assertFalse(Body::isBinary('text/plain', 'hello'));
        $this->assertTrue(Body::isBinary(null, "\x00binary"));
    }
}
