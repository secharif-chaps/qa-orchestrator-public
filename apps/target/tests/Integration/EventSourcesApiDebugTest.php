<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFileActivity\WatchFileActivityFactory;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;

class EventSourcesApiDebugTest extends AbstractApiTestCase
{
    public function testEventSourcesDebugResponse(): void
    {
        // Create test data using factories
        $user = UserFactory::createOne([
            'email' => 'debug@test.com',
            'firstName' => 'Debug',
            'lastName' => 'User',
        ]);

        $watchFile = WatchFileFactory::createOne([
            'name' => 'Debug WatchFile',
            'createdBy' => $user,
            'status' => WatchFileStatus::ENABLED,
        ]);

        $source = SourceFactory::createOne([
            'name' => 'Debug Source',
            'type' => SourceType::RSS_FEED,
            'primaryDomain' => 'debug.com',
            'watchFile' => $watchFile,
            'status' => SourceStatus::ACTIVE,
        ]);

        $activity = WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::SOURCE_STATUS_CHANGED,
            'actionData' => [
                'source_id' => $source->getId(),
                'source_name' => 'Debug Source',
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

        // Check that we have the expected structure
        $this->assertArrayHasKey('event', $data);
        $this->assertArrayHasKey('sources', $data);
        $this->assertArrayHasKey('count', $data);

        // Additional debug
        $this->assertIsArray($data['event'], 'Event should be an array');
        $this->assertIsArray($data['sources'], 'Sources should be an array');
        $this->assertIsInt($data['count'], 'Count should be an integer');
    }
}
