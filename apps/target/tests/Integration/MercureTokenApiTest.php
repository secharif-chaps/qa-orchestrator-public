<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Integration tests for Mercure token generation API endpoint.
 *
 * These tests verify the token generation endpoint returns valid JWT tokens
 * with URI Templates for subscription claims, as specified in ADR-2025-001.
 *
 * Token format: Uses URI Templates instead of explicit resource URLs
 * - /users/{userId}/watch-files/{id}
 * - /users/{userId}/conversations/{id}
 * - /users/{userId}/conversations/{id}/messages
 */
class MercureTokenApiTest extends AbstractApiTestCase
{
    public function testGetMercureTokenRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/security/real-time/token');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetMercureTokenReturnsValidJwtForAuthenticatedUser(): void
    {
        $userEmail = 'test@chapsvision.com';

        $user = UserFactory::new()
            ->with([
                'email' => $userEmail,
                'firstName' => 'Test',
                'lastName' => 'User',
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/security/real-time/token');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();

        $this->assertArrayHasKey('token', $responseData);
        $this->assertArrayHasKey('expires_at', $responseData);
        $this->assertIsString($responseData['token']);
        $this->assertIsInt($responseData['expires_at']);

        // Verify the token is not empty
        $this->assertNotEmpty($responseData['token']);

        // Verify expiration is in the future
        $this->assertGreaterThan(time(), $responseData['expires_at']);

        // Verify the token is a valid JWT
        $this->assertValidMercureJwt($responseData['token'], $userEmail);
    }

    public function testGetMercureTokenContainsUriTemplates(): void
    {
        $userEmail = 'jwt@chapsvision.com';

        $user = UserFactory::new()
            ->with([
                'email' => $userEmail,
                'firstName' => 'JWT',
                'lastName' => 'Test',
            ])
            ->create();

        $userId = $user->getId();

        // Create watchfiles owned by the user (to verify token still works with resources)
        WatchFileFactory::new()->withOwnedBy($user)->create();
        WatchFileFactory::new()->withOwnedBy($user)->create();
        WatchFileFactory::new()->withOwnedBy($user)->create();

        // Create conversations for each watchfile
        $watchFile1 = WatchFileFactory::new()->withOwnedBy($user)->create();
        ConversationFactory::new()->with([
            'watchFile' => $watchFile1,
        ])->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/security/real-time/token');

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $token = $responseData['token'];

        // Decode the JWT to verify payload
        $decoded = JWT::decode($token, new Key($this->getMercureJwtSecret(), 'HS256'));

        $this->assertEquals($userEmail, $decoded->sub);
        $this->assertObjectHasProperty('mercure', $decoded);

        // Should have exactly 3 URI Templates (regardless of number of resources)
        $this->assertCount(3, $decoded->mercure->subscribe);

        // Verify that subscriptions are URI Templates, not explicit resource URLs
        $subscriptions = $decoded->mercure->subscribe;

        // Check for watch-files URI Template
        $watchFileTemplate = "/users/{$userId}/watch-files/{id}";
        $this->assertContains($watchFileTemplate, $subscriptions, 'Token should contain watch-files URI template');

        // Check for conversations URI Template
        $conversationTemplate = "/users/{$userId}/conversations/{id}";
        $this->assertContains($conversationTemplate, $subscriptions, 'Token should contain conversations URI template');

        // Check for conversation messages URI Template
        $messagesTemplate = "/users/{$userId}/conversations/{id}/messages";
        $this->assertContains(
            $messagesTemplate,
            $subscriptions,
            'Token should contain conversation messages URI template'
        );

        // Verify no publish rights
        $this->assertEquals([], $decoded->mercure->publish);
        $this->assertEquals($responseData['expires_at'], $decoded->exp);
    }

    public function testGetMercureTokenExpiresInOneHour(): void
    {
        $user = UserFactory::new()->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/security/real-time/token');

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $expectedExpiry = time() + 3600; // 1 hour from now

        // Allow for a small time difference (1 second) due to test execution time
        $this->assertGreaterThanOrEqual($expectedExpiry - 1, $responseData['expires_at']);
        $this->assertLessThanOrEqual($expectedExpiry + 1, $responseData['expires_at']);
    }

    public function testGetMercureTokenHasConstantSizeRegardlessOfResources(): void
    {
        $user = UserFactory::new()->create();
        $userId = $user->getId();

        // First, get token with no resources
        $client = $this->createAuthenticatedClient($user);
        $response1 = $client->request('GET', '/api/security/real-time/token');

        $this->assertResponseIsSuccessful();
        $decoded1 = JWT::decode($response1->toArray()['token'], new Key($this->getMercureJwtSecret(), 'HS256'));

        // Token should have 3 URI Templates even with no resources
        $this->assertCount(3, $decoded1->mercure->subscribe);

        // Now create 10 watchfiles with conversations
        for ($i = 0; $i < 10; ++$i) {
            $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();
            ConversationFactory::new()->with([
                'watchFile' => $watchFile,
            ])->create();
        }

        // Get token again with 10 resources
        $response2 = $client->request('GET', '/api/security/real-time/token');

        $this->assertResponseIsSuccessful();
        $decoded2 = JWT::decode($response2->toArray()['token'], new Key($this->getMercureJwtSecret(), 'HS256'));

        // Token should still have exactly 3 URI Templates
        $this->assertCount(3, $decoded2->mercure->subscribe);

        // Both tokens should have the same URI Templates
        $this->assertEquals($decoded1->mercure->subscribe, $decoded2->mercure->subscribe);

        // Verify the URI Templates format
        $watchFileTemplate = "/users/{$userId}/watch-files/{id}";
        $conversationTemplate = "/users/{$userId}/conversations/{id}";
        $messagesTemplate = "/users/{$userId}/conversations/{id}/messages";

        $this->assertContains($watchFileTemplate, $decoded2->mercure->subscribe);
        $this->assertContains($conversationTemplate, $decoded2->mercure->subscribe);
        $this->assertContains($messagesTemplate, $decoded2->mercure->subscribe);
    }

    public function testGetMercureTokenGeneratedFreshOnEachCall(): void
    {
        $user = UserFactory::new()->create();

        $client = $this->createAuthenticatedClient($user);

        // First request
        $response1 = $client->request('GET', '/api/security/real-time/token');
        $this->assertResponseIsSuccessful();
        $decoded1 = JWT::decode($response1->toArray()['token'], new Key($this->getMercureJwtSecret(), 'HS256'));

        // Wait a bit to ensure different timestamps
        sleep(1);

        // Second request
        $response2 = $client->request('GET', '/api/security/real-time/token');
        $this->assertResponseIsSuccessful();
        $decoded2 = JWT::decode($response2->toArray()['token'], new Key($this->getMercureJwtSecret(), 'HS256'));

        // Tokens should have different iat (issued at) timestamps
        // This proves tokens are generated fresh, not cached
        $this->assertNotEquals($decoded1->iat, $decoded2->iat, 'Tokens should be generated fresh on each call');
    }

    private function assertValidMercureJwt(string $token, string $userIdentifier): void
    {
        try {
            $decoded = JWT::decode($token, new Key($this->getMercureJwtSecret(), 'HS256'));

            // Verify required claims
            $this->assertEquals($userIdentifier, $decoded->sub);
            $this->assertObjectHasProperty('mercure', $decoded);
            $this->assertObjectHasProperty('subscribe', $decoded->mercure);
            $this->assertObjectHasProperty('publish', $decoded->mercure);
            $this->assertObjectHasProperty('iat', $decoded);
            $this->assertObjectHasProperty('exp', $decoded);
        } catch (\Exception $e) {
            $this->fail('JWT token is not valid: ' . $e->getMessage());
        }
    }

    private function getMercureJwtSecret(): string
    {
        return self::getContainer()->getParameter('mercure.jwt.secret');
    }
}
