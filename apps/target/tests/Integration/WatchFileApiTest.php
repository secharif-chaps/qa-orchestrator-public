<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WatchFileApiTest extends AbstractApiTestCase
{
    /**
     * @param array<string, int|string> $parameters
     */
    private function getTranslatedQuotaMessage(string $key, array $parameters, string $locale = 'en'): string
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);

        return $translator->trans($key, $parameters, 'messages', $locale);
    }

    public function testPostWatchFileFavorite(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', \sprintf('/api/watch_files/%s/favorite', $watchFile->getId()), [
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Verify the favorite was created in the database
        $this->assertTrue($this->isWatchFileFavoritedByUser($watchFile, $user));
    }

    public function testPostWatchFileFavoriteAlreadyFavorited(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/favorite", [
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Verify the favorite still exists (idempotent operation)
        $this->assertTrue($this->isWatchFileFavoritedByUser($watchFile, $user));
    }

    public function testPostWatchFileFavoriteUnauthorized(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create()
        ;

        $unauthorizedUser = UserFactory::createOne();
        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/favorite", [
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPostWatchFileFavoriteNotFound(): void
    {
        $user = UserFactory::createOne();

        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$nonExistentId}/favorite", [
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteWatchFileFavorite(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);

        $client->request('DELETE', "/api/watch_files/{$watchFile->getId()}/favorite");

        $this->assertResponseStatusCodeSame(204);

        // Verify the favorite was removed from the database
        $this->assertFalse($this->isWatchFileFavoritedByUser($watchFile, $user));
    }

    public function testDeleteWatchFileFavoriteNotFavorited(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $client->request('DELETE', "/api/watch_files/{$watchFile->getId()}/favorite");

        $this->assertResponseStatusCodeSame(204);

        // Verify the watch file is still not favorited
        $this->assertFalse($this->isWatchFileFavoritedByUser($watchFile, $user));
    }

    public function testDeleteWatchFileFavoriteUnauthorized(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create()
        ;

        $unauthorizedUser = UserFactory::createOne();
        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('DELETE', "/api/watch_files/{$watchFile->getId()}/favorite");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteWatchFileFavoriteNotFound(): void
    {
        $user = UserFactory::createOne();

        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('DELETE', "/api/watch_files/{$nonExistentId}/favorite");

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetWatchFileWithFavoriteStatus(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('isFavorite', $data);
        $this->assertTrue($data['isFavorite']);
    }

    public function testGetWatchFileWithoutFavoriteStatus(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('isFavorite', $data);
        $this->assertFalse($data['isFavorite']);
    }

    /**
     * Test that GET /api/watch_files/{id} returns 403 Forbidden for unauthorized users.
     * This tests the security fix for TAR-947.
     */
    public function testGetWatchFileUnauthorized(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Private Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Test that GET /api/watch_files/{id} returns 404 for non-existent watch file.
     */
    public function testGetWatchFileNotFound(): void
    {
        $user = UserFactory::createOne();
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', "/api/watch_files/{$nonExistentId}");

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Test that PATCH /api/watch_files/{id} returns 403 Forbidden for unauthorized users.
     * This tests the security fix for TAR-947.
     */
    public function testPatchWatchFileUnauthorized(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Private Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'json' => [
                'name' => 'Hacked Name',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Test that viewers cannot PATCH a watch file (only editors and owners can).
     */
    public function testPatchWatchFileViewerForbidden(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'Shared Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($viewer);
        $client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'json' => [
                'name' => 'Viewer Cannot Change This',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Test that editors can PATCH a watch file.
     */
    public function testPatchWatchFileEditorAllowed(): void
    {
        $owner = UserFactory::createOne();
        $editor = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($editor, WatchFileUserRole::EDITOR)
            ->with([
                'name' => 'Original Name',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($editor);
        $response = $client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'json' => [
                'name' => 'Updated By Editor',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals('Updated By Editor', $data['name']);
    }

    /**
     * Test that viewers can view a watch file (WATCH_FILE_VIEW permission).
     */
    public function testGetWatchFileViewerAllowed(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'Shared Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($viewer);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals('Shared Watch File', $data['name']);
    }

    private function isWatchFileFavoritedByUser(WatchFile $watchFile, User $user): bool
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $freshWatchFile = $em->find(WatchFile::class, $watchFile->getId());
        if (null === $freshWatchFile) {
            return false;
        }

        $watchFileFavorites = $freshWatchFile->getUserFavorites();
        foreach ($watchFileFavorites as $watchFileFavorite) {
            if ($watchFileFavorite->getUser()?->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    public function testGetCollectionSortByNameAsc(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Alpha Watch File',
                ],
                [
                    'name' => 'Beta Watch File',
                ],
                [
                    'name' => 'Charlie Watch File',
                ],
            ])
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[name]=asc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('totalItems', $data);
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles);

        // Verify ascending order by name
        $this->assertSame('Alpha Watch File', $watchFiles[0]['name']);
        $this->assertSame('Beta Watch File', $watchFiles[1]['name']);
        $this->assertSame('Charlie Watch File', $watchFiles[2]['name']);
    }

    public function testGetCollectionSortByNameDesc(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Alpha Watch File',
                ],
                [
                    'name' => 'Beta Watch File',
                ],
                [
                    'name' => 'Charlie Watch File',
                ],
            ])
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[name]=desc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles);

        // Verify descending order by name
        $this->assertSame('Charlie Watch File', $watchFiles[0]['name']);
        $this->assertSame('Beta Watch File', $watchFiles[1]['name']);
        $this->assertSame('Alpha Watch File', $watchFiles[2]['name']);
    }

    public function testGetCollectionSortByNameWithAccentsAsc(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Andre Watch File',
                ],
                [
                    'name' => 'Émile Watch File',
                ],
                [
                    'name' => 'Zoe Watch File',
                ],
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[name]=asc');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles);

        $names = array_column($watchFiles, 'name');
        $this->assertEquals(['Andre Watch File', 'Émile Watch File', 'Zoe Watch File'], $names);
    }

    public function testGetCollectionSortByNameWithAccentsDesc(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Andre Watch File',
                ],
                [
                    'name' => 'Émile Watch File',
                ],
                [
                    'name' => 'Zoe Watch File',
                ],
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[name]=desc');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles);

        $names = array_column($watchFiles, 'name');
        $this->assertEquals(['Zoe Watch File', 'Émile Watch File', 'Andre Watch File'], $names);
    }

    public function testGetCollectionSortByStatus(): void
    {
        $user = UserFactory::createOne();

        // Create watch files with different statuses
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Draft File',
                    'status' => WatchFileStatus::DRAFT,
                ],
                [
                    'name' => 'Enabled File',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Archived File',
                    'status' => WatchFileStatus::ARCHIVED,
                ],
            ])
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[status]=asc&includeArchived=true');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles);

        // Verify statuses are sorted (archived, draft, enabled alphabetically)
        $statuses = array_column($watchFiles, 'status');
        $this->assertSame([
            WatchFileStatus::DRAFT->value,
            WatchFileStatus::ARCHIVED->value,
            WatchFileStatus::ENABLED->value,
        ], $statuses);
    }

    public function testGetCollectionSortByCountAccess(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'File with 1 access',
            ])
            ->create()
        ;

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUser(UserFactory::createOne(), WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'File with 2 accesses',
            ])
            ->create()
        ;

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUser(UserFactory::createOne(), WatchFileUserRole::VIEWER)
            ->withUser(UserFactory::createOne(), WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'File with 3 accesses',
            ])
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[countAccess]=desc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles);

        // Verify descending order by access count
        $this->assertSame('File with 3 accesses', $watchFiles[0]['name']);
        $this->assertSame('File with 2 accesses', $watchFiles[1]['name']);
        $this->assertSame('File with 1 access', $watchFiles[2]['name']);
    }

    public function testGetCollectionSortWithFavoritesLoggedUser(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File',
            ])
            ->create()
        ;

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite File',
            ])
            ->create()
        ;

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Another Regular File',
            ])
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[name]=asc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles);

        // Verify favorite appears first, then sorted by name
        $this->assertSame('Favorite File', $watchFiles[0]['name']);
        $this->assertTrue($watchFiles[0]['isFavorite']);

        $this->assertSame('Another Regular File', $watchFiles[1]['name']);
        $this->assertFalse($watchFiles[1]['isFavorite']);

        $this->assertSame('Regular File', $watchFiles[2]['name']);
        $this->assertFalse($watchFiles[2]['isFavorite']);
    }

    public function testGetCollectionMultipleSortParameters(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Beta File',
                    'status' => WatchFileStatus::ENABLED,
                    'updatedAt' => new \DateTimeImmutable('2025-07-10 10:00:00'),
                ],
                [
                    'name' => 'Alpha File',
                    'status' => WatchFileStatus::ENABLED,
                    'updatedAt' => new \DateTimeImmutable('2025-07-11 11:00:00'),
                ],
                [
                    'name' => 'Charlie File',
                    'status' => WatchFileStatus::DRAFT,
                    'updatedAt' => new \DateTimeImmutable('2025-07-11 12:00:00'),
                ],
            ])
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[status]=asc&sort[updatedAt]=asc&sort[name]=asc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles);

        // Verify sorting by status, then updatedAt, then name
        $this->assertSame('Charlie File', $watchFiles[0]['name']);
        $this->assertSame(WatchFileStatus::DRAFT->value, $watchFiles[0]['status']);

        $this->assertSame('Beta File', $watchFiles[1]['name']);
        $this->assertSame(WatchFileStatus::ENABLED->value, $watchFiles[1]['status']);

        $this->assertSame('Alpha File', $watchFiles[2]['name']);
        $this->assertSame(WatchFileStatus::ENABLED->value, $watchFiles[2]['status']);
    }

    public function testGetCollectionInvalidSortParameter(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Test File',
            ])
            ->create()
        ;

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?sort[invalidField]=asc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(1, $watchFiles);
        $this->assertSame('Test File', $watchFiles[0]['name']);
    }

    public function testPostChangeWatchFileStatusDraftToEnabled(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Test Watch File',
                'status' => WatchFileStatus::DRAFT,
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
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testPostChangeWatchFileStatusEnabledToArchived(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Test Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/archived");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('archived', $data['status']);
    }

    public function testPostChangeWatchFileStatusArchivedToDraft(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Test Watch File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/draft");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('draft', $data['status']);
    }

    public function testPostChangeWatchFileStatusSameStatus(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Test Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/draft");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('draft', $data['status']);
    }

    public function testPostChangeWatchFileStatusInvalidStatus(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Test Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/invalid");

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPostChangeWatchFileStatusUnauthorized(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Private Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPostChangeWatchFileStatusNotFound(): void
    {
        $user = UserFactory::createOne();
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$nonExistentId}/status/enabled");

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetWatchFileShareUsers(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();
        $editor = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->withUser($editor, WatchFileUserRole::EDITOR)
            ->with([
                'name' => 'Shared Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/share");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('totalItems', $data);
        $this->assertEquals(3, $data['totalItems']);
        $this->assertArrayHasKey('member', $data);
        $members = $data['member'];
        $this->assertCount(3, $members);

        $roles = array_column($members, 'role');
        $this->assertContains('owner', $roles);
        $this->assertContains('viewer', $roles);
        $this->assertContains('editor', $roles);
    }

    public function testGetWatchFileShareUsersUnauthorized(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Private Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}/share");

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetWatchFileShareUsersNotFound(): void
    {
        $user = UserFactory::createOne();
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', "/api/watch_files/{$nonExistentId}/share");

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPostShareWatchFileWithUsers(): void
    {
        $owner = UserFactory::createOne();
        $userToShare1 = UserFactory::createOne();
        $userToShare2 = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Watch File to Share',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $shareData = [
            'member' => [
                [
                    'userId' => $userToShare1->getId(),
                    'role' => 'viewer',
                ],
                [
                    'userId' => $userToShare2->getId(),
                    'role' => 'editor',
                ],
            ],
        ];

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/share", [
            'json' => $shareData,
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertArrayHasKey('totalItems', $data);

        $members = $data['member'];
        $this->assertCount(2, $members);

        $roles = array_column($members, 'role');
        $this->assertContains('viewer', $roles);
        $this->assertContains('editor', $roles);
    }

    public function testPostShareWatchFileWithSameUser(): void
    {
        $owner = UserFactory::createOne();
        $userToShare = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($userToShare, WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'Already Shared Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $shareData = [
            'member' => [
                [
                    'userId' => $userToShare->getId(),
                    'role' => 'editor',
                ],
            ],
        ];

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/share", [
            'json' => $shareData,
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $members = $data['member'];
        $userMember = array_filter($members, fn ($member) => $member['user']['id'] === $userToShare->getId());
        $userMember = reset($userMember);

        $this->assertEquals('editor', $userMember['role']);
    }

    public function testPostShareWatchFileEmptyList(): void
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $shareData = [
            'member' => [],
        ];

        $client = $this->createAuthenticatedClient($owner);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/share", [
            'json' => $shareData,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testPostShareWatchFileInvalidRole(): void
    {
        $owner = UserFactory::createOne();
        $userToShare = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $shareData = [
            'member' => [
                [
                    'userId' => $userToShare->getId(),
                    'role' => 'invalid_role',
                ],
            ],
        ];

        $client = $this->createAuthenticatedClient($owner);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/share", [
            'json' => $shareData,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testPostShareWatchFileUnauthorized(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();
        $userToShare = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Private Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $shareData = [
            'member' => [
                [
                    'userId' => $userToShare->getId(),
                    'role' => 'viewer',
                ],
            ],
        ];

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/share", [
            'json' => $shareData,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPostShareWatchFileNotFound(): void
    {
        $user = UserFactory::createOne();
        $userToShare = UserFactory::createOne();
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $shareData = [
            'member' => [
                [
                    'userId' => $userToShare->getId(),
                    'role' => 'viewer',
                ],
            ],
        ];

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$nonExistentId}/share", [
            'json' => $shareData,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    private function getAndAssertWatchFileUserId(
        Client $client,
        string $watchFileId,
        string $userId,
        WatchFileUserRole $role = WatchFileUserRole::VIEWER,
    ): string {
        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/share");
        $data = $response->toArray();

        $this->assertResponseStatusCodeSame(200);

        $this->assertArrayHasKey('member', $data);
        $this->assertIsArray($data['member']);

        $watchFileUserId = null;
        foreach ($data['member'] as $watchFileUser) {
            if (
                \is_array($watchFileUser)
                && isset($watchFileUser['role'])
                && $watchFileUser['role'] === $role->value
                && isset($watchFileUser['user']['id'])
                && $watchFileUser['user']['id'] === $userId
                && !empty($watchFileUser['id'])
                && \is_string($watchFileUser['id'])
            ) {
                $watchFileUserId = $watchFileUser['id'];
                break;
            }
        }

        $this->assertIsString($watchFileUserId);
        $this->assertNotEmpty($watchFileUserId);

        return $watchFileUserId;
    }

    public function testDeleteWatchFileShareSuccess(): void
    {
        $owner = UserFactory::createOne();

        $sharedUser = UserFactory::createOne();
        $sharedUserId = $sharedUser->getId();
        $this->assertIsString($sharedUserId);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($sharedUser, WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'Shared Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $watchFileId = $watchFile->getId();

        $client = $this->createAuthenticatedClient($owner);
        $watchFileUserId = $this->getAndAssertWatchFileUserId($client, $watchFileId, $sharedUserId);

        $client->request('DELETE', "/api/watch_files/{$watchFileId}/share/{$watchFileUserId}");

        // Should return 204 No Content on successful deletion
        $this->assertResponseStatusCodeSame(204);

        // Verify the user was removed by checking the share list
        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/share");
        $data = $response->toArray();

        // Should only have the owner left
        $this->assertEquals(1, $data['totalItems']);
        $members = $data['member'];
        $roles = array_column($members, 'role');
        $this->assertContains('owner', $roles);
        $this->assertNotContains('viewer', $roles);
    }

    public function testDeleteWatchFileShareUnauthorizedUser(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner);

        $ownerRelation = WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $owner,
                'role' => WatchFileUserRole::OWNER,
            ])
            ->create();

        $sharedUser = UserFactory::createOne();
        $sharedUserRelation = WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $sharedUser,
                'role' => WatchFileUserRole::VIEWER,
            ])
            ->create();

        $sharedUserRelationId = $sharedUserRelation->getId();

        $watchFile->with([
            'name' => 'Shared Watch File',
            'status' => WatchFileStatus::ENABLED,
            'watchFileUsers' => [$ownerRelation, $sharedUserRelation],
        ]);

        $unauthorizedUser = UserFactory::createOne();

        $watchFile = $watchFile
            ->withCreatedBy($owner)
            ->with([
                'name' => 'Shared Watch File',
                'status' => WatchFileStatus::ENABLED,
                'watchFileUsers' => [$ownerRelation, $sharedUserRelation],
            ])
            ->create();

        $watchFileId = $watchFile->getId();

        $client = $this->createAuthenticatedClient($unauthorizedUser);

        // Unauthorized user tries to remove access
        $client->request('DELETE', "/api/watch_files/{$watchFileId}/share/{$sharedUserRelationId}");

        // Should return 404 Not Found
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteWatchFileShareNonExistentWatchFile(): void
    {
        $user = UserFactory::createOne();
        $nonExistentWatchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $nonExistentShareId = '123e4567-e89b-12d3-a456-426614174000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('DELETE', "/api/watch_files/{$nonExistentWatchFileId}/share/{$nonExistentShareId}");

        // Should return 404 Not Found for non-existent watchFile
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteWatchFileShareNonExistentWatchFileUser(): void
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Test Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $nonExistentShareId = '123e4567-e89b-12d3-a456-426614174000';

        $client = $this->createAuthenticatedClient($owner);
        $client->request('DELETE', "/api/watch_files/{$watchFile->getId()}/share/{$nonExistentShareId}");

        // Should return 404 Not Found for non-existent watchFileUser
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteWatchFileShareOwnerRole(): void
    {
        $owner = UserFactory::createOne();
        $ownerId = $owner->getId();
        $this->assertIsString($ownerId);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Owner Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $watchFileId = $watchFile->getId();

        $client = $this->createAuthenticatedClient($owner);
        $watchFileUserId = $this->getAndAssertWatchFileUserId(
            $client,
            $watchFileId,
            $ownerId,
            WatchFileUserRole::OWNER
        );

        $client->request('DELETE', "/api/watch_files/{$watchFileId}/share/{$watchFileUserId}");

        $this->assertResponseStatusCodeSame(422);
    }

    public function testDeleteWatchFileShareViewerCannotRemoveOtherUser(): void
    {
        $owner = UserFactory::createOne();

        $viewer1 = UserFactory::createOne();

        $viewer2 = UserFactory::createOne();
        $viewer2Id = $viewer2->getId();
        $this->assertIsString($viewer2Id);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer1, WatchFileUserRole::VIEWER)
            ->withUser($viewer2, WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'Multi-user Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $watchFileId = $watchFile->getId();

        $client = $this->createAuthenticatedClient($owner);
        $watchFileUser2Id = $this->getAndAssertWatchFileUserId($client, $watchFileId, $viewer2Id);

        // viewer1 tries to remove viewer2
        $client = $this->createAuthenticatedClient($viewer1);
        $client->request('DELETE', "/api/watch_files/{$watchFileId}/share/{$watchFileUser2Id}");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteWatchFileShareEditorCanRemoveViewer(): void
    {
        $owner = UserFactory::createOne();

        $editor = UserFactory::createOne();

        $viewer = UserFactory::createOne();
        $viewerId = $viewer->getId();
        $this->assertIsString($viewerId);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($editor, WatchFileUserRole::EDITOR)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'Editor Permissions Test Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $watchFileId = $watchFile->getId();

        $client = $this->createAuthenticatedClient($editor);
        $watchFileUserId = $this->getAndAssertWatchFileUserId($client, $watchFileId, $viewerId);

        // editor removes viewer
        $client->request('DELETE', "/api/watch_files/{$watchFileId}/share/{$watchFileUserId}");

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteWatchFileShareInvalidUuid(): void
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Test Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $invalidShareId = 'invalid-uuid-format';

        $client = $this->createAuthenticatedClient($owner);
        $client->request('DELETE', "/api/watch_files/{$watchFile->getId()}/share/{$invalidShareId}");

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPostChangeWatchFileStatusExceedsActiveWatchFileQuota(): void
    {
        $user = UserFactory::createOne();

        // Create 2 active watch files (reaching the limit of 2 per user)
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        // Create a third watch file in DRAFT status
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File To Activate',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        // Should return 429 Too Many Requests due to quota exceeded
        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.watchfile_max_active_per_user', [
            'limit' => 2,
            'current' => 2,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusExceedsActiveSourcesQuota(): void
    {
        $user = UserFactory::createOne();

        // Create a watch file in DRAFT status
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File With Many Sources',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create 21 active sources (exceeding the limit of 20 per watchfile)
        for ($i = 1; $i <= 21; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Active Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://example{$i}.com",
                    'primaryDomain' => "example{$i}.com",
                    'status' => SourceStatus::ACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        // Should return 429 Too Many Requests due to quota exceeded
        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.source_max_active_per_watchfile', [
            'limit' => 20,
            'current' => 21,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusSourcesQuotaNotExceeded(): void
    {
        $user = UserFactory::createOne();

        // Create a watch file in DRAFT status
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File With Few Sources',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create only 5 active sources (under the limit of 20)
        for ($i = 1; $i <= 5; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Active Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://example{$i}.com",
                    'primaryDomain' => "example{$i}.com",
                    'status' => SourceStatus::ACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        // Should succeed since we're under the quota
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testPostChangeWatchFileStatusSourcesQuotaExactlyAtLimit(): void
    {
        $user = UserFactory::createOne();

        // Create a watch file in DRAFT status
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File At Source Limit',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create exactly 20 active sources (the limit, which is now inclusive)
        for ($i = 1; $i <= 20; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Active Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://example{$i}.com",
                    'primaryDomain' => "example{$i}.com",
                    'status' => SourceStatus::ACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        // Should succeed since the quota is inclusive and allows exactly 20 sources
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testPostChangeWatchFileStatusSourcesQuotaWithInactiveSources(): void
    {
        $user = UserFactory::createOne();

        // Create a watch file in DRAFT status
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File With Mixed Sources',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create 10 active sources and 15 inactive ones
        for ($i = 1; $i <= 10; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Active Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://active{$i}.com",
                    'primaryDomain' => "active{$i}.com",
                    'status' => SourceStatus::ACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        for ($i = 1; $i <= 15; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Inactive Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://inactive{$i}.com",
                    'primaryDomain' => "inactive{$i}.com",
                    'status' => SourceStatus::INACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        // Should succeed - only active sources count (10 < 20)
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testPostChangeWatchFileStatusWithCorruptedSourcesExceedingQuota(): void
    {
        $user = UserFactory::createOne();

        // Create an ENABLED watch file with 25 active sources (exceeding quota)
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Corrupted Watch File With Too Many Sources',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        // Create 25 active sources (exceeding the limit of 20)
        for ($i = 1; $i <= 25; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Corrupted Active Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://corrupted{$i}.com",
                    'primaryDomain' => "corrupted{$i}.com",
                    'status' => SourceStatus::ACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);

        // User should be able to deactivate watchfile even when sources quota is exceeded
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/draft");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('draft', $data['status']);
    }

    public function testPostChangeWatchFileStatusCannotReactivateWhenSourcesExceedQuota(): void
    {
        $user = UserFactory::createOne();

        // Create a DRAFT watch file with 25 active sources (exceeding quota)
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft Watch File With Too Many Sources',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create 25 active sources (exceeding the limit of 20)
        for ($i = 1; $i <= 25; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Active Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://example{$i}.com",
                    'primaryDomain' => "example{$i}.com",
                    'status' => SourceStatus::ACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);

        // Should not be able to activate watchfile when sources quota is exceeded
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(429);
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.source_max_active_per_watchfile', [
            'limit' => 20,
            'current' => 25,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusCanKeepEnabledWhenSourcesExceedQuota(): void
    {
        $user = UserFactory::createOne();

        // Create an ENABLED watch file with 25 active sources (exceeding quota)
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Already Enabled With Too Many Sources',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        // Create 25 active sources (exceeding the limit of 20)
        for ($i = 1; $i <= 25; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Active Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://example{$i}.com",
                    'primaryDomain' => "example{$i}.com",
                    'status' => SourceStatus::ACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);

        // Should be able to keep watchfile enabled (idempotent) even when sources quota is exceeded
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testPostChangeWatchFileStatusBothQuotasExceeded(): void
    {
        $user = UserFactory::createOne();

        // Create 2 active watch files (reaching user quota)
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        // Create a draft watch file with 25 active sources (exceeding sources quota)
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft Watch File With Too Many Sources',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        for ($i = 1; $i <= 25; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => "Active Source {$i}",
                    'type' => SourceType::WEBSITE,
                    'url' => "https://example{$i}.com",
                    'primaryDomain' => "example{$i}.com",
                    'status' => SourceStatus::ACTIVE,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        // Should fail with watchfile quota first (checked before sources quota)
        $this->assertResponseStatusCodeSame(429);
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.watchfile_max_active_per_user', [
            'limit' => 2,
            'current' => 2,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusFromActiveToDraftThenEnableAnother(): void
    {
        $user = UserFactory::createOne();

        // Create 2 active watch files (reaching the quota limit)
        $activeWatchFile1 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Active Watch File 1',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Active Watch File 2',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        // Create a third watch file in DRAFT status with referenceSubject
        $draftWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft Watch File',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create an active source for the draft watch file (required for activation)
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $draftWatchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Step 1: Change one active watch file to DRAFT
        $response = $client->request('POST', "/api/watch_files/{$activeWatchFile1->getId()}/status/draft");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('draft', $data['status']);

        // Step 2: Now we should be able to enable the third watch file
        $response = $client->request('POST', "/api/watch_files/{$draftWatchFile->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testPostChangeWatchFileStatusFromActiveToArchivedThenEnableAnother(): void
    {
        $user = UserFactory::createOne();

        // Create 2 active watch files (reaching the quota limit)
        $activeWatchFile1 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Active Watch File 1',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Active Watch File 2',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        // Create a third watch file in DRAFT status with referenceSubject
        $draftWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft Watch File',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create an active source for the draft watch file (required for activation)
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $draftWatchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Step 1: Archive one active watch file
        $response = $client->request('POST', "/api/watch_files/{$activeWatchFile1->getId()}/status/archived");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('archived', $data['status']);

        // Step 2: Now we should be able to enable the draft watch file
        $response = $client->request('POST', "/api/watch_files/{$draftWatchFile->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testPostChangeWatchFileStatusQuotaNotExceededWithOnlyOneActive(): void
    {
        $user = UserFactory::createOne();

        // Create only 1 active watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Active Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        // Create a draft watch file with referenceSubject
        $draftWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft Watch File',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create an active source for the draft watch file (required for activation)
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $draftWatchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Should succeed since we're under the quota
        $response = $client->request('POST', "/api/watch_files/{$draftWatchFile->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testPostChangeWatchFileStatusQuotaExactlyAtLimit(): void
    {
        $user = UserFactory::createOne();

        // Create exactly 2 active watch files (the quota limit)
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        // Try to enable a draft watch file while at quota limit
        $draftWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$draftWatchFile->getId()}/status/enabled");

        // Should fail with quota exceeded
        $this->assertResponseStatusCodeSame(429);
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.watchfile_max_active_per_user', [
            'limit' => 2,
            'current' => 2,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusQuotaWithArchivedWatchFiles(): void
    {
        $user = UserFactory::createOne();

        // Create 2 active watch files and 3 archived ones
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Archived Watch File 1',
                    'status' => WatchFileStatus::ARCHIVED,
                ],
                [
                    'name' => 'Archived Watch File 2',
                    'status' => WatchFileStatus::ARCHIVED,
                ],
                [
                    'name' => 'Archived Watch File 3',
                    'status' => WatchFileStatus::ARCHIVED,
                ],
            ])
            ->create();

        // Create a draft watch file
        $draftWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$draftWatchFile->getId()}/status/enabled");

        // Should fail - archived files don't count but we still have 2 active
        $this->assertResponseStatusCodeSame(429);
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.watchfile_max_active_per_user', [
            'limit' => 2,
            'current' => 2,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusReactivatingArchivedWatchFile(): void
    {
        $user = UserFactory::createOne();

        // Create 2 active watch files
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        // Create an archived watch file
        $archivedWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Archived Watch File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Try to reactivate archived file while at quota limit
        $client->request('POST', "/api/watch_files/{$archivedWatchFile->getId()}/status/enabled");

        // Should fail with quota exceeded
        $this->assertResponseStatusCodeSame(429);
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.watchfile_max_active_per_user', [
            'limit' => 2,
            'current' => 2,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusMultipleQuickTransitions(): void
    {
        $user = UserFactory::createOne();

        // Create 2 active watch files
        $activeWatchFile1 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Active Watch File 1',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $activeWatchFile2 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Active Watch File 2',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Deactivate both files quickly
        $client->request('POST', "/api/watch_files/{$activeWatchFile1->getId()}/status/draft");
        $this->assertResponseStatusCodeSame(201);

        $client->request('POST', "/api/watch_files/{$activeWatchFile2->getId()}/status/archived");
        $this->assertResponseStatusCodeSame(201);

        // Now enable two new watch files (with referenceSubject and active sources)
        $draftWatchFile1 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'New Draft Watch File 1',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test 1', 'Test subject 1'),
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Active Source 1',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example1.com',
                'primaryDomain' => 'example1.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $draftWatchFile1,
            ])
            ->create();

        $draftWatchFile2 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'New Draft Watch File 2',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test 2', 'Test subject 2'),
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Active Source 2',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example2.com',
                'primaryDomain' => 'example2.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $draftWatchFile2,
            ])
            ->create();

        $client->request('POST', "/api/watch_files/{$draftWatchFile1->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);

        $client->request('POST', "/api/watch_files/{$draftWatchFile2->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);
    }

    public function testPostChangeWatchFileStatusSharedWatchFileCountsTowardsOwnerQuota(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        // Create 2 active watch files owned by owner
        WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->sequence([
                [
                    'name' => 'Owner Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Owner Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        // Create a draft watch file shared with viewer
        $draftWatchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->with([
                'name' => 'Shared Draft Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('POST', "/api/watch_files/{$draftWatchFile->getId()}/status/enabled");

        // Should fail - owner already has 2 active watch files
        $this->assertResponseStatusCodeSame(429);
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.watchfile_max_active_per_user', [
            'limit' => 2,
            'current' => 2,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusSharedWatchFileDoesNotCountTowardsViewerQuota(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        // Create 2 active watch files owned by owner and shared with viewer
        WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->sequence([
                [
                    'name' => 'Shared Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Shared Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        // Create a draft watch file owned by viewer (with referenceSubject)
        $viewerDraftWatchFile1 = WatchFileFactory::new()
            ->withCreatedBy($viewer)
            ->withOwnedBy($viewer)
            ->with([
                'name' => 'Viewer Draft Watch File 1',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test 1', 'Test subject 1'),
            ])
            ->create();

        // Create an active source for the first draft watch file (required for activation)
        SourceFactory::new()
            ->with([
                'name' => 'Active Source 1',
                'type' => SourceType::WEBSITE,
                'url' => 'https://viewer-example1.com',
                'primaryDomain' => 'viewer-example1.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $viewerDraftWatchFile1,
            ])
            ->create();

        $viewerDraftWatchFile2 = WatchFileFactory::new()
            ->withCreatedBy($viewer)
            ->withOwnedBy($viewer)
            ->with([
                'name' => 'Viewer Draft Watch File 2',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test 2', 'Test subject 2'),
            ])
            ->create();

        // Create an active source for the second draft watch file (required for activation)
        SourceFactory::new()
            ->with([
                'name' => 'Active Source 2',
                'type' => SourceType::WEBSITE,
                'url' => 'https://viewer-example2.com',
                'primaryDomain' => 'viewer-example2.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $viewerDraftWatchFile2,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($viewer);

        // Viewer should be able to enable both their own watch files
        // Shared watch files don't count towards viewer's quota
        $client->request('POST', "/api/watch_files/{$viewerDraftWatchFile1->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);

        $client->request('POST', "/api/watch_files/{$viewerDraftWatchFile2->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);
    }

    public function testPostChangeWatchFileStatusWithCorruptedDataExceedingQuota(): void
    {
        $user = UserFactory::createOne();

        // Simulate corrupted state: 4 active watch files (exceeding quota of 2)
        // This could happen if quota was reduced, or data was corrupted
        $activeWatchFiles = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Corrupted Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Corrupted Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Corrupted Active Watch File 3',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Corrupted Active Watch File 4',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // User should be able to deactivate a watchfile even when quota is exceeded
        $firstWatchFile = $activeWatchFiles[0];
        $response = $client->request('POST', "/api/watch_files/{$firstWatchFile->getId()}/status/draft");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('draft', $data['status']);

        // User should be able to archive a watchfile even when quota is exceeded
        $secondWatchFile = $activeWatchFiles[1];
        $response = $client->request('POST', "/api/watch_files/{$secondWatchFile->getId()}/status/archived");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('archived', $data['status']);
    }

    public function testPostChangeWatchFileStatusCannotActivateWhenAlreadyExceedingQuota(): void
    {
        $user = UserFactory::createOne();

        // Simulate corrupted state: 3 active watch files (exceeding quota of 2)
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Corrupted Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Corrupted Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Corrupted Active Watch File 3',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        // Create a draft watch file
        $draftWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Should not be able to activate another watchfile when already exceeding quota
        $client->request('POST', "/api/watch_files/{$draftWatchFile->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(429);
        $expectedMessage = $this->getTranslatedQuotaMessage('quota.watchfile_max_active_per_user', [
            'limit' => 2,
            'current' => 3,
        ]);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testPostChangeWatchFileStatusCanKeepActiveWhenAlreadyActive(): void
    {
        $user = UserFactory::createOne();

        // Create 3 active watch files (exceeding quota)
        $activeWatchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Already Active Watch File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->sequence([
                [
                    'name' => 'Corrupted Active Watch File 1',
                    'status' => WatchFileStatus::ENABLED,
                ],
                [
                    'name' => 'Corrupted Active Watch File 2',
                    'status' => WatchFileStatus::ENABLED,
                ],
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Should be able to keep an already active watchfile active (idempotent)
        $response = $client->request('POST', "/api/watch_files/{$activeWatchFile->getId()}/status/enabled");
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }
}
