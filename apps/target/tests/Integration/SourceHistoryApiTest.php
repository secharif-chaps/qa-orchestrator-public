<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Source\Source;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Utils\EntityUtilsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;

class SourceHistoryApiTest extends AbstractApiTestCase
{
    use EntityUtilsTrait;

    public function testGetSourceHistorySuccessfully(): void
    {
        $watchFileOwner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('activitiesByDay', $data);
        $this->assertArrayHasKey('totalItems', $data);

        $this->assertIsArray($data['activitiesByDay']);
        $this->assertEquals(0, $data['totalItems']);
        $this->assertEmpty($data['activitiesByDay']);
    }

    public function testGetSourceHistoryWithEmptySource(): void
    {
        $watchFileOwner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertEmpty($data['activitiesByDay']);
        $this->assertEquals(0, $data['totalItems']);
        $this->assertEquals(0, $data['daysCount']);
        $this->assertEmpty($data['days']);
    }

    public function testGetSourceHistoryWithPagination(): void
    {
        $watchFileOwner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivities($source, 25);

        $client = $this->createAuthenticatedClient($watchFileOwner);

        $response = $client->request('GET', '/api/sources/' . $source->getId() . '/history?page=1&itemsPerPage=10');
        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertLessThanOrEqual(25, $data['totalItems']);

        $response = $client->request('GET', '/api/sources/' . $source->getId() . '/history?page=2&itemsPerPage=10');
        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertIsArray($data['activitiesByDay']);
    }

    public function testGetSourceHistoryWithDifferentDays(): void
    {
        $watchFileOwner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivitiesAcrossDays($source, 3, 3);

        $client = $this->createAuthenticatedClient($watchFileOwner);
        $response = $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertGreaterThan(1, \count($data['activitiesByDay']));
        $this->assertEquals(3, $data['totalItems']);

        $days = array_keys($data['activitiesByDay']);
        $this->assertGreaterThan(1, \count($days));

        for ($i = 0; $i < \count($days) - 1; ++$i) {
            $this->assertGreaterThanOrEqual($days[$i + 1], $days[$i]);
        }
    }

    public function testPaginationValidation(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', '/api/sources/' . $source->getId() . '/history?page=1&itemsPerPage=20');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/api/sources/' . $source->getId() . '/history?page=0');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $client->request('GET', '/api/sources/' . $source->getId() . '/history?page=-1');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $client->request('GET', '/api/sources/' . $source->getId() . '/history?itemsPerPage=0');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/api/sources/' . $source->getId() . '/history?itemsPerPage=-5');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $client->request('GET', '/api/sources/' . $source->getId() . '/history?itemsPerPage=1000');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/api/sources/' . $source->getId() . '/history?page=abc');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $client->request('GET', '/api/sources/' . $source->getId() . '/history?itemsPerPage=xyz');
        $this->assertResponseIsSuccessful();
    }

    public function testUnauthenticatedUserCannotAccessSourceHistory(): void
    {
        $watchFileOwner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = static::createClient();
        $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUserCannotAccessSourceHistoryFromDifferentWatchFile(): void
    {
        $sourceOwner = UserFactory::new()->create();
        $otherUser = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($sourceOwner)
            ->withOwnedBy($sourceOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivities($source, 5);

        $client = $this->createAuthenticatedClient($otherUser);
        $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testViewerCanAccessSourceHistory(): void
    {
        $owner = UserFactory::new()->create();
        $viewer = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivities($source, 3);

        $client = $this->createAuthenticatedClient($viewer);
        $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseIsSuccessful();
    }

    public function testEditorCanAccessSourceHistory(): void
    {
        $owner = UserFactory::new()->create();
        $editor = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($editor, WatchFileUserRole::EDITOR)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivities($source, 3);

        $client = $this->createAuthenticatedClient($editor);
        $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseIsSuccessful();
    }

    public function testUserWithNoRoleCannotAccessSourceHistory(): void
    {
        $owner = UserFactory::new()->create();
        $stranger = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($stranger);
        $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAccessToNonExistentSourceReturns403(): void
    {
        $user = UserFactory::new()->create();
        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', '/api/sources/00000000-0000-0000-0000-000000000000/history');

        // Security voter denies access when source is not found (does not leak resource existence)
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAccessToSourceWithInvalidIdFormatReturns403(): void
    {
        $user = UserFactory::new()->create();
        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', '/api/sources/invalid-uuid/history');

        // Security voter denies access when source ID is invalid (does not leak resource existence)
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCacheIsUsedForRepeatedRequests(): void
    {
        $watchFileOwner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivities($source, 10);

        $client = $this->createAuthenticatedClient($watchFileOwner);

        $cache = $this
            ->getContainer()
            ->get(CacheInterface::class);
        $cache->clear();

        $startTime = microtime(true);
        $response1 = $client->request('GET', '/api/sources/' . $source->getId() . '/history');
        $firstRequestTime = microtime(true) - $startTime;

        $this->assertResponseIsSuccessful();
        $data1 = $response1->toArray();

        $startTime = microtime(true);
        $response2 = $client->request('GET', '/api/sources/' . $source->getId() . '/history');
        $secondRequestTime = microtime(true) - $startTime;

        $this->assertResponseIsSuccessful();
        $data2 = $response2->toArray();

        $this->assertEquals($data1['totalItems'], $data2['totalItems']);
        $this->assertEquals($data1['activitiesByDay'], $data2['activitiesByDay']);
    }

    public function testPerformanceWithLargeDataset(): void
    {
        $watchFileOwner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($watchFileOwner)
            ->withOwnedBy($watchFileOwner)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivities($source, 1000);

        $client = $this->createAuthenticatedClient($watchFileOwner);

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $response = $client->request('GET', '/api/sources/' . $source->getId() . '/history?itemsPerPage=50');

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->assertResponseIsSuccessful();

        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        $this->assertLessThan(2.0, $executionTime, 'Request should complete in less than 2 seconds');
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be less than 50MB');

        $data = $response->toArray();
        $this->assertLessThanOrEqual(1000, $data['totalItems'], 'Should not exceed total activities in database');
    }

    public function testResponseDataValidation(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivities($source, 10);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('activitiesByDay', $data);
        $this->assertArrayHasKey('totalItems', $data);

        $this->assertIsArray($data['activitiesByDay']);
        $this->assertIsInt($data['totalItems']);

        $this->assertEquals(10, $data['totalItems']);

        foreach ($data['activitiesByDay'] as $day => $activities) {
            $this->assertIsString($day);
            $this->assertIsArray($activities);
            $this->assertGreaterThan(0, \count($activities));

            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $day);

            foreach ($activities as $activity) {
                $this->assertIsArray($activity);
                $this->assertArrayHasKey('id', $activity);
                $this->assertArrayHasKey('actionType', $activity);
                $this->assertArrayHasKey('actionData', $activity);
                $this->assertArrayHasKey('createdAt', $activity);
                $this->assertArrayHasKey('user', $activity);

                $this->assertIsString($activity['id']);
                $this->assertIsString($activity['actionType']);
                $this->assertIsArray($activity['actionData']);

                $this->assertMatchesRegularExpression(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                    $activity['id']
                );

                $validActionTypes = array_map(fn ($case) => $case->value, SourceActivityActionType::cases());
                $this->assertContains($activity['actionType'], $validActionTypes);

                $this->assertIsString($activity['createdAt']);
                $date = \DateTime::createFromFormat(\DateTime::ATOM, $activity['createdAt']);
                $this->assertNotFalse($date, 'createdAt should be valid ISO 8601 date');

                $this->assertIsArray($activity['user']);
                $this->assertArrayHasKey('@id', $activity['user']);
                $this->assertIsString($activity['user']['@id']);
                $this->assertStringStartsWith('/api/users/', $activity['user']['@id']);
            }
        }
    }

    public function testDateSortingValidation(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $this->createSourceActivitiesAcrossDays($source, 15, 5);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/sources/' . $source->getId() . '/history');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertGreaterThan(1, \count($data['activitiesByDay']));
        $this->assertEquals(15, $data['totalItems']);

        $days = array_keys($data['activitiesByDay']);
        $this->assertGreaterThan(1, \count($days));

        $sortedDays = $days;
        rsort($sortedDays);
        $this->assertEquals($sortedDays, $days, 'Days should be sorted in descending order');

        foreach ($data['activitiesByDay'] as $day => $activities) {
            if (\count($activities) > 1) {
                for ($i = 0; $i < \count($activities) - 1; ++$i) {
                    $currentActivity = new \DateTime($activities[$i]['createdAt']);
                    $nextActivity = new \DateTime($activities[$i + 1]['createdAt']);
                    $this->assertGreaterThanOrEqual(
                        $currentActivity,
                        $nextActivity,
                        'Activities within a day should be sorted by creation time'
                    );
                }
            }
        }
    }

    private function createSourceActivities(Source $source, int $count): void
    {
        $user = $source->getWatchFile()
->getCreatedBy();
        if (!$user) {
            throw new \RuntimeException('User not found for source');
        }
        $entityManager = $this->getEntityManager();

        $sourceId = $source->getId();
        $realSource = $entityManager->find(Source::class, $sourceId);

        if (!$realSource) {
            throw new \RuntimeException('Source not found in database');
        }

        $visibleTypes = array_filter(
            SourceActivityActionType::cases(),
            fn (SourceActivityActionType $type) => SourceActivityActionType::SOURCE_QUERY_LOG !== $type
                && SourceActivityActionType::SOURCE_COLLECT_LOG !== $type,
        );

        for ($i = 0; $i < $count; ++$i) {
            $actionType = $visibleTypes[array_rand($visibleTypes)];

            $activity = new SourceActivity(
                $realSource,
                $user,
                $actionType,
                [
                    'test_data' => 'value_' . $i,
                    'timestamp' => new \DateTime()
->format('c'),
                ]
            );

            $entityManager->persist($activity);
        }

        $entityManager->flush();
    }

    private function createSourceActivitiesAcrossDays(Source $source, int $totalCount, int $daysCount): void
    {
        $user = $source->getWatchFile()
->getCreatedBy();
        if (!$user) {
            throw new \RuntimeException('User not found for source');
        }
        $entityManager = $this->getEntityManager();
        $activitiesPerDay = (int) ($totalCount / $daysCount);

        $sourceId = $source->getId();
        $realSource = $entityManager->find(Source::class, $sourceId);

        if (!$realSource) {
            throw new \RuntimeException('Source not found in database');
        }

        $visibleTypes = array_filter(
            SourceActivityActionType::cases(),
            fn (SourceActivityActionType $type) => SourceActivityActionType::SOURCE_QUERY_LOG !== $type
                && SourceActivityActionType::SOURCE_COLLECT_LOG !== $type,
        );

        for ($day = 0; $day < $daysCount; ++$day) {
            for ($i = 0; $i < $activitiesPerDay; ++$i) {
                $actionType = $visibleTypes[array_rand($visibleTypes)];

                $activity = new SourceActivity(
                    $realSource,
                    $user,
                    $actionType,
                    [
                        'test_data' => 'value_' . $day . '_' . $i,
                        'timestamp' => new \DateTime()
->format('c'),
                    ]
                );

                $createdAt = new \DateTime('-' . $day . ' days');
                $this->forcePropertyValue($activity, $createdAt, 'createdAt');

                $entityManager->persist($activity);
            }
        }

        $entityManager->flush();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $manager = self::getContainer()->get('doctrine')->getManager();
        \assert($manager instanceof EntityManagerInterface);

        return $manager;
    }
}
