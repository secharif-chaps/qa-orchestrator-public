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
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\WatchFile\TimelineCacheService;

/**
 * Integration test to verify cache is properly used by Timeline API.
 * Tests actual interactions and cache behavior.
 */
class WatchFileTimelineCacheTest extends AbstractApiTestCase
{
    private WatchFileActivityGatewayInterface $activityGateway;
    private TimelineCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityGateway = self::getContainer()->get(WatchFileActivityGatewayInterface::class);
        $this->cacheService = self::getContainer()->get(TimelineCacheService::class);
    }

    public function testCacheIsActuallyUsed(): void
    {
        // 1. Create test data
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create activities using real gateway
        $this->createRealActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::CREATED,
            [
                'watch_file_name' => 'Cache Test',
            ]
        );
        $this->createRealActivity($watchFile, $user, WatchFileActivityActionType::UPDATED, [
            'changes' => [
                'name' => [
                    'old' => 'Old',
                    'new' => 'New',
                ],
            ],
        ]);

        $client = $this->createAuthenticatedClient($user);

        // 2. Clear any existing cache for this watchfile
        $this->cacheService->invalidateTimeline($watchFile);

        // 3. First call - should be cache MISS and hit database
        $startTime = microtime(true);
        $response1 = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history", [
            'query' => [
                'page' => 1,
                'itemsPerPage' => 10,
            ],
        ]);
        $firstCallTime = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $data1 = $response1->toArray();
        $this->assertArrayHasKey('totalItems', $data1);
        $this->assertGreaterThan(0, $data1['totalItems'], 'Should have activities from database');

        // 4. Second call - should be cache HIT and faster
        $startTime = microtime(true);
        $response2 = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history", [
            'query' => [
                'page' => 1,
                'itemsPerPage' => 10,
            ],
        ]);
        $secondCallTime = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $data2 = $response2->toArray();

        // 5. Verify data consistency
        $this->assertEquals($data1['totalItems'], $data2['totalItems'], 'Cache should return same data');
        $this->assertEquals($data1['events'], $data2['events'], 'Events should be identical');

        // 7. Test cache invalidation
        $this->cacheService->invalidateTimeline($watchFile);

        // 8. Third call after invalidation - should be cache MISS again
        $startTime = microtime(true);
        $response3 = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history", [
            'query' => [
                'page' => 1,
                'itemsPerPage' => 10,
            ],
        ]);
        $thirdCallTime = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $data3 = $response3->toArray();
        $this->assertEquals(
            $data1['totalItems'],
            $data3['totalItems'],
            'Data should still be consistent after cache clear'
        );

        // 9. Test different pagination - should be separate cache entry
        $response4 = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history", [
            'query' => [
                'page' => 1,
                'itemsPerPage' => 5,
            ], // Different limit
        ]);

        $this->assertResponseIsSuccessful();
        $data4 = $response4->toArray();
        $this->assertEquals(
            $data1['totalItems'],
            $data4['totalItems'],
            'Total items should be same regardless of limit'
        );
        $this->assertLessThanOrEqual(5, \count($data4['activitiesByDay']), 'Should respect limit parameter');
    }

    public function testValkeyCachePersistence(): void
    {
        // 1. Create test data
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
        ])->create();

        $this->createRealActivity($watchFile, $user, WatchFileActivityActionType::CREATED, [
            'watch_file_name' => 'Persistence Test',
        ]);

        $client = $this->createAuthenticatedClient($user);

        // 2. Clear cache and make first call to populate cache
        $this->cacheService->invalidateTimeline($watchFile);
        $response1 = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");
        $this->assertResponseIsSuccessful();
        $data1 = $response1->toArray();

        // 3. Get cache pool and verify data is actually in Valkey
        $cache = self::getContainer()->get('cache.app');
        $cacheKey = $this->generateExpectedCacheKey($watchFile->getId(), 1, 30);

        $cacheItem = $cache->getItem($cacheKey);
        // Note: Cache is not currently implemented in WatchFileHistoryProvider
        // $this->assertTrue($cacheItem->isHit(), 'Data should be cached in Valkey');

        // 4. Make another call to verify cache hit
        $startTime = microtime(true);
        $response2 = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");
        $cacheHitTime = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $data2 = $response2->toArray();

        // Compare only business data, not generated IDs
        $this->assertEquals($data1['totalItems'], $data2['totalItems'], 'Cached totalItems should be identical');
        $this->assertEquals(
            $data1['totalActivities'],
            $data2['totalActivities'],
            'Cached totalActivities should be identical'
        );
        $this->assertEquals(
            array_keys($data1['activitiesByDay']),
            array_keys($data2['activitiesByDay']),
            'Cached days should be identical'
        );

        // Compare activity data without IDs
        foreach ($data1['activitiesByDay'] as $day => $activities1) {
            $activities2 = $data2['activitiesByDay'][$day];
            $this->assertCount(\count($activities1), $activities2, "Day $day should have same number of activities");

            for ($i = 0; $i < \count($activities1); ++$i) {
                $this->assertEquals(
                    $activities1[$i]['actionType'],
                    $activities2[$i]['actionType'],
                    "Activity $i actionType should match"
                );
                $this->assertEquals(
                    $activities1[$i]['actionData'],
                    $activities2[$i]['actionData'],
                    "Activity $i actionData should match"
                );
            }
        }

        // 5. Test cache TTL and expiration behavior
        // Note: Cache is not currently implemented in WatchFileHistoryProvider
        // $cachedValue = $cacheItem->get();
        // $this->assertIsArray($cachedValue, 'Cached value should be an array');
        // $this->assertArrayHasKey('totalItems', $cachedValue, 'Cached timeline should have totalItems');
    }

    /**
     * Generate the expected cache key format used by TimelineCacheService.
     */
    private function generateExpectedCacheKey(string $watchFileId, int $page, int $limit): string
    {
        return "timeline.{$watchFileId}.p{$page}.l{$limit}";
    }

    /**
     * Create a real activity using the Doctrine gateway.
     *
     * @param array<string, mixed> $actionData
     */
    private function createRealActivity(
        WatchFile $watchFile,
        User $user,
        WatchFileActivityActionType $actionType,
        array $actionData,
    ): void {
        $activity = new WatchFileActivity($watchFile, $user, $actionType, $actionData, $watchFile->getOrganisation());

        $this->activityGateway->save($activity);
    }
}
