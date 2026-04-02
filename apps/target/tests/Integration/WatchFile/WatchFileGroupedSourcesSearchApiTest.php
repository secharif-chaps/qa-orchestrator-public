<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Source\CollectStatus;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Tests\Integration\AbstractApiTestCase;

class WatchFileGroupedSourcesSearchApiTest extends AbstractApiTestCase
{
    public function testGetGroupedSourcesWithoutSearch(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Nature - Energy Research',
            'primaryDomain' => 'nature.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Forrester - Competitive Intelligence',
            'primaryDomain' => 'forrester.com',
            'type' => SourceType::RSS_FEED,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertCount(3, $data['groups']);

        $this->assertEquals(3, $data['summary']['total']);
        $this->assertEquals(0, $data['summary']['error']);
        $this->assertEquals(0, $data['summary']['running']);
        $this->assertEquals(3, $data['summary']['stopped']);
    }

    public function testGetGroupedSourcesWithSearchOnName(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Nature - Energy Research',
            'primaryDomain' => 'nature.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Forrester - Competitive Intelligence',
            'primaryDomain' => 'forrester.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/sources/grouped?search=techcrunch'
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertCount(1, $data['groups']);
        $this->assertEquals('blog', $data['groups'][0]['type']);
        $this->assertEquals(1, $data['groups'][0]['count']);

        $this->assertEquals(1, $data['summary']['total']);
        $this->assertEquals(0, $data['summary']['error']);
        $this->assertEquals(0, $data['summary']['running']);
        $this->assertEquals(1, $data['summary']['stopped']);

        $sourceName = $data['groups'][0]['sources'][0]['name'];
        $this->assertEquals('TechCrunch - Health Tech', $sourceName);
    }

    public function testGetGroupedSourcesWithSearchOnDomain(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Nature - Energy Research',
            'primaryDomain' => 'nature.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Forrester - Competitive Intelligence',
            'primaryDomain' => 'forrester.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/sources/grouped?search=nature.com'
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertCount(1, $data['groups']);
        $this->assertEquals('website', $data['groups'][0]['type']);
        $this->assertEquals(1, $data['groups'][0]['count']);

        $this->assertEquals(1, $data['summary']['total']);

        $sourceName = $data['groups'][0]['sources'][0]['name'];
        $this->assertEquals('Nature - Energy Research', $sourceName);
    }

    public function testGetGroupedSourcesWithSearchTooShort(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped?search=t');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertCount(1, $data['groups']);
        $this->assertEquals(1, $data['summary']['total']);
    }

    public function testGetGroupedSourcesWithSearchNoResults(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/sources/grouped?search=nonexistent'
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertCount(0, $data['groups']);
        $this->assertEquals(0, $data['summary']['total']);
        $this->assertEquals(0, $data['summary']['error']);
        $this->assertEquals(0, $data['summary']['running']);
        $this->assertEquals(0, $data['summary']['stopped']);
    }

    public function testGetGroupedSourcesWithSearchCaseInsensitive(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/sources/grouped?search=TECHCRUNCH'
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertCount(1, $data['groups']);
        $this->assertEquals(1, $data['summary']['total']);

        $sourceName = $data['groups'][0]['sources'][0]['name'];
        $this->assertEquals('TechCrunch - Health Tech', $sourceName);
    }

    public function testGetGroupedSourcesWithSearchOnMultipleFields(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Tech News Daily',
            'primaryDomain' => 'technews.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Nature - Energy Research',
            'primaryDomain' => 'nature.com',
            'type' => SourceType::WEBSITE,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped?search=tech');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertGreaterThan(0, $data['groups']);

        $allSources = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['sources'] as $source) {
                $allSources[] = $source['name'];
            }
        }

        $this->assertContains('TechCrunch - Health Tech', $allSources);
        $this->assertContains('Tech News Daily', $allSources);
        $this->assertNotContains('Nature - Energy Research', $allSources);
    }

    public function testGetGroupedSourcesAccessDenied(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $unauthorizedUser = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped?search=tech');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetGroupedSourcesWithErrorSources(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'TechCrunch - Health Tech',
            'primaryDomain' => 'techcrunch.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::STOPPED,
        ])->create();

        SourceFactory::new([
            'watchFile' => $watchFile,
            'name' => 'Error Source',
            'primaryDomain' => 'error.com',
            'type' => SourceType::BLOG,
            'status' => SourceStatus::ACTIVE,
            'collectStatus' => CollectStatus::ERROR,
        ])->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/sources/grouped?search=error'
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertCount(1, $data['groups']);
        $this->assertEquals('error', $data['groups'][0]['type']);
        $this->assertEquals(1, $data['groups'][0]['count']);

        $this->assertEquals(1, $data['summary']['total']);
        $this->assertEquals(1, $data['summary']['error']);
        $this->assertEquals(0, $data['summary']['running']);
        $this->assertEquals(0, $data['summary']['stopped']);
    }
}
