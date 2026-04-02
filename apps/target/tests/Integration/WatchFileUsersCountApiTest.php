<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;

use function Zenstruck\Foundry\Persistence\save;

/**
 * Integration test for TAR-208: watchFileUsersCount not updating after share operations.
 *
 * Bug Description:
 * When applying sort on "Access" column, then sharing a watch file with a new user,
 * the watchFileUsersCount value is not updated and remains at 1.
 * Grid order doesn't update until manually re-sorting.
 *
 * Expected Behavior:
 * After adding or removing share, GET /api/watch_files should return updated watchFileUsersCount.
 */
class WatchFileUsersCountApiTest extends AbstractApiTestCase
{
    public function testGetWatchFileInitialUsersCount(): void
    {
        ['owner' => $owner, 'watchFile' => $watchFile] = $this->createWatchFile();
        $watchFileId = $watchFile->getId();

        $data = $this->fetchWatchFile($owner, $watchFileId);

        $this->assertArrayHasKey('watchFileUsersCount', $data);
        $this->assertEquals(1, $data['watchFileUsersCount'], 'Initial watchFileUsersCount should be 1 (owner only)');
    }

    public function testWatchFileUsersCountAfterAddingShare(): void
    {
        ['owner' => $owner, 'watchFile' => $watchFile] = $this->createWatchFile();
        $watchFileId = $watchFile->getId();

        // Initial state: should be 1 (owner only)
        $data = $this->fetchWatchFile($owner, $watchFileId);
        $this->assertEquals(1, $data['watchFileUsersCount'], 'Initial count should be 1');

        // Add first shared user
        $this->addSharedUser($watchFile, WatchFileUserRole::EDITOR);

        // Fetch again: should be 2 (owner + 1 shared user)
        $data = $this->fetchWatchFile($owner, $watchFileId);
        $this->assertEquals(
            2,
            $data['watchFileUsersCount'],
            'After adding 1 shared user, count should be 2 (owner + 1 shared user)'
        );

        // Add second shared user
        $this->addSharedUser($watchFile, WatchFileUserRole::VIEWER);

        // Fetch again: should be 3 (owner + 2 shared users)
        $data = $this->fetchWatchFile($owner, $watchFileId);
        $this->assertEquals(
            3,
            $data['watchFileUsersCount'],
            'After adding 2 shared users, count should be 3 (owner + 2 shared users)'
        );
    }

    public function testWatchFileUsersCountAfterRemovingShare(): void
    {
        ['owner' => $owner, 'watchFile' => $watchFile] = $this->createWatchFile();
        $watchFileId = $watchFile->getId();

        // Add 2 shared users
        $watchFileUser1 = $this->addSharedUser($watchFile, WatchFileUserRole::EDITOR);
        $watchFileUser1Id = $watchFileUser1->getId();

        $watchFileUser2 = $this->addSharedUser($watchFile, WatchFileUserRole::VIEWER);
        $watchFileUser2Id = $watchFileUser2->getId();

        $client = $this->createAuthenticatedClient($owner);

        // Initial state with 2 shares: should be 3 (owner + 2 shared users)
        $response = $client->request('GET', '/api/watch_files/' . $watchFileId);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(3, $data['watchFileUsersCount'], 'Initial count should be 3');

        // Remove first shared user via API
        $client->request('DELETE', \sprintf('/api/watch_files/%s/share/%s', $watchFileId, $watchFileUser1Id));
        $this->assertResponseStatusCodeSame(204);

        // Fetch again: should be 2 (owner + 1 remaining shared user)
        $response = $client->request('GET', '/api/watch_files/' . $watchFileId);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(
            2,
            $data['watchFileUsersCount'],
            'After removing 1 shared user, count should be 2 (owner + 1 shared user)'
        );

        // Remove second shared user via API
        $client->request('DELETE', \sprintf('/api/watch_files/%s/share/%s', $watchFileId, $watchFileUser2Id));
        $this->assertResponseStatusCodeSame(204);

        // Fetch again: should be 1 (owner only)
        $response = $client->request('GET', '/api/watch_files/' . $watchFileId);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(
            1,
            $data['watchFileUsersCount'],
            'After removing all shared users, count should be 1 (owner only)'
        );
    }

    public function testWatchFileUsersCountInCollection(): void
    {
        $owner = UserFactory::createOne();

        // Create watch file with 1 shared user (total: 2)
        ['watchFile' => $watchFile1] = $this->createWatchFile($owner, 'Watch File 1');
        $watchFile1Id = $watchFile1->getId();
        $this->addSharedUser($watchFile1, WatchFileUserRole::EDITOR);

        // Create watch file with no shared users (total: 1)
        ['watchFile' => $watchFile2] = $this->createWatchFile($owner, 'Watch File 2');
        $watchFile2Id = $watchFile2->getId();

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/watch_files');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertCount(2, $data['member']);

        // Find watch files in response
        $watchFile1Data = $this->findItemInCollection($data['member'], $watchFile1Id);
        $watchFile2Data = $this->findItemInCollection($data['member'], $watchFile2Id);

        $this->assertNotNull($watchFile1Data, 'Watch File 1 should be in collection');
        $this->assertNotNull($watchFile2Data, 'Watch File 2 should be in collection');

        $this->assertEquals(
            2,
            $watchFile1Data['watchFileUsersCount'],
            'Watch File 1 should have count of 2 (owner + 1 shared user)'
        );

        $this->assertEquals(
            1,
            $watchFile2Data['watchFileUsersCount'],
            'Watch File 2 should have count of 1 (owner only)'
        );
    }

    public function testWatchFileUsersCountWithMixedRoles(): void
    {
        ['owner' => $owner, 'watchFile' => $watchFile] = $this->createWatchFile();
        $watchFileId = $watchFile->getId();

        // Add users with different roles
        $this->addSharedUser($watchFile, WatchFileUserRole::EDITOR);
        $this->addSharedUser($watchFile, WatchFileUserRole::VIEWER);
        $this->addSharedUser($watchFile, WatchFileUserRole::VIEWER);

        $data = $this->fetchWatchFile($owner, $watchFileId);
        $this->assertEquals(
            4,
            $data['watchFileUsersCount'],
            'With mixed roles (1 owner + 1 editor + 2 viewers), count should be 4'
        );
    }

    public function testWatchFileUsersCountNotAffectedByOtherWatchFiles(): void
    {
        $owner = UserFactory::createOne();
        $sharedUser = UserFactory::createOne();

        // Create first watch file with 1 shared user
        ['watchFile' => $watchFile1] = $this->createWatchFile($owner, 'Watch File 1');
        $watchFile1Id = $watchFile1->getId();
        $this->addSharedUser($watchFile1, WatchFileUserRole::EDITOR, $sharedUser);

        // Create second watch file with NO shared users
        ['watchFile' => $watchFile2] = $this->createWatchFile($owner, 'Watch File 2');
        $watchFile2Id = $watchFile2->getId();

        // Create third watch file with same shared user
        ['watchFile' => $watchFile3] = $this->createWatchFile($owner, 'Watch File 3');
        $watchFile3Id = $watchFile3->getId();
        $this->addSharedUser($watchFile3, WatchFileUserRole::VIEWER, $sharedUser);

        $client = $this->createAuthenticatedClient($owner);

        // Verify watch file 1 count is not affected by others
        $response = $client->request('GET', '/api/watch_files/' . $watchFile1Id);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(2, $data['watchFileUsersCount'], 'Watch File 1 should have count of 2');

        // Verify watch file 2 count is not affected by others
        $response = $client->request('GET', '/api/watch_files/' . $watchFile2Id);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(1, $data['watchFileUsersCount'], 'Watch File 2 should have count of 1');

        // Verify watch file 3 count is not affected by others
        $response = $client->request('GET', '/api/watch_files/' . $watchFile3Id);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(2, $data['watchFileUsersCount'], 'Watch File 3 should have count of 2');
    }

    public function testWatchFileUsersCountAfterDuplicateShareAttempt(): void
    {
        [
            'owner' => $owner,
            'watchFile' => $watchFile,
        ] = $this->createWatchFile();

        $sharedUser = UserFactory::createOne();
        $watchFileId = $watchFile->getId();

        // Add shared user
        $this->addSharedUser($watchFile, WatchFileUserRole::EDITOR, $sharedUser);

        // Verify count is 2
        $data = $this->fetchWatchFile($owner, $watchFileId);
        $this->assertEquals(2, $data['watchFileUsersCount'], 'After adding 1 shared user, count should be 2');

        // Attempt to add same user again (should fail at database constraint level)
        $exceptionThrown = false;
        try {
            WatchFileUserFactory::createOne([
                'watchFile' => $watchFile,
                'user' => $sharedUser,
                'role' => WatchFileUserRole::VIEWER,
            ]);
        } catch (\Exception $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Duplicate WatchFileUser should throw exception');

        // Verify count is still 2 (unchanged)
        $data = $this->fetchWatchFile($owner, $watchFileId);
        $this->assertEquals(2, $data['watchFileUsersCount'], 'After duplicate attempt, count should remain 2');
    }

    public function testWatchFileUsersCountInCollectionWithPagination(): void
    {
        $owner = UserFactory::createOne();

        // Create 5 watch files with varying user counts
        for ($i = 1; $i <= 5; ++$i) {
            ['watchFile' => $watchFile] = $this->createWatchFile($owner, 'Watch File ' . $i);

            // Add shared users (0 to 4 additional users)
            for ($j = 0; $j < $i - 1; ++$j) {
                $additionalUser = UserFactory::createOne();
                $this->addSharedUser($watchFile, WatchFileUserRole::VIEWER, $additionalUser);
            }
        }

        $client = $this->createAuthenticatedClient($owner);

        // Fetch first page (default pagination)
        $response = $client->request('GET', '/api/watch_files');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(5, \count($data['member']), 'Should have at least 5 watch files');

        // Verify each watch file has correct count
        $countsFound = [];
        foreach ($data['member'] as $item) {
            $countsFound[] = $item['watchFileUsersCount'];
        }

        $this->assertContains(1, $countsFound, 'Should have watch file with count 1');
        $this->assertContains(2, $countsFound, 'Should have watch file with count 2');
        $this->assertContains(3, $countsFound, 'Should have watch file with count 3');
        $this->assertContains(4, $countsFound, 'Should have watch file with count 4');
        $this->assertContains(5, $countsFound, 'Should have watch file with count 5');
    }

    public function testWatchFileUsersCountAfterRoleChange(): void
    {
        [
            'owner' => $owner,
            'watchFile' => $watchFile,
        ] = $this->createWatchFile();

        $sharedUser = UserFactory::createOne();
        $watchFileId = $watchFile->getId();

        $watchFileUser = $this->addSharedUser($watchFile, WatchFileUserRole::VIEWER, $sharedUser);

        // Initial count: 2 (owner + 1 viewer)
        $data = $this->fetchWatchFile($owner, $watchFileId);
        $this->assertEquals(2, $data['watchFileUsersCount'], 'Initial count should be 2');

        // Change role from VIEWER to EDITOR
        $this->changeWatchFileUserRole($watchFileUser, WatchFileUserRole::EDITOR);

        // Count should remain 2 (role change doesn't affect count)
        $data = $this->fetchWatchFile($owner, $watchFileId);
        $this->assertEquals(2, $data['watchFileUsersCount'], 'After role change, count should remain 2');
    }

    /**
     * @return array{owner: User, watchFile: WatchFile}
     */
    private function createWatchFile(?User $owner = null, string $name = 'Test Watch File'): array
    {
        if (null === $owner) {
            $owner = UserFactory::createOne();
        }

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create([
                'name' => $name,
                'status' => WatchFileStatus::ENABLED,
            ]);

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
        ];
    }

    private function addSharedUser(
        WatchFile $watchFile,
        WatchFileUserRole $role,
        ?User $user = null,
    ): WatchFileUser {
        if (null === $user) {
            $user = UserFactory::createOne();
        }

        return WatchFileUserFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'role' => $role,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchWatchFile(User $owner, string $watchFileId): array
    {
        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFileId);

        $this->assertResponseIsSuccessful();

        return $response->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $collection
     *
     * @return array<string, mixed>|null
     */
    private function findItemInCollection(array $collection, string $id): ?array
    {
        foreach ($collection as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    private function changeWatchFileUserRole(WatchFileUser $watchFileUser, WatchFileUserRole $newRole): void
    {
        $watchFileUser->setRole($newRole);
        save($watchFileUser);
    }
}
