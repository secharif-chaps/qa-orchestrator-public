<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFileActivity\WatchFileActivityFactory;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use Symfony\Component\HttpFoundation\Response;

class WatchFileTimelineRealDataTest extends AbstractApiTestCase
{
    public function testGetTimelineWithRealActivities(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])->create();

        WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::CREATED,
            'actionData' => [
                'test' => 'data1',
            ],
        ]);

        WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::STATUS_CHANGED,
            'actionData' => [
                'old_status' => 'draft',
                'new_status' => 'enabled',
            ],
        ]);

        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");

        $data = $response->toArray();

        // Check the JSON-LD Timeline structure
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
        $eventTypes = array_map(fn ($activity) => $activity['actionType'], $activities);
        $this->assertContains('created', $eventTypes);
        $this->assertContains('status_changed', $eventTypes);

        // Check totalItems in Timeline metadata (now represents number of days in current page)
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertEquals(1, $data['totalItems']); // 1 day with activities

        // Check totalActivities for the actual number of activities
        $this->assertArrayHasKey('totalActivities', $data);
        $this->assertEquals(2, $data['totalActivities']); // 2 activities total
    }

    public function testPaginationWithLimit2(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])->create();
        $watchFileId = $watchFile->getId();

        WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::CREATED,
            'actionData' => [
                'test' => 'data1',
            ],
        ]);

        WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::STATUS_CHANGED,
            'actionData' => [
                'old_status' => 'draft',
                'new_status' => 'enabled',
            ],
        ]);

        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/history?itemsPerPage=1&page=1");

        $this->assertResponseIsSuccessful();

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertArrayHasKey('activitiesByDay', $data);
        $this->assertIsArray($data['activitiesByDay']);

        // Should have maximum 1 day
        $this->assertLessThanOrEqual(1, $data['totalItems']);
        $this->assertLessThanOrEqual(1, \count($data['activitiesByDay']));

        $response2 = $client->request('GET', "/api/watch_files/{$watchFileId}/history?itemsPerPage=30&page=1");
        $data2 = json_decode($response2->getContent(), true);
        $this->assertIsArray($data2);
        /** @var array<string, mixed> $data2 */
        $this->assertArrayHasKey('totalItems', $data2);
        $this->assertArrayHasKey('totalActivities', $data2);
    }

    public function testActionDataAlwaysArray(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create multiple activities with the same actionType and timestamp (should be grouped)
        $sameTimestamp = new \DateTime('2024-09-25 10:00:00');

        WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::SOURCE_STATUS_CHANGED,
            'actionData' => [
                'source_name' => 'Test Source 1',
                'source_id' => 'test-1',
                'status' => 'active',
            ],
            'createdAt' => $sameTimestamp,
        ]);

        WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::SOURCE_STATUS_CHANGED,
            'actionData' => [
                'source_name' => 'Test Source 2',
                'source_id' => 'test-2',
                'status' => 'active',
            ],
            'createdAt' => $sameTimestamp,
        ]);

        // Add a non-groupable activity (different actionType)
        WatchFileActivityFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::CREATED,
            'actionData' => [
                'test' => 'data',
            ],
            'createdAt' => new \DateTime('2024-09-25 11:00:00'),
        ]);

        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");

        $this->assertResponseIsSuccessful();

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertArrayHasKey('activitiesByDay', $data);
        $this->assertIsArray($data['activitiesByDay']);

        // Check that actionData is always an array
        foreach ($data['activitiesByDay'] as $day => $activities) {
            $this->assertIsString($day);
            $this->assertIsArray($activities);
            foreach ($activities as $activity) {
                $this->assertIsArray($activity);
                $this->assertIsArray($activity['actionData'], 'actionData should always be an array');
            }
        }
    }

    public function testGetTimelineWithDifferentActivityTypes(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $activityTypes = [
            WatchFileActivityActionType::CREATED,
            WatchFileActivityActionType::STATUS_CHANGED,
            WatchFileActivityActionType::ACTOR_STATUS_CHANGED,
        ];

        foreach ($activityTypes as $actionType) {
            WatchFileActivityFactory::createOne([
                'watchFile' => $watchFile,
                'user' => $user,
                'actionType' => $actionType,
                'actionData' => [
                    'test' => 'data',
                ],
            ]);
        }

        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertIsArray($data['activitiesByDay']); // Activities grouped by day

        // Check that all activity types are present
        $activitiesByDay = $data['activitiesByDay'];
        $allActivities = [];
        foreach ($activitiesByDay as $dayActivities) {
            $this->assertIsArray($dayActivities);
            $allActivities = array_merge($allActivities, $dayActivities);
        }
        /** @var array<array<string, mixed>> $allActivities */
        $eventTypes = array_map(fn ($activity) => $activity['actionType'] ?? '', $allActivities);

        $this->assertContains('created', $eventTypes);
        $this->assertContains('status_changed', $eventTypes);
        $this->assertContains('actor_status_changed', $eventTypes);
    }

    public function testGetTimelineForNonExistentWatchFile(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', '/api/watch_files/550e8400-e29b-41d4-a716-446655440000/history');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetTimelineWithInvalidPagination(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();
        $watchFileId = $watchFile->getId();

        $client = $this->createAuthenticatedClient($user);

        // Test with invalid page (API Platform returns 400 error)
        $client->request('GET', "/api/watch_files/{$watchFileId}/history", [
            'query' => [
                'page' => 0,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400); // Page 0 should return 400 error

        // Test with invalid limit (API Platform normalizes to max)
        $client->request(
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
}
