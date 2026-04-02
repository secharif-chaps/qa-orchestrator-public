<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Infrastructure\User\Security\RateLimitSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;

class RateLimitIntegrationTest extends AbstractApiTestCase
{
    private const string WEBHOOK_ENDPOINT = '/api/webhook';
    private const string VALID_TOKEN = 'my_super_secret_token';
    private const string VALID_SECRET = 'my_ultra_secret_HMAC_key';

    public function testRateLimitHeadersArePresentForAuthenticatedUser(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', \sprintf('/api/watch_files/%s', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('X-RateLimit-Limit');
        $this->assertResponseHasHeader('X-RateLimit-Remaining');
        $this->assertResponseHasHeader('X-RateLimit-Reset');
    }

    #[DataProvider('excludedPathsProvider')]
    public function testRateLimitExcludedPathsDoNotHaveHeaders(string $path): void
    {
        $client = self::createClient();
        $client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->assertResponseNotHasHeader('X-RateLimit-Limit');
        $this->assertResponseNotHasHeader('X-RateLimit-Remaining');
        $this->assertResponseNotHasHeader('X-RateLimit-Reset');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function excludedPathsProvider(): iterable
    {
        yield 'docs' => ['/api/docs'];
        yield 'docs.jsonld' => ['/api/docs.jsonld'];
    }

    public function testRateLimitExceededReturns429(): void
    {
        $user = UserFactory::createOne();
        $userIdentifier = $user->getUserIdentifier();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Exhaust the rate limit for this user
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');

        $limiter = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $userIdentifier)));
        $limiter->reset();
        $limiter->consume(RateLimitSubscriber::AUTHENTICATED_LIMIT);

        // Now make a real HTTP request - should get 429
        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', \sprintf('/api/watch_files/%s', $watchFileId));

        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseHasHeader('Retry-After');
    }

    public function testAuthenticatedUserGetsHigherLimit(): void
    {
        $user = UserFactory::createOne();
        $userIdentifier = $user->getUserIdentifier();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Reset limiter to ensure clean state
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');
        $limiter = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $userIdentifier)));
        $limiter->reset();

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', \sprintf('/api/watch_files/%s', $watchFileId));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('X-RateLimit-Limit', (string) RateLimitSubscriber::AUTHENTICATED_LIMIT);
    }

    public function testDifferentUsersHaveSeparateRateLimits(): void
    {
        $user1 = UserFactory::createOne();
        $user1Identifier = $user1->getUserIdentifier();

        $user2 = UserFactory::createOne();
        $user2Identifier = $user2->getUserIdentifier();

        $watchFile1 = WatchFileFactory::new()
            ->withCreatedBy($user1)
            ->withOwnedBy($user1)
            ->create();

        $watchFile2 = WatchFileFactory::new()
            ->withCreatedBy($user2)
            ->withOwnedBy($user2)
            ->create();

        $watchFile1Id = $watchFile1->getId();
        $watchFile2Id = $watchFile2->getId();

        // Exhaust rate limit for user1 only
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');

        $limiter1 = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $user1Identifier)));
        $limiter1->reset();
        $limiter1->consume(RateLimitSubscriber::AUTHENTICATED_LIMIT);

        // Reset user2's limiter to ensure clean state
        $limiter2 = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $user2Identifier)));
        $limiter2->reset();

        // User1 should be rate limited
        $client1 = $this->createAuthenticatedClient($user1);
        $client1->request('GET', \sprintf('/api/watch_files/%s', $watchFile1Id));
        $this->assertResponseStatusCodeSame(429);

        // User2 should not be rate limited
        $client2 = $this->createAuthenticatedClient($user2);
        $client2->request('GET', \sprintf('/api/watch_files/%s', $watchFile2Id));
        $this->assertResponseIsSuccessful();
    }

    public function testRateLimitAppliesToAllHttpMethods(): void
    {
        $user = UserFactory::createOne();
        $userIdentifier = $user->getUserIdentifier();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Exhaust rate limit
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');

        $limiter = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $userIdentifier)));
        $limiter->reset();
        $limiter->consume(RateLimitSubscriber::AUTHENTICATED_LIMIT);

        $client = $this->createAuthenticatedClient($user);

        // GET collection should be rate limited
        $client->request('GET', '/api/watch_files');
        $this->assertResponseStatusCodeSame(429);

        // POST should also be rate limited
        $client->request('POST', '/api/watch_files', [
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'title' => 'New WatchFile',
            ],
        ]);
        $this->assertResponseStatusCodeSame(429);
    }

    public function testNonApiPathsAreNotRateLimited(): void
    {
        $client = self::createClient();

        // Access a non-API path
        $client->request('GET', '/');

        // Should not have rate limit headers (regardless of response status)
        $this->assertResponseNotHasHeader('X-RateLimit-Limit');
        $this->assertResponseNotHasHeader('X-RateLimit-Remaining');
        $this->assertResponseNotHasHeader('X-RateLimit-Reset');
    }

    public function testRateLimitedResponseHasCorrectContentType(): void
    {
        $user = UserFactory::createOne();
        $userIdentifier = $user->getUserIdentifier();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');

        $limiter = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $userIdentifier)));
        $limiter->reset();
        $limiter->consume(RateLimitSubscriber::AUTHENTICATED_LIMIT);

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', \sprintf('/api/watch_files/%s', $watchFileId), [
            'headers' => [
                'Accept' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
    }

    /**
     * Test that webhook endpoint is rate limited.
     * The webhook uses a virtual "webhook" user after authentication,
     * so it uses the authenticated limiter.
     */
    public function testWebhookEndpointIsRateLimited(): void
    {
        // Exhaust rate limit for the webhook virtual user
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');

        // The webhook authenticator creates a virtual user with identifier 'webhook'
        $limiter = $limiterFactory->create(\sprintf('user_%s', hash('sha256', 'webhook')));
        $limiter->reset();
        $limiter->consume(RateLimitSubscriber::AUTHENTICATED_LIMIT);

        $client = self::createClient();

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
        $payloadJson = json_encode($payload, \JSON_THROW_ON_ERROR);
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

        $this->assertResponseStatusCodeSame(429);
    }

    /**
     * Test that webhook endpoint has rate limit headers.
     * The webhook uses the authenticated limiter because it has a virtual user.
     */
    public function testWebhookEndpointHasRateLimitHeaders(): void
    {
        // Reset limiter for clean state
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');

        // The webhook authenticator creates a virtual user with identifier 'webhook'
        $limiter = $limiterFactory->create(\sprintf('user_%s', hash('sha256', 'webhook')));
        $limiter->reset();

        $client = self::createClient();

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
        $payloadJson = json_encode($payload, \JSON_THROW_ON_ERROR);
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

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('X-RateLimit-Limit');
        $this->assertResponseHasHeader('X-RateLimit-Remaining');
        $this->assertResponseHasHeader('X-RateLimit-Reset');
        $this->assertResponseHeaderSame('X-RateLimit-Limit', (string) RateLimitSubscriber::AUTHENTICATED_LIMIT);
    }

    public function testRateLimitIsPerUserNotGlobal(): void
    {
        $user1 = UserFactory::createOne();
        $user1Identifier = $user1->getUserIdentifier();

        $user2 = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user2)
            ->withOwnedBy($user2)
            ->create();

        $watchFileId = $watchFile->getId();

        // Completely exhaust user1's limit
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');

        $limiter1 = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $user1Identifier)));
        $limiter1->reset();
        $limiter1->consume(RateLimitSubscriber::AUTHENTICATED_LIMIT);

        // User2 should still have full access
        $client = $this->createAuthenticatedClient($user2);
        $client->request('GET', \sprintf('/api/watch_files/%s', $watchFileId));

        $this->assertResponseIsSuccessful();
        // User2 should have nearly full limit (minus 1 for this request)
        $this->assertResponseHeaderSame('X-RateLimit-Limit', (string) RateLimitSubscriber::AUTHENTICATED_LIMIT);
    }

    public function testConsecutiveRequestsDecrementRemaining(): void
    {
        $user = UserFactory::createOne();
        $userIdentifier = $user->getUserIdentifier();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Reset limiter to have a clean state
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');
        $limiter = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $userIdentifier)));
        $limiter->reset();

        $client = $this->createAuthenticatedClient($user);

        // First request - should have limit - 1 remaining
        $client->request('GET', \sprintf('/api/watch_files/%s', $watchFileId));
        $this->assertResponseIsSuccessful();
        $expectedAfterFirst = RateLimitSubscriber::AUTHENTICATED_LIMIT - 1;
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', (string) $expectedAfterFirst);

        // Second request - should have limit - 2 remaining
        $client->request('GET', \sprintf('/api/watch_files/%s', $watchFileId));
        $this->assertResponseIsSuccessful();
        $expectedAfterSecond = RateLimitSubscriber::AUTHENTICATED_LIMIT - 2;
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', (string) $expectedAfterSecond);
    }

    public function testPartiallyConsumedLimitShowsCorrectRemaining(): void
    {
        $user = UserFactory::createOne();
        $userIdentifier = $user->getUserIdentifier();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Consume half the limit
        $limiterFactory = self::getContainer()->get('limiter.api_authenticated');

        $limiter = $limiterFactory->create(\sprintf('user_%s', hash('sha256', $userIdentifier)));
        $limiter->reset();
        $halfLimit = (int) (RateLimitSubscriber::AUTHENTICATED_LIMIT / 2);
        $limiter->consume($halfLimit);

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', \sprintf('/api/watch_files/%s', $watchFileId));

        $this->assertResponseIsSuccessful();

        // Should be half minus one (for this request)
        $expectedRemaining = RateLimitSubscriber::AUTHENTICATED_LIMIT - $halfLimit - 1;
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', (string) $expectedRemaining);
    }
}
