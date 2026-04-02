<?php

namespace App\Tests\Units\Infrastructure\User\Security;

use App\Infrastructure\User\Security\WebhookTokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class WebhookTokenAuthenticatorTest extends TestCase
{
    private const VALID_TOKEN = 'test-token';
    private const VALID_SECRET = 'test-secret';

    public function testSupportsWebhookPostRequest(): void
    {
        $authenticator = $this->createAuthenticator('127.0.0.1');
        $request = Request::create('/api/webhook', 'POST');

        $this->assertTrue($authenticator->supports($request));
    }

    public function testDoesNotSupportNonWebhookRequest(): void
    {
        $authenticator = $this->createAuthenticator('127.0.0.1');
        $request = Request::create('/api/other', 'POST');

        $this->assertFalse($authenticator->supports($request));
    }

    public function testDoesNotSupportNonPostRequest(): void
    {
        $authenticator = $this->createAuthenticator('127.0.0.1');
        $request = Request::create('/api/webhook', 'GET');

        $this->assertFalse($authenticator->supports($request));
    }

    public function testAuthenticateWithExactIpMatch(): void
    {
        $authenticator = $this->createAuthenticator('127.0.0.1,192.168.1.100');
        $request = $this->createAuthenticatedRequest('192.168.1.100', 'test content');

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('webhook', $passport->getUser()->getUserIdentifier());
        $this->assertContains('ROLE_WEBHOOK', $passport->getUser()->getRoles());
    }

    public function testAuthenticateWithCidrRange(): void
    {
        $authenticator = $this->createAuthenticator('172.18.0.0/16');
        $request = $this->createAuthenticatedRequest('172.18.5.10', 'test content');

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('webhook', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateWithMultipleCidrRanges(): void
    {
        $authenticator = $this->createAuthenticator('10.0.0.0/8,172.16.0.0/12,192.168.0.0/16');

        // Test IP in first range
        $request1 = $this->createAuthenticatedRequest('10.5.10.20', 'test content');
        $passport1 = $authenticator->authenticate($request1);
        $this->assertEquals('webhook', $passport1->getUser()->getUserIdentifier());

        // Test IP in second range
        $request2 = $this->createAuthenticatedRequest('172.20.1.50', 'test content');
        $passport2 = $authenticator->authenticate($request2);
        $this->assertEquals('webhook', $passport2->getUser()->getUserIdentifier());

        // Test IP in third range
        $request3 = $this->createAuthenticatedRequest('192.168.100.200', 'test content');
        $passport3 = $authenticator->authenticate($request3);
        $this->assertEquals('webhook', $passport3->getUser()->getUserIdentifier());
    }

    public function testAuthenticateWithMixedIpsAndCidrRanges(): void
    {
        $authenticator = $this->createAuthenticator('127.0.0.1,172.18.0.0/16,192.168.1.100');

        // Test exact IP match
        $request1 = $this->createAuthenticatedRequest('127.0.0.1', 'test content');
        $passport1 = $authenticator->authenticate($request1);
        $this->assertEquals('webhook', $passport1->getUser()->getUserIdentifier());

        // Test CIDR range match
        $request2 = $this->createAuthenticatedRequest('172.18.99.99', 'test content');
        $passport2 = $authenticator->authenticate($request2);
        $this->assertEquals('webhook', $passport2->getUser()->getUserIdentifier());

        // Test another exact IP match
        $request3 = $this->createAuthenticatedRequest('192.168.1.100', 'test content');
        $passport3 = $authenticator->authenticate($request3);
        $this->assertEquals('webhook', $passport3->getUser()->getUserIdentifier());
    }

    public function testAuthenticateFailsWithIpOutsideCidrRange(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid IP address');

        $authenticator = $this->createAuthenticator('172.18.0.0/16');
        $request = $this->createAuthenticatedRequest('172.19.0.1', 'test content');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateFailsWithUnauthorizedIp(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid IP address');

        $authenticator = $this->createAuthenticator('127.0.0.1');
        $request = $this->createAuthenticatedRequest('192.168.1.1', 'test content');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateFailsWithInvalidToken(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid API token');

        $authenticator = $this->createAuthenticator('127.0.0.1');
        $request = $this->createRequest('127.0.0.1', 'wrong-token', 'test content');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateFailsWithInvalidSignature(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid signature');

        $authenticator = $this->createAuthenticator('127.0.0.1');
        $content = 'test content';
        $request = $this->createRequest('127.0.0.1', self::VALID_TOKEN, $content);
        $request->headers->set('X-Signature', 'invalid-signature');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateFailsWithEmptyAllowedIpsList(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid IP address');

        $authenticator = $this->createAuthenticator('');
        $request = $this->createAuthenticatedRequest('127.0.0.1', 'test content');

        $authenticator->authenticate($request);
    }

    public function testCidrRangeBoundaries(): void
    {
        $authenticator = $this->createAuthenticator('172.18.0.0/16');

        // Test first IP in range (network address)
        $request1 = $this->createAuthenticatedRequest('172.18.0.0', 'test content');
        $passport1 = $authenticator->authenticate($request1);
        $this->assertEquals('webhook', $passport1->getUser()->getUserIdentifier());

        // Test last IP in range (broadcast address)
        $request2 = $this->createAuthenticatedRequest('172.18.255.255', 'test content');
        $passport2 = $authenticator->authenticate($request2);
        $this->assertEquals('webhook', $passport2->getUser()->getUserIdentifier());
    }

    public function testCreateFactoryMethod(): void
    {
        $authenticator = WebhookTokenAuthenticator::create(
            self::VALID_TOKEN,
            self::VALID_SECRET,
            '127.0.0.1, 172.18.0.0/16 , 192.168.1.1'
        );

        // Test that whitespace is properly trimmed
        $request1 = $this->createAuthenticatedRequest('127.0.0.1', 'test content');
        $passport1 = $authenticator->authenticate($request1);
        $this->assertEquals('webhook', $passport1->getUser()->getUserIdentifier());

        $request2 = $this->createAuthenticatedRequest('172.18.10.20', 'test content');
        $passport2 = $authenticator->authenticate($request2);
        $this->assertEquals('webhook', $passport2->getUser()->getUserIdentifier());

        $request3 = $this->createAuthenticatedRequest('192.168.1.1', 'test content');
        $passport3 = $authenticator->authenticate($request3);
        $this->assertEquals('webhook', $passport3->getUser()->getUserIdentifier());
    }

    private function createAuthenticator(string $allowedIps): WebhookTokenAuthenticator
    {
        return WebhookTokenAuthenticator::create(self::VALID_TOKEN, self::VALID_SECRET, $allowedIps);
    }

    private function createAuthenticatedRequest(?string $clientIp, string $content): Request
    {
        $request = $this->createRequest($clientIp, self::VALID_TOKEN, $content);
        $signature = hash_hmac('sha256', $content, self::VALID_SECRET);
        $request->headers->set('X-Signature', $signature);

        return $request;
    }

    private function createRequest(?string $clientIp, string $token, string $content): Request
    {
        $request = Request::create('/api/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('X-API-TOKEN', $token);

        if (null !== $clientIp) {
            $request->server->set('REMOTE_ADDR', $clientIp);
        }

        return $request;
    }
}
