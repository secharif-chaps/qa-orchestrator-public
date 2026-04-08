<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\CollectStatus;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFileStatus;
use Symfony\Component\HttpFoundation\Response;

use function Zenstruck\Foundry\Persistence\save;

class SourceApiTest extends AbstractApiTestCase
{
    public function testGetCollectionSources(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        for ($i = 0; $i < 50; ++$i) {
            SourceFactory::new([
                'watchFile' => $watchFile,
                'name' => 'Test Source ' . $i,
                'url' => 'https://example.com/source-' . $i,
                'type' => SourceType::WEBSITE,
            ])->create();
        }

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            'totalItems' => 50,
        ]);

        $this->assertCount(30, $response->toArray()['member']);
    }

    public function testGetCollectionSourcesAccessDenied(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $unauthorizedUser = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        SourceFactory::createMany(5, [
            'watchFile' => $watchFile,
        ]);

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSourceOutputSchema(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'name' => 'Test Source',
                'description' => TranslatedText::fromArray([
                    'fr' => 'Description en français',
                    'en' => 'Description in English',
                ]),
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'query' => 'test query',
                'relevance' => TranslatedText::fromArray([
                    'fr' => 'Pertinence en français',
                    'en' => 'Relevance in English',
                ]),
                'parameters' => [
                    'param1' => 'value1',
                    'param2' => 'value2',
                ],
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
        $this->assertCount(1, $data['member']);

        $sourceData = $data['member'][0];

        // Test required fields
        $this->assertArrayHasKey('id', $sourceData);
        $this->assertArrayHasKey('name', $sourceData);
        $this->assertArrayHasKey('description', $sourceData);
        $this->assertArrayHasKey('type', $sourceData);
        $this->assertArrayHasKey('url', $sourceData);
        $this->assertArrayHasKey('primaryDomain', $sourceData);
        $this->assertArrayHasKey('relevance', $sourceData);
        $this->assertArrayHasKey('active', $sourceData);
        $this->assertArrayHasKey('createdAt', $sourceData);

        // Test field values
        $this->assertEquals('Test Source', $sourceData['name']);
        $this->assertEquals(SourceType::WEBSITE->value, $sourceData['type']);
        $this->assertEquals('https://example.com', $sourceData['url']);
        $this->assertEquals('example.com', $sourceData['primaryDomain']);

        // Test multilingual fields
        $this->assertEquals([
            'fr' => 'Description en français',
            'en' => 'Description in English',
        ], $sourceData['description']);

        $this->assertEquals([
            'fr' => 'Pertinence en français',
            'en' => 'Relevance in English',
        ], $sourceData['relevance']);
    }

    public function testNameFilter(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create sources with different names
        SourceFactory::new()
            ->with([
                'name' => 'Apple News Source',
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Google Analytics Source',
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Microsoft Blog Source',
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);

        // Test partial name search (case-insensitive)
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources?name=apple');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
        $this->assertEquals('Apple News Source', $data['member'][0]['name']);
    }

    public function testTypeFilter(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create sources with different types
        SourceFactory::new()
            ->with([
                'type' => SourceType::WEBSITE,
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'type' => SourceType::RSS_FEED,
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'type' => SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $watchFileId = $watchFile->getId();

        // Test exact type filter
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFileId . '/sources?type=' . SourceType::WEBSITE->value
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
        $this->assertEquals(SourceType::WEBSITE->value, $data['member'][0]['type']);

        // Test social media type filter
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFileId . '/sources?type=' . SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY->value
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
        $this->assertEquals(SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY->value, $data['member'][0]['type']);
    }

    public function testPrimaryDomainFilter(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create sources with different domains
        SourceFactory::new()
            ->with([
                'primaryDomain' => 'linkedin.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'primaryDomain' => 'twitter.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'primaryDomain' => 'facebook.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);

        // Test partial domain search (case-insensitive)
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/sources?primaryDomain=linkedin'
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
        $this->assertEquals('linkedin.com', $data['member'][0]['primaryDomain']);
    }

    public function testOrdering(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create sources with different names for ordering
        SourceFactory::new()
            ->with([
                'name' => 'Zebra Source',
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Alpha Source',
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Beta Source',
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $watchFileId = $watchFile->getId();

        // Test ascending order by name
        $response = $client->request('GET', '/api/watch_files/' . $watchFileId . '/sources?order[name]=asc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(3, $data['member']);
        $this->assertEquals('Alpha Source', $data['member'][0]['name']);
        $this->assertEquals('Beta Source', $data['member'][1]['name']);
        $this->assertEquals('Zebra Source', $data['member'][2]['name']);

        // Test descending order by name
        $response = $client->request('GET', '/api/watch_files/' . $watchFileId . '/sources?order[name]=desc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(3, $data['member']);
        $this->assertEquals('Zebra Source', $data['member'][0]['name']);
        $this->assertEquals('Beta Source', $data['member'][1]['name']);
        $this->assertEquals('Alpha Source', $data['member'][2]['name']);
    }

    public function testCombinedFilters(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create sources with different combinations
        SourceFactory::new()
            ->with([
                'name' => 'LinkedIn Company Source',
                'type' => SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY,
                'primaryDomain' => 'linkedin.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'LinkedIn User Source',
                'type' => SourceType::SOCIAL_MEDIA_LINKEDIN_USER,
                'primaryDomain' => 'linkedin.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Twitter Search Source',
                'type' => SourceType::SOCIAL_MEDIA_X_SEARCH,
                'primaryDomain' => 'twitter.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);

        // Test combined filters: LinkedIn + Company type
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/sources?primaryDomain=linkedin&type=' . SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY->value
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
        $this->assertEquals('LinkedIn Company Source', $data['member'][0]['name']);
        $this->assertEquals('linkedin.com', $data['member'][0]['primaryDomain']);
        $this->assertEquals(SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY->value, $data['member'][0]['type']);
    }

    public function testChangeSourceStatusEnable(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::new()
            ->with([
                'name' => 'Test Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        // Deactivate the source to test enabling it
        $source->deactivate();
        save($source);

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/sources/' . $source->getId() . '/change-status',
            [
                'json' => [
                    'status' => 'active',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($source->getId(), $data['id']);
        $this->assertTrue($data['active']);
    }

    public function testChangeSourceStatusDisable(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::new()
            ->with([
                'name' => 'Test Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        // Source is active by default, so we can test disabling it

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/sources/' . $source->getId() . '/change-status',
            [
                'json' => [
                    'status' => 'inactive',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($source->getId(), $data['id']);
        $this->assertFalse($data['active']);
    }

    public function testChangeSourceStatusAccessDenied(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $unauthorizedUser = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::new()
            ->with([
                'name' => 'Test Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/sources/' . $source->getId() . '/change-status',
            [
                'json' => [
                    'status' => 'inactive',
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testChangeSourceStatusInvalidStatus(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::new()
            ->with([
                'name' => 'Test Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/sources/' . $source->getId() . '/change-status',
            [
                'json' => [
                    'status' => 'invalid_status',
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangeSourceStatusSourceNotFound(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $nonExistentSourceId = '00000000-0000-0000-0000-000000000000';

        $client = $this->createAuthenticatedClient($watchFileOwner);
        // Security check runs before resource resolution, returning 403 instead of 404
        $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/sources/' . $nonExistentSourceId . '/change-status',
            [
                'json' => [
                    'status' => 'active',
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testChangeSourceStatusWatchFileNotFound(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::new()
            ->with([
                'name' => 'Test Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        $nonExistentWatchFileId = '00000000-0000-0000-0000-000000000000';

        $client = $this->createAuthenticatedClient($watchFileOwner);
        // Security check runs before resource resolution, returning 403 instead of 404
        $client->request(
            'POST',
            '/api/watch_files/' . $nonExistentWatchFileId . '/sources/' . $source->getId() . '/change-status',
            [
                'json' => [
                    'status' => 'active',
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testChangeSourceStatusSourceNotInWatchFile(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile1 = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $watchFile2 = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::new()
            ->with([
                'name' => 'Test Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'watchFile' => $watchFile1,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $client->request(
            'POST',
            '/api/watch_files/' . $watchFile2->getId() . '/sources/' . $source->getId() . '/change-status',
            [
                'json' => [
                    'status' => 'inactive',
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testChangeSourceStatusWatchFileActive(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->with([
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $source = SourceFactory::new()
            ->with([
                'name' => 'Test Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/sources/' . $source->getId() . '/change-status',
            [
                'json' => [
                    'status' => 'inactive',
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetGroupedSources(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create sources with different types and statuses
        // Note: Only ACTIVE sources are shown in the grouped view
        SourceFactory::new()
            ->with([
                'name' => 'RSS Source',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://example.com/rss',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'collectStatus' => CollectStatus::RUNNING,
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'YouTube Source',
                'type' => SourceType::VIDEO_YOUTUBE_CHANNEL,
                'url' => 'https://youtube.com/channel',
                'primaryDomain' => 'youtube.com',
                'status' => SourceStatus::ACTIVE,
                'collectStatus' => CollectStatus::RUNNING,
                'watchFile' => $watchFile,
            ])
            ->create();

        // Active source with error status - visible in error group
        SourceFactory::new()
            ->with([
                'name' => 'Error Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://error.com',
                'primaryDomain' => 'error.com',
                'collectStatus' => CollectStatus::ERROR,
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        // Inactive source - NOT visible (disabled sources are filtered out)
        SourceFactory::new()
            ->with([
                'name' => 'Inactive Source',
                'type' => SourceType::BLOG,
                'url' => 'https://inactive.com',
                'primaryDomain' => 'inactive.com',
                'status' => SourceStatus::INACTIVE,
                'collectStatus' => CollectStatus::STOPPED,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        // Test response structure
        $this->assertArrayHasKey('groups', $data);
        $this->assertArrayHasKey('summary', $data);

        // Test groups structure
        $groups = $data['groups'];
        $this->assertIsArray($groups);
        // Only 3 groups now: Error group + 2 type groups (RSS and YouTube)
        // Inactive sources are filtered out
        $this->assertCount(3, $groups);

        // Test summary structure
        $summary = $data['summary'];
        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('error', $summary);
        $this->assertArrayHasKey('running', $summary);
        $this->assertArrayHasKey('stopped', $summary);

        // Test summary values - only active sources are counted
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(1, $summary['error']);
        $this->assertEquals(2, $summary['running']); // 2 ACTIVE sources running
        $this->assertEquals(0, $summary['stopped']); // No stopped active sources

        // Test group structure
        foreach ($groups as $group) {
            $this->assertIsArray($group);
            $this->assertArrayHasKey('type', $group);
            $this->assertArrayHasKey('typeLabel', $group);
            $this->assertArrayHasKey('count', $group);
            $this->assertArrayHasKey('sources', $group);
            $this->assertIsArray($group['sources']);
        }
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

        SourceFactory::createMany(3, [
            'watchFile' => $watchFile,
        ]);

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetGroupedSourcesWithEmptySources(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Test response structure
        $this->assertArrayHasKey('groups', $data);
        $this->assertArrayHasKey('summary', $data);

        // Test empty groups
        $groups = $data['groups'];
        $this->assertIsArray($groups);
        $this->assertEmpty($groups);

        // Test empty summary
        $summary = $data['summary'];
        $this->assertEquals(0, $summary['total']);
        $this->assertEquals(0, $summary['error']);
        $this->assertEquals(0, $summary['running']);
        $this->assertEquals(0, $summary['stopped']);
    }

    public function testGetGroupedSourcesWithMixedStatuses(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create sources with different statuses
        // Only ACTIVE sources are visible in the grouped view
        SourceFactory::new()
            ->with([
                'name' => 'Active Running Source',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://active.com',
                'primaryDomain' => 'active.com',
                'status' => SourceStatus::ACTIVE,
                'collectStatus' => CollectStatus::RUNNING,
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Active Stopped Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://activestopped.com',
                'primaryDomain' => 'activestopped.com',
                'status' => SourceStatus::ACTIVE,
                'collectStatus' => CollectStatus::STOPPED,
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Active Error Source',
                'type' => SourceType::BLOG,
                'url' => 'https://activeerror.com',
                'primaryDomain' => 'activeerror.com',
                'status' => SourceStatus::ACTIVE,
                'collectStatus' => CollectStatus::ERROR,
                'watchFile' => $watchFile,
            ])
            ->create();

        // These disabled sources should NOT be visible
        SourceFactory::new()
            ->with([
                'name' => 'Auto Disabled Source',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://autodisabled.com',
                'primaryDomain' => 'autodisabled.com',
                'status' => SourceStatus::AUTO_DISABLED,
                'collectStatus' => CollectStatus::STOPPED,
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Inactive Error Source',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://inactiveerror.com',
                'primaryDomain' => 'inactiveerror.com',
                'collectStatus' => CollectStatus::ERROR,
                'status' => SourceStatus::INACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $summary = $data['summary'];

        // Test summary values - only ACTIVE sources are counted
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(1, $summary['error']); // 1 active source with error
        $this->assertEquals(1, $summary['running']); // 1 active running
        $this->assertEquals(1, $summary['stopped']); // 1 active stopped

        // Verify disabled sources are not present
        $allSourceNames = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['sources'] as $source) {
                $allSourceNames[] = $source['name'];
            }
        }

        $this->assertContains('Active Running Source', $allSourceNames);
        $this->assertContains('Active Stopped Source', $allSourceNames);
        $this->assertContains('Active Error Source', $allSourceNames);
        $this->assertNotContains('Auto Disabled Source', $allSourceNames);
        $this->assertNotContains('Inactive Error Source', $allSourceNames);
    }

    public function testGetGroupedSourcesNotFound(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $client->request('GET', '/api/watch_files/123e4567-e89b-12d3-a456-426614174000/sources/grouped');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetGroupedSourcesExcludesInactiveSources(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create an active source - should be visible
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://active.com',
                'primaryDomain' => 'active.com',
                'status' => SourceStatus::ACTIVE,
                'collectStatus' => CollectStatus::RUNNING,
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create an inactive source - should NOT be visible
        SourceFactory::new()
            ->with([
                'name' => 'Inactive Source',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://inactive.com',
                'primaryDomain' => 'inactive.com',
                'status' => SourceStatus::INACTIVE,
                'collectStatus' => CollectStatus::STOPPED,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Should only have 1 source (the active one)
        $this->assertEquals(1, $data['summary']['total']);

        // Collect all source names from groups
        $allSourceNames = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['sources'] as $source) {
                $allSourceNames[] = $source['name'];
            }
        }

        $this->assertContains('Active Source', $allSourceNames);
        $this->assertNotContains('Inactive Source', $allSourceNames);
    }

    public function testGetGroupedSourcesExcludesAutoDisabledSources(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create an active source - should be visible
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://active.com',
                'primaryDomain' => 'active.com',
                'status' => SourceStatus::ACTIVE,
                'collectStatus' => CollectStatus::RUNNING,
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create an auto-disabled source - should NOT be visible
        SourceFactory::new()
            ->with([
                'name' => 'Auto Disabled Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://autodisabled.com',
                'primaryDomain' => 'autodisabled.com',
                'status' => SourceStatus::AUTO_DISABLED,
                'collectStatus' => CollectStatus::STOPPED,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Should only have 1 source (the active one)
        $this->assertEquals(1, $data['summary']['total']);

        // Collect all source names from groups
        $allSourceNames = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['sources'] as $source) {
                $allSourceNames[] = $source['name'];
            }
        }

        $this->assertContains('Active Source', $allSourceNames);
        $this->assertNotContains('Auto Disabled Source', $allSourceNames);
    }

    public function testGetGroupedSourcesErrorGroupExcludesDisabledSources(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create an active source with error status - should be visible in error group
        SourceFactory::new()
            ->with([
                'name' => 'Active Error Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://activeerror.com',
                'primaryDomain' => 'activeerror.com',
                'status' => SourceStatus::ACTIVE,
                'collectStatus' => CollectStatus::ERROR,
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create an inactive source with error status - should NOT be visible
        SourceFactory::new()
            ->with([
                'name' => 'Inactive Error Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://inactiveerror.com',
                'primaryDomain' => 'inactiveerror.com',
                'status' => SourceStatus::INACTIVE,
                'collectStatus' => CollectStatus::ERROR,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Should only have 1 source total
        $this->assertEquals(1, $data['summary']['total']);
        $this->assertEquals(1, $data['summary']['error']);

        // Find error group and verify only active error source is present
        $errorGroup = null;
        foreach ($data['groups'] as $group) {
            if ('error' === $group['type']) {
                $errorGroup = $group;
                break;
            }
        }

        $this->assertNotNull($errorGroup);
        $this->assertCount(1, $errorGroup['sources']);
        $this->assertEquals('Active Error Source', $errorGroup['sources'][0]['name']);
    }

    public function testGetGroupedSourcesOnlyShowsActiveSources(): void
    {
        $watchFileOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        // Create 3 active sources
        for ($i = 1; $i <= 3; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => 'Active Source ' . $i,
                    'type' => SourceType::WEBSITE,
                    'url' => 'https://active' . $i . '.com',
                    'primaryDomain' => 'active' . $i . '.com',
                    'status' => SourceStatus::ACTIVE,
                    'collectStatus' => CollectStatus::RUNNING,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        // Create 2 inactive sources
        for ($i = 1; $i <= 2; ++$i) {
            SourceFactory::new()
                ->with([
                    'name' => 'Inactive Source ' . $i,
                    'type' => SourceType::WEBSITE,
                    'url' => 'https://inactive' . $i . '.com',
                    'primaryDomain' => 'inactive' . $i . '.com',
                    'status' => SourceStatus::INACTIVE,
                    'collectStatus' => CollectStatus::STOPPED,
                    'watchFile' => $watchFile,
                ])
                ->create();
        }

        // Create 1 auto-disabled source
        SourceFactory::new()
            ->with([
                'name' => 'Auto Disabled Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://autodisabled.com',
                'primaryDomain' => 'autodisabled.com',
                'status' => SourceStatus::AUTO_DISABLED,
                'collectStatus' => CollectStatus::STOPPED,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources/grouped');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Should only have 3 sources (the active ones)
        $this->assertEquals(3, $data['summary']['total']);

        // Collect all source names from groups
        $allSourceNames = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['sources'] as $source) {
                $allSourceNames[] = $source['name'];
            }
        }

        // Verify only active sources are present
        $this->assertCount(3, $allSourceNames);
        $this->assertContains('Active Source 1', $allSourceNames);
        $this->assertContains('Active Source 2', $allSourceNames);
        $this->assertContains('Active Source 3', $allSourceNames);

        // Verify inactive sources are not present
        $this->assertNotContains('Inactive Source 1', $allSourceNames);
        $this->assertNotContains('Inactive Source 2', $allSourceNames);
        $this->assertNotContains('Auto Disabled Source', $allSourceNames);
    }
}
