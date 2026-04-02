<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFileActivity\WatchFileActivityFactory;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class EventSourcesApiTest extends AbstractApiTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetEventSourcesBasic(): void
    {
        // Create test data using factories
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne([
            'createdBy' => $user,
            'status' => WatchFileStatus::DRAFT,
        ]);

        $source = SourceFactory::createOne([
            'name' => 'Test Source',
            'type' => SourceType::RSS_FEED,
            'primaryDomain' => 'example.com',
            'watchFile' => $watchFile,
        ]);

        $activity = WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::SOURCE_STATUS_CHANGED,
            'actionData' => [
                'source_id' => $source->getId(),
                'source_name' => 'Test Source',
                'old_status' => 'active',
                'new_status' => 'inactive',
            ],
        ]);

        // Make API call
        $client = $this->createAuthenticatedClient($user);
        $response = $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/timeline/' . $activity->getId() . '/sources'
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('event', $data);
        $this->assertArrayHasKey('sources', $data);
        $this->assertArrayHasKey('count', $data);

        // Check event data
        $this->assertEquals($activity->getId(), $data['event']['id']);
        $this->assertEquals('source_status_changed', $data['event']['type']);

        // Check sources data
        $this->assertEquals(1, $data['count']);
        $this->assertCount(1, $data['sources']);

        $sourceData = $data['sources'][0];
        $this->assertEquals($source->getId(), $sourceData['id']);
        $this->assertEquals('Test Source', $sourceData['name']);
        $this->assertEquals('example.com', $sourceData['primaryDomain']);
        $this->assertEquals('rss_feed', $sourceData['type']);
    }

    public function testGetEventSourcesNotFound(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne([
            'createdBy' => $user,
            'status' => WatchFileStatus::DRAFT,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $client->request(
            'GET',
            '/api/watch_files/' . $watchFile->getId() . '/timeline/550e8400-e29b-41d4-a716-446655440000/sources'
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetEventSourcesUnauthorized(): void
    {
        $this->createClient()
->request('GET', '/api/watch_files/some-id/timeline/some-event-id/sources');
        $this->assertResponseStatusCodeSame(401);
    }
}
