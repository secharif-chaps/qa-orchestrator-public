<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFileStatus;
use App\Infrastructure\User\Security\TestAuthenticator;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Webmozart\Assert\Assert;

class WatchFileOwnerQuotaApiTest extends AbstractApiTestCase
{
    public function testCanCreateWatchFileWhenQuotaNotReached(): void
    {
        $user = UserFactory::new()->create();
        $userId = $user->getId();
        Assert::string($userId);

        $this->createWatchFilesForUser($user, WatchFileStatus::ENABLED, 20);

        $this->assertQuotaCheckPasses($userId, '/api/watch_files', 'POST', [
            'content' => 'Test message',
        ]);
    }

    public function testCannotCreateWatchFileWhenQuotaExceeded(): void
    {
        $user = UserFactory::new()->create();
        $userId = $user->getId();
        Assert::string($userId);

        $this->createWatchFilesForUser($user, WatchFileStatus::ENABLED, 100);

        $client = $this->createAuthenticatedClientWithUserId($userId);
        $response = $client->request('POST', '/api/watch_files', [
            'json' => [
                'content' => 'Test message',
            ],
        ]);

        $this->assertQuotaExceededResponse($response);
    }

    public function testCannotUnarchiveWatchFileWhenQuotaExceeded(): void
    {
        $user = UserFactory::new()->create();
        $userId = $user->getId();
        Assert::string($userId);

        $this->createWatchFilesForUser($user, WatchFileStatus::ENABLED, 100);

        $archivedWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClientWithUserId($userId);
        $response = $client->request(
            'POST',
            \sprintf('/api/watch_files/%s/status/%s', $archivedWatchFile->getId(), WatchFileStatus::ENABLED->value),
            [
                'json' => [],
            ]
        );

        $this->assertQuotaExceededResponse($response);
    }

    public function testCanUnarchiveWatchFileWhenQuotaNotReached(): void
    {
        $user = UserFactory::new()->create();
        $userId = $user->getId();
        Assert::string($userId);

        $this->createWatchFilesForUser($user, WatchFileStatus::DRAFT, 99);

        $archivedWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::ARCHIVED,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create an active source (required for activation)
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $archivedWatchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClientWithUserId($userId);
        $response = $client->request(
            'POST',
            \sprintf('/api/watch_files/%s/status/%s', $archivedWatchFile->getId(), WatchFileStatus::ENABLED->value),
            [
                'json' => [],
            ]
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(WatchFileStatus::ENABLED->value, $data['status']);
    }

    public function testExcludesArchivedWatchFilesFromQuota(): void
    {
        $user = UserFactory::new()->create();
        $userId = $user->getId();
        Assert::string($userId);

        $this->createWatchFilesForUser($user, WatchFileStatus::ENABLED, 20);
        $this->createWatchFilesForUser($user, WatchFileStatus::ARCHIVED, 10);

        $this->assertQuotaCheckPasses($userId, '/api/watch_files', 'POST', [
            'content' => 'Test message',
        ]);
    }

    public function testQuotaIsPerOwnerOnly(): void
    {
        $user1 = UserFactory::new()->create();
        $user2 = UserFactory::new()->create();

        $this->createWatchFilesForUser($user1, WatchFileStatus::ENABLED, 100);
        $this->createWatchFilesForUser($user2, WatchFileStatus::ENABLED, 10);

        $userId1 = $user1->getId();
        Assert::string($userId1);
        $client1 = $this->createAuthenticatedClientWithUserId($userId1);
        $client1->request('POST', '/api/watch_files', [
            'json' => [
                'content' => 'Test message',
            ],
        ]);
        $this->assertResponseStatusCodeSame(429);

        $userId2 = $user2->getId();
        Assert::string($userId2);
        $this->assertQuotaCheckPasses($userId2, '/api/watch_files', 'POST', [
            'content' => 'Test message',
        ]);
    }

    public function testCanCreateWhenQuotaIsUnlimited(): void
    {
        $user = UserFactory::new()->create();
        $userId = $user->getId();
        Assert::string($userId);

        $this->createWatchFilesForUser($user, WatchFileStatus::DRAFT, 101);

        $client = $this->createAuthenticatedClientWithUserId($userId);
        $client->request('POST', '/api/watch_files', [
            'json' => [
                'content' => 'Test message',
            ],
        ]);

        $this->assertResponseStatusCodeSame(429);
    }

    private function createWatchFilesForUser(User $user, WatchFileStatus $status, int $count): void
    {
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => $status,
            ])
            ->many($count)
            ->create();
    }

    private function createAuthenticatedClientWithUserId(string $userId): Client
    {
        return self::createClient([], [
            'headers' => [
                TestAuthenticator::HEADER_TEST_AUTH_USER_ID => $userId,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $json
     */
    private function assertQuotaCheckPasses(string $userId, string $uri, string $method, array $json = []): void
    {
        $client = $this->createAuthenticatedClientWithUserId($userId);
        $response = $client->request($method, $uri, [
            'json' => $json,
        ]);

        $statusCode = $response->getStatusCode();
        $this->assertNotEquals(429, $statusCode, 'Quota check should pass - status should not be 429');
    }

    private function assertQuotaExceededResponse(?ResponseInterface $response): void
    {
        $this->assertNotNull($response);
        $this->assertResponseStatusCodeSame(429);

        $data = $response->toArray(false);
        $this->assertArrayHasKey('detail', $data);
        $this->assertIsString($data['detail']);
        $this->assertStringContainsString('cannot own more than 100 non-archived watchfiles', $data['detail']);
    }
}
