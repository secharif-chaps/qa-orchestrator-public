<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\WatchFileActivity\NullWatchFileActivityGateway;

/**
 * Integration tests for WatchFile Timeline API endpoint.
 */
class WatchFileTimelineApiTest extends AbstractApiTestCase
{
    private NullWatchFileActivityGateway $activityGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityGateway = new NullWatchFileActivityGateway();
        self::getContainer()->set(
            'App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface',
            $this->activityGateway
        );
    }

    public function testGetTimelineReturnsEmptyListForNewWatchFile(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('GroupedWatchFileActivityDto', $data['@type']);
        $this->assertArrayHasKey('activitiesByDay', $data);
        $this->assertIsArray($data['activitiesByDay']);
        $this->assertCount(0, $data['activitiesByDay']); // New WatchFile should have no activities

        // Check totalItems in Timeline metadata
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertEquals(0, $data['totalItems']);
    }

    public function testGetTimelineWithPaginationParameters(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history", [
            'query' => [
                'page' => 2,
                'itemsPerPage' => 10,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        // With Timeline object, pagination info is in Timeline metadata
        // Check that the request succeeded and has correct JSON-LD structure
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('GroupedWatchFileActivityDto', $data['@type']);
    }

    public function testGetTimelineWithInvalidPaginationParameters(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);

        // Test invalid page (should return empty result instead of 500 error)
        $response = $client->request(
            'GET',
            "/api/watch_files/{$watchFileId}/history",
            [
                'query' => [
                    'page' => 0,
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetTimelineWithInvalidLimitParameter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);

        // Test invalid limit (API Platform normalizes to max)
        $response = $client->request(
            'GET',
            "/api/watch_files/{$watchFileId}/history",
            [
                'query' => [
                    'itemsPerPage' => 101,
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testGetTimelineReturns404ForNonExistentWatchFile(): void
    {
        $user = UserFactory::createOne();
        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', '/api/watch_files/00000000-0000-0000-0000-000000000000/history');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetTimelineRequiresAuthentication(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        $client = static::createClient();

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");

        $this->assertResponseStatusCodeSame(401);
    }
}
