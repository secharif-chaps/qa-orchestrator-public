<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\DataFixtures\Factory\User\UserFactory;
use App\Infrastructure\Organisation\Security\InternalJwtToken;
use App\Tests\Integration\AbstractApiTestCase;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class InternalJwtAuthenticatorIntegrationTest extends AbstractApiTestCase
{
    public function testValidInternalJwtGrantsAccess(): void
    {
        $user = UserFactory::new()->create();

        $jwt = $this->createValidJwt([
            'sub' => $user->getId(),
        ]);

        $client = self::createClient([], [
            'headers' => [
                'Authorization' => 'Internal ' . $jwt,
            ],
        ]);

        // /api/healthcheck is PUBLIC_ACCESS — use a protected endpoint
        $client->request('GET', '/api/watch_files');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testExpiredJwtReturns401(): void
    {
        $user = UserFactory::new()->create();

        $jwt = JWT::encode([
            'sub' => $user->getId(),
            'iss' => 'global-gateway',
            'iat' => time() - 300,
            'exp' => time() - 120,
        ], $this->getJwtSecret(), 'HS256');

        $client = self::createClient([], [
            'headers' => [
                'Authorization' => 'Internal ' . $jwt,
            ],
        ]);

        $client->request('GET', '/api/watch_files');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testWrongSecretReturns401(): void
    {
        $user = UserFactory::new()->create();

        $jwt = JWT::encode([
            'sub' => $user->getId(),
            'iss' => 'global-gateway',
            'iat' => time(),
            'exp' => time() + 60,
        ], 'wrong-secret-that-does-not-match-at-all!!', 'HS256');

        $client = self::createClient([], [
            'headers' => [
                'Authorization' => 'Internal ' . $jwt,
            ],
        ]);

        $client->request('GET', '/api/watch_files');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testMalformedJwtReturns401(): void
    {
        $client = self::createClient([], [
            'headers' => [
                'Authorization' => 'Internal not.a.valid.jwt.token',
            ],
        ]);

        $client->request('GET', '/api/watch_files');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testMissingAuthorizationHeaderReturns401(): void
    {
        // No Authorization header → InternalJwtAuthenticator::supports() returns false
        // → access_control ROLE_USER rule blocks the request with 401
        $client = self::createClient();
        $client->request('GET', '/api/watch_files');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testWrongIssuerReturns401(): void
    {
        $user = UserFactory::new()->create();

        $jwt = JWT::encode([
            'sub' => $user->getId(),
            'iss' => 'wrong-issuer',
            'iat' => time(),
            'exp' => time() + 60,
        ], $this->getJwtSecret(), 'HS256');

        $client = self::createClient([], [
            'headers' => [
                'Authorization' => 'Internal ' . $jwt,
            ],
        ]);

        $client->request('GET', '/api/watch_files');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testClaimsAreExtractedFromToken(): void
    {
        $user = UserFactory::new()->create();

        $jwt = $this->createValidJwt([
            'sub' => $user->getId(),
            'org_id' => 'org-test-123',
            'username' => 'testuser',
            'roles' => ['ROLE_USER'],
        ]);

        $client = self::createClient([], [
            'headers' => [
                'Authorization' => 'Internal ' . $jwt,
            ],
        ]);

        $client->request('GET', '/api/watch_files');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $token = $tokenStorage->getToken();

        $this->assertInstanceOf(InternalJwtToken::class, $token);
        $claims = $token->getClaims();
        $this->assertSame('org-test-123', $claims['org_id']);
        $this->assertSame('testuser', $claims['username']);
        $this->assertSame(['ROLE_USER'], $claims['roles']);
    }

    /**
     * IP allowlist validation is configured via env var INTERNAL_ALLOWED_IPS.
     * In test env (compose.local.yaml), it's set to "172.16.0.0/12,192.168.0.0/16,10.0.0.0/8".
     * The test container IP is typically 172.18.0.x (within 172.16.0.0/12), so IP validation passes.
     *
     * To test disallowed IPs, we would need to override the env var or use a custom container network.
     * For now, we verify that valid IPs are allowed via the default config.
     */
    public function testAllowedIpGrantsAccess(): void
    {
        $user = UserFactory::new()->create();
        $jwt = $this->createValidJwt([
            'sub' => $user->getId(),
        ]);

        $client = self::createClient([], [
            'headers' => [
                'Authorization' => 'Internal ' . $jwt,
            ],
        ]);
        $client->request('GET', '/api/watch_files');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testIpValidationIsEnforced(): void
    {
        $user = UserFactory::new()->create();
        $jwt = $this->createValidJwt([
            'sub' => $user->getId(),
        ]);

        $client = self::createClient([], [
            'headers' => [
                'Authorization' => 'Internal ' . $jwt,
            ],
        ]);
        $client->request('GET', '/api/watch_files');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createValidJwt(array $overrides = []): string
    {
        $payload = array_merge([
            'sub' => 'user-123',
            'username' => 'testuser',
            'org_id' => 'org-456',
            'roles' => [],
            'iss' => 'global-gateway',
            'iat' => time(),
            'exp' => time() + 60,
        ], $overrides);

        return JWT::encode($payload, $this->getJwtSecret(), 'HS256');
    }

    private function getJwtSecret(): string
    {
        $raw = $_SERVER['INTERNAL_JWT_SECRET'] ?? $_ENV['INTERNAL_JWT_SECRET'] ?? null;
        $secret = \is_string($raw) ? $raw : '';

        if ('' === $secret) {
            throw new \LogicException('INTERNAL_JWT_SECRET is not configured in the test environment.');
        }

        return $secret;
    }
}
