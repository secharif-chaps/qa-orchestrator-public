<?php

declare(strict_types=1);

namespace App\Tests\Integration\Source;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Tests\Integration\AbstractApiTestCase;

class SourceTypesApiTest extends AbstractApiTestCase
{
    public function testGetSourceTypesReturnsTypesWithCounts(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::INACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::RSS_FEED,
            'status' => SourceStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);
        $this->assertIsArray($data['types']);

        // Should have 2 unique types: website and rss_feed
        $this->assertCount(2, $data['types']);

        // Verify types are present with their counts
        $types = $data['types'];
        $this->assertArrayHasKey('website', $types, 'Website type should be present');
        $this->assertArrayHasKey('rss_feed', $types, 'RSS feed type should be present');
        $this->assertEquals(2, $types['website'], 'Website should have 2 sources');
        $this->assertEquals(1, $types['rss_feed'], 'RSS feed should have 1 source');
    }

    public function testGetSourceTypesRequiresAuthentication(): void
    {
        $watchFile = WatchFileFactory::createOne();

        $client = self::createClient();
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types");

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetSourceTypesRequiresWatchFileAccess(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetSourceTypesWithNonExistentWatchFile(): void
    {
        $user = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', '/api/watch_files/00000000-0000-0000-0000-000000000000/source-types');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetSourceTypesWithInvalidWatchFileId(): void
    {
        $user = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', '/api/watch_files/invalid-id/source-types');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetSourceTypesReturnsEmptyWhenNoSources(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);
        $this->assertIsArray($data['types']);
        $this->assertCount(0, $data['types']);
    }

    public function testGetSourceTypesWithActiveStatusFilter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::INACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::RSS_FEED,
            'status' => SourceStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types?status=active");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);
        $this->assertIsArray($data['types']);

        // Should only have active source types: website and rss_feed
        $this->assertCount(2, $data['types']);

        $types = $data['types'];
        $this->assertArrayHasKey('website', $types, 'Website type should be present');
        $this->assertArrayHasKey('rss_feed', $types, 'RSS feed type should be present');
        $this->assertEquals(1, $types['website'], 'Active website should have 1 source');
        $this->assertEquals(1, $types['rss_feed'], 'Active RSS feed should have 1 source');
    }

    public function testGetSourceTypesWithInactiveStatusFilter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::INACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::NEWSLETTER,
            'status' => SourceStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types?status=inactive");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);
        $this->assertIsArray($data['types']);

        // Should only have inactive source types: website and newsletter
        $this->assertCount(2, $data['types']);

        $types = $data['types'];
        $this->assertArrayHasKey('website', $types, 'Website type should be present');
        $this->assertArrayHasKey('newsletter', $types, 'Newsletter type should be present');
        $this->assertEquals(1, $types['website'], 'Inactive website should have 1 source');
        $this->assertEquals(1, $types['newsletter'], 'Inactive newsletter should have 1 source');
    }

    public function testGetSourceTypesWithInvalidStatusFilter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types?status=invalid");

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetSourceTypesWithNameFilter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Acme Corporation Website',
            'primaryDomain' => 'acme.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Beta News RSS',
            'primaryDomain' => 'beta.com',
            'type' => SourceType::RSS_FEED,
            'status' => SourceStatus::ACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Acme Newsletter',
            'primaryDomain' => 'acme.com',
            'type' => SourceType::NEWSLETTER,
            'status' => SourceStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types?name=acme");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);

        // Should only have sources matching "acme": website and newsletter
        $this->assertCount(2, $data['types']);

        $types = $data['types'];
        $this->assertArrayHasKey('website', $types, 'Website type should be present');
        $this->assertArrayHasKey('newsletter', $types, 'Newsletter type should be present');
        $this->assertArrayNotHasKey('rss_feed', $types, 'RSS feed type should not be present');
        $this->assertEquals(1, $types['website']);
        $this->assertEquals(1, $types['newsletter']);
    }

    public function testGetSourceTypesWithNameFilterOnDomain(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Source One',
            'primaryDomain' => 'example.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Source Two',
            'primaryDomain' => 'other.org',
            'type' => SourceType::RSS_FEED,
            'status' => SourceStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types?name=example");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);

        // Should only have sources matching domain "example"
        $this->assertCount(1, $data['types']);

        $types = $data['types'];
        $this->assertArrayHasKey('website', $types, 'Website type should be present');
        $this->assertArrayNotHasKey('rss_feed', $types, 'RSS feed type should not be present');
        $this->assertEquals(1, $types['website']);
    }

    public function testGetSourceTypesWithNameAndStatusFilter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Acme Website Active',
            'primaryDomain' => 'acme.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Acme Website Inactive',
            'primaryDomain' => 'acme.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::INACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Beta RSS Active',
            'primaryDomain' => 'beta.com',
            'type' => SourceType::RSS_FEED,
            'status' => SourceStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request(
            'GET',
            "/api/watch_files/{$watchFile->getId()}/source-types?name=acme&status=active"
        );

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);

        // Should only have active sources matching "acme"
        $this->assertCount(1, $data['types']);

        $types = $data['types'];
        $this->assertArrayHasKey('website', $types, 'Website type should be present');
        $this->assertEquals(1, $types['website']); // Only 1 active Acme website
    }

    public function testGetSourceTypesWithEmptyNameFilter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
        ]);

        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'type' => SourceType::RSS_FEED,
            'status' => SourceStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/source-types?name=");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);

        // Empty name should return all types
        $this->assertCount(2, $data['types']);
    }
}
