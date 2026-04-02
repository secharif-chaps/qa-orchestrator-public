<?php

namespace App\Tests\Integration;

use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for webhook security (IP filtering, token validation, signature validation).
 *
 * Note: IP filtering with CIDR ranges is thoroughly tested in WebhookTokenAuthenticatorTest (unit tests).
 * These integration tests focus on the complete authentication flow from HTTP request to response.
 */
class WebhookSecurityTest extends AbstractApiTestCase
{
    private const WEBHOOK_ENDPOINT = '/api/webhook';
    private const VALID_TOKEN = 'my_super_secret_token';
    private const VALID_SECRET = 'my_ultra_secret_HMAC_key';

    /**
     * Test that webhook accepts requests with valid credentials.
     *
     * Integration tests run from localhost (127.0.0.1) which is in the allowed IP list.
     */
    public function testWebhookAcceptsValidRequest(): void
    {
        $client = static::createClient();

        $payload = [
            'watchFileId' => 'test-watchfile-id',
            'name' => 'ChapsVision',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Un concurrent majeur sur le marché.',
                'en' => 'A major competitor in the market.',
            ],
            'primaryDomain' => 'chapvision.com',
            'score' => 0.95,
            'messageContentId' => null,
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonContains([
            'status' => 'ok',
            'mode' => 'async',
        ]);
    }

    /**
     * Test that webhook rejects requests with invalid token.
     */
    public function testWebhookRejectsInvalidToken(): void
    {
        $client = static::createClient();

        $payload = [
            'test' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => 'invalid-token',
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonContains([
            'error' => 'Invalid API token',
        ]);
    }

    /**
     * Test that webhook rejects requests with missing token.
     */
    public function testWebhookRejectsMissingToken(): void
    {
        $client = static::createClient();

        $payload = [
            'test' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonContains([
            'error' => 'Invalid API token',
        ]);
    }

    /**
     * Test that webhook rejects requests with invalid signature.
     */
    public function testWebhookRejectsInvalidSignature(): void
    {
        $client = static::createClient();

        $payload = [
            'test' => 'data',
        ];

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => 'invalid-signature',
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonContains([
            'error' => 'Invalid signature',
        ]);
    }

    /**
     * Test that webhook rejects requests with missing signature.
     */
    public function testWebhookRejectsMissingSignature(): void
    {
        $client = static::createClient();

        $payload = [
            'test' => 'data',
        ];

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonContains([
            'error' => 'Invalid signature',
        ]);
    }

    /**
     * Test that webhook rejects requests with signature mismatch (tampered payload).
     */
    public function testWebhookRejectsTamperedPayload(): void
    {
        $client = static::createClient();

        $originalPayload = [
            'test' => 'data',
        ];
        $originalPayloadJson = json_encode($originalPayload);
        $this->assertIsString($originalPayloadJson);
        $signature = hash_hmac('sha256', $originalPayloadJson, self::VALID_SECRET);

        // Tamper with payload after signature generation
        $tamperedPayload = [
            'test' => 'tampered_data',
        ];

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $tamperedPayload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonContains([
            'error' => 'Invalid signature',
        ]);
    }

    /**
     * Test complete security chain: all validations must pass.
     */
    public function testWebhookRequiresAllSecurityChecks(): void
    {
        $client = static::createClient();

        $payload = [
            'watchFileId' => 'test-watchfile-id',
            'name' => 'ChapsVision',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Un concurrent majeur sur le marché.',
                'en' => 'A major competitor in the market.',
            ],
            'primaryDomain' => 'chapvision.com',
            'score' => 0.95,
            'messageContentId' => null,
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        // All security checks pass
        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'status' => 'ok',
            'mode' => 'async',
        ]);
    }

    /**
     * Test that webhook enforces security checks in the correct order.
     * Order: IP check → Token check → Signature check.
     */
    public function testWebhookSecurityCheckOrder(): void
    {
        $client = static::createClient();
        $payload = [
            'test' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);

        // Test: Token check fails (invalid token, valid signature)
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);
        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => 'invalid-token',
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonContains([
            'error' => 'Invalid API token',
        ]);

        // Test: Signature check fails last (valid token, invalid signature)
        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => 'invalid-signature',
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonContains([
            'error' => 'Invalid signature',
        ]);
    }
}
