<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Tests\Units\Infrastructure\WatchFileActivity\NullWatchFileActivityGateway;

/**
 * Integration tests for WatchFile Timeline API endpoint with activities.
 */
class WatchFileTimelineWithActivitiesTest extends AbstractApiTestCase
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

    public function testGetTimelineReturnsActivitiesInChronologicalOrder(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        // Create some activities with different timestamps (most recent first)
        $now = new \DateTime();

        $this->createMockActivity($watchFile, $user, WatchFileActivityActionType::STATUS_CHANGED, [
            'old_status' => 'draft',
            'new_status' => 'enabled',
        ], $now);

        $this->createMockActivity($watchFile, $user, WatchFileActivityActionType::UPDATED, [
            'changes' => [
                'name' => [
                    'old' => 'Old Name',
                    'new' => 'New Name',
                ],
            ],
        ], $now->modify('-1 hour'));

        $this->createMockActivity($watchFile, $user, WatchFileActivityActionType::CREATED, [
            'watch_file_name' => 'Test WatchFile',
        ], $now->modify('-2 hours'));

        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('GroupedWatchFileActivityDto', $data['@type']);
        $this->assertArrayHasKey('activitiesByDay', $data);
        $this->assertIsArray($data['activitiesByDay']);
        $this->assertCount(1, $data['activitiesByDay']); // One day with activities

        // Activities are grouped by day
        $activitiesByDay = $data['activitiesByDay'];
        $firstDay = array_keys($activitiesByDay)[0];
        $activities = $activitiesByDay[$firstDay];
        /** @var array<int, array<string, mixed>> $activities */

        // Check that activities are in chronological order (most recent first)
        $this->assertEquals('status_changed', $activities[0]['actionType']);
        $this->assertEquals('updated', $activities[1]['actionType']);
        $this->assertEquals('created', $activities[2]['actionType']);

        // Check activity structure
        $firstActivity = $activities[0];
        /** @var array<string, mixed> $firstActivity */
        $this->assertArrayHasKey('id', $firstActivity);
        $this->assertArrayHasKey('actionType', $firstActivity);
        $this->assertArrayHasKey('createdAt', $firstActivity);
        $this->assertArrayHasKey('user', $firstActivity);
        $this->assertArrayHasKey('actionData', $firstActivity);

        // Check user structure
        $userData = $firstActivity['user'];
        /** @var array<string, mixed> $userData */
        $this->assertArrayHasKey('@id', $userData);
        $this->assertArrayHasKey('@type', $userData);
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('email', $userData);

        // Check totalItems in collection metadata
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertEquals(1, $data['totalItems']); // Number of days
        $this->assertEquals(3, $data['totalActivities']); // Number of activities
    }

    public function testGetTimelineWithPagination(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        // Create 5 activities with different timestamps
        for ($i = 0; $i < 5; ++$i) {
            // Create activity with a small delay to ensure different timestamps
            usleep(1000); // 1ms delay
            $this->createMockActivity($watchFile, $user, WatchFileActivityActionType::UPDATED, [
                'changes' => [
                    'name' => [
                        'old' => "Old Name {$i}",
                        'new' => "New Name {$i}",
                    ],
                ],
            ]);
        }

        $client = $this->createAuthenticatedClient($user);

        // Test first page
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history", [
            'query' => [
                'page' => 1,
                'itemsPerPage' => 3,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertIsArray($data['activitiesByDay']); // Activities grouped by day
        $this->assertEquals(1, $data['totalItems']); // Number of days
        $this->assertEquals(3, $data['totalActivities']); // Number of activities on the current page

        // Test pagination structure works (first page was successful above)
    }

    public function testGetTimelineWithDifferentActivityTypes(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        // Test different activity types with different timestamps (most recent first)
        $now = new \DateTime();

        $this->createMockActivity($watchFile, $user, WatchFileActivityActionType::REFERENCE_SUBJECT_UPDATED, [
            'reference_subject' => [
                'data' => 'AI detected context',
            ],
        ], $now);

        $this->createMockActivity($watchFile, $user, WatchFileActivityActionType::MONITORING_TYPE_DETECTED, [
            'detected_monitoring_type' => 'competitor',
            'confidence_score' => 0.95,
        ], (clone $now)->modify('-1 hour'));

        $this->createMockActivity($watchFile, $user, WatchFileActivityActionType::SOURCE_STATUS_CHANGED, [
            'source_id' => 'source-456',
            'source_name' => 'Test Source',
            'status' => 'active',
            'old_status' => 'inactive',
        ], (clone $now)->modify('-2 hours'));

        $this->createMockActivity($watchFile, $user, WatchFileActivityActionType::ACTOR_STATUS_CHANGED, [
            'actor_id' => 'actor-123',
            'actor_name' => 'Test Actor',
            'status' => 'active',
            'old_status' => 'inactive',
        ], (clone $now)->modify('-3 hours'));

        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertIsArray($data['activitiesByDay']); // Activities grouped by day

        $activitiesByDay = $data['activitiesByDay'];
        $allActivities = [];
        foreach ($activitiesByDay as $dayActivities) {
            $this->assertIsArray($dayActivities);
            $allActivities = array_merge($allActivities, $dayActivities);
        }
        /** @var array<array<string, mixed>> $allActivities */
        $this->assertEquals('reference_subject_updated', $allActivities[0]['actionType']);
        $this->assertEquals('monitoring_type_detected', $allActivities[1]['actionType']);
        $this->assertEquals('source_status_changed', $allActivities[2]['actionType']);
        $this->assertEquals('actor_status_changed', $allActivities[3]['actionType']);

        // Check metadata for actor status change
        $actorActivity = $allActivities[3];
        $this->assertIsArray($actorActivity['actionData']);
        $rawActionData = $actorActivity['actionData'];
        /** @var array<string, mixed> $actorActivityData */
        $actorActivityData = $rawActionData[0] ?? $rawActionData;
        $this->assertEquals('actor-123', $actorActivityData['actor_id']);
        $this->assertEquals('Test Actor', $actorActivityData['actor_name']);
        $this->assertEquals('active', $actorActivityData['status']);
        $this->assertEquals('inactive', $actorActivityData['old_status']);
    }

    /**
     * @param array<string, mixed> $actionData
     */
    private function createMockActivity(
        WatchFile $watchFile,
        User $user,
        WatchFileActivityActionType $actionType,
        array $actionData,
        ?\DateTime $createdAt = null,
    ): void {
        $activity = new WatchFileActivity($watchFile, $user, $actionType, $actionData, $watchFile->getOrganisation());

        if ($createdAt) {
            // Use reflection to set the createdAt timestamp
            $reflection = new \ReflectionClass($activity);
            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setAccessible(true);
            $createdAtProperty->setValue($activity, $createdAt);
        }

        $this->activityGateway->save($activity);
    }
}
