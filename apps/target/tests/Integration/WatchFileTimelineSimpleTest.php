<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\WatchFileActivity\NullWatchFileActivityGateway;

/**
 * Simple integration tests for WatchFile Timeline API endpoint.
 */
class WatchFileTimelineSimpleTest extends AbstractApiTestCase
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

        // The response is a JSON-LD Timeline object with events directly accessible
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('GroupedWatchFileActivityDto', $data['@type']);
        $this->assertArrayHasKey('activitiesByDay', $data);
        $this->assertIsArray($data['activitiesByDay']);
        $this->assertCount(0, $data['activitiesByDay']); // New WatchFile should have no activities

        // Check timeline metadata
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertEquals(0, $data['totalItems']);
    }
}
