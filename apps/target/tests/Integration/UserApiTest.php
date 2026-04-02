<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\WatchFile\WatchFileUserRole;

class UserApiTest extends AbstractApiTestCase
{
    public function testGetCollectionUsers(): void
    {
        UserFactory::createMany(50);

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/users');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/User',
            '@id' => '/api/users',
            '@type' => 'Collection',
            'totalItems' => 51,
            'view' => [
                '@id' => '/api/users?page=1',
                '@type' => 'PartialCollectionView',
                'first' => '/api/users?page=1',
                'last' => '/api/users?page=2',
                'next' => '/api/users?page=2',
            ],
        ]);

        $this->assertCount(30, $response->toArray()['member']);
    }

    public function testGetCollectionUsersStructure(): void
    {
        UserFactory::new()
            ->with([
                'firstName' => 'John',
                'lastName' => 'Doe',
                'userName' => 'johndoe',
                'email' => 'john.doe@example.com',
            ])
            ->create();

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/users');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // Check that users are present and have expected structure
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThan(0, \count($data['member']));

        $foundUser = null;
        foreach ($data['member'] as $member) {
            if ('john.doe@example.com' === $member['email']) {
                $foundUser = $member;
                break;
            }
        }

        $this->assertNotNull($foundUser, 'Created user should be found in the collection');
        $this->assertArrayHasKey('@id', $foundUser);
        $this->assertArrayHasKey('@type', $foundUser);
        $this->assertArrayHasKey('id', $foundUser);
        $this->assertArrayHasKey('email', $foundUser);
        $this->assertArrayHasKey('firstName', $foundUser);
        $this->assertArrayHasKey('lastName', $foundUser);
        $this->assertArrayHasKey('displayName', $foundUser);
        $this->assertArrayHasKey('defaultThumbnail', $foundUser);

        $this->assertEquals('User', $foundUser['@type']);
        $this->assertEquals('john.doe@example.com', $foundUser['email']);
        $this->assertEquals('John', $foundUser['firstName']);
        $this->assertEquals('Doe', $foundUser['lastName']);
        $this->assertEquals('John Doe', $foundUser['displayName']);
        $this->assertEquals('JD', $foundUser['defaultThumbnail']);
    }

    public function testGetCollectionUsersPagination(): void
    {
        UserFactory::createMany(35);

        $client = $this->createAuthenticatedClient();

        // Test first page
        $response = $client->request('GET', '/api/users?page=1');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('view', $data);
        $this->assertArrayHasKey('first', $data['view']);
        $this->assertArrayHasKey('last', $data['view']);
        $this->assertArrayHasKey('next', $data['view']);
        $this->assertCount(30, $data['member']);

        // Test second page
        $response = $client->request('GET', '/api/users?page=2');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(6, $data['member']); // 35 + 1 (authenticated user) = 36 total, 30 on first page, 6 on second
        $this->assertArrayHasKey('previous', $data['view']);
    }

    public function testGetCollectionUsersMultiFieldSearch(): void
    {
        UserFactory::new()
            ->with([
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'userName' => 'jsmith',
                'email' => 'jane.smith@example.com',
            ])
            ->create();

        UserFactory::new()
            ->with([
                'firstName' => 'John',
                'lastName' => 'Doe',
                'userName' => 'jdoe',
                'email' => 'john.doe@example.com',
            ])
            ->create();

        $client = $this->createAuthenticatedClient();

        // Test search by first name
        $response = $client->request('GET', '/api/users?search=Jane');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertGreaterThanOrEqual(1, \count($data['member']));
        $foundJane = false;
        foreach ($data['member'] as $user) {
            if ('jane.smith@example.com' === $user['email']) {
                $foundJane = true;
                break;
            }
        }
        $this->assertTrue($foundJane, 'Should find Jane when searching by first name');

        // Test search by email
        $response = $client->request('GET', '/api/users?search=john.doe');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $foundJohn = false;
        foreach ($data['member'] as $user) {
            if ('john.doe@example.com' === $user['email']) {
                $foundJohn = true;
                break;
            }
        }
        $this->assertTrue($foundJohn, 'Should find John when searching by email');
    }

    public function testGetCollectionUsersExcludeCurrentUser(): void
    {
        $currentUser = UserFactory::new()
            ->with([
                'email' => 'current@example.com',
                'firstName' => 'Current',
                'lastName' => 'User',
            ])
            ->create();

        UserFactory::createMany(5);

        $client = $this->createAuthenticatedClient($currentUser);
        $response = $client->request('GET', '/api/users?excludeCurrentUser=true');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // Current user should be excluded from results
        foreach ($data['member'] as $user) {
            $this->assertNotEquals('current@example.com', $user['email']);
        }
    }

    public function testGetCollectionUsersExcludeWatchFileSharedUsers(): void
    {
        $watchFileOwner = UserFactory::new()
            ->with([
                'email' => 'owner@example.com',
            ])
            ->create();

        // Create a watch file with shared users
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->create();

        WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $watchFileOwner,
                'role' => WatchFileUserRole::OWNER,
            ])
            ->create();

        WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => UserFactory::new()->with([
                    'email' => 'shared1@example.com',
                ]),
                'role' => WatchFileUserRole::EDITOR,
            ])
            ->create();

        WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => UserFactory::new()->with([
                    'email' => 'shared2@example.com',
                ]),
                'role' => WatchFileUserRole::VIEWER,
            ])
            ->create();

        UserFactory::new()
            ->with([
                'email' => 'notshared@example.com',
            ])
            ->create();

        // Add shared users to watch file (this would normally be done through WatchFileUser entities)
        // For this test, we'll use the watch file parameter to exclude users

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request(
            'GET',
            '/api/users?excludeWatchFileSharedUsers=' . $watchFile->getId() . '&excludeCurrentUser=true'
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // All users should be present since we haven't actually shared the watch file yet
        // This tests the filter is working, even if no exclusions are made
        $this->assertCount(1, $data['member']);
        $this->assertEquals('notshared@example.com', $data['member'][0]['email']);
    }

    public function testGetCollectionUsersDisplayNameGeneration(): void
    {
        // Test user with both first and last name
        UserFactory::new()
            ->with([
                'firstName' => 'Alice',
                'lastName' => 'Johnson',
                'userName' => 'ajohnson',
                'email' => 'alice.johnson@example.com',
            ])
            ->create();

        // Test user with only userName (no first/last name)
        UserFactory::new()
            ->with([
                'firstName' => null,
                'lastName' => null,
                'userName' => 'bobsmith',
                'email' => 'bob@example.com',
            ])
            ->create();

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/users');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $foundFullName = false;
        $foundUserNameOnly = false;

        foreach ($data['member'] as $user) {
            if ('alice.johnson@example.com' === $user['email']) {
                $this->assertEquals('Alice Johnson', $user['displayName']);
                $this->assertEquals('AJ', $user['defaultThumbnail']);
                $foundFullName = true;
            }

            if ('bob@example.com' === $user['email']) {
                $this->assertEquals('bobsmith', $user['displayName']);
                $this->assertEquals('B', $user['defaultThumbnail']);
                $foundUserNameOnly = true;
            }
        }

        $this->assertTrue($foundFullName, 'Should find user with full name');
        $this->assertTrue($foundUserNameOnly, 'Should find user with userName only');
    }

    public function testGetCollectionUsersWithLongDisplayName(): void
    {
        $user = UserFactory::new()
            ->with([
                'firstName' => 'Christopher',
                'lastName' => 'Montgomery-Wellington',
                'userName' => 'cmw',
                'email' => 'christopher@example.com',
            ])
            ->create();

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/users');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        foreach ($data['member'] as $member) {
            if ('christopher@example.com' === $member['email']) {
                $this->assertEquals('Christopher Montgomery-Wellington', $member['displayName']);
                $this->assertEquals('CM', $member['defaultThumbnail']); // Should be limited to 2 characters
                break;
            }
        }
    }

    public function testGetCollectionUsersUnauthorized(): void
    {
        UserFactory::createMany(5);

        // Create client without authentication
        $client = self::createClient();
        $client->request('GET', '/api/users');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetCollectionUsersEmptyResult(): void
    {
        // Only create the authenticated user (done by createAuthenticatedClient)
        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/users?excludeCurrentUser=true');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertCount(0, $data['member']); // Current user is excluded
        $this->assertEquals(0, $data['totalItems']);
    }

    public function testGetCollectionUsersInvalidPage(): void
    {
        UserFactory::createMany(5);

        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/users?page=999');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertCount(0, $data['member']); // No users on page 999
    }
}
