<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFileEvent;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Domain\WatchFileEvent\EventType;
use App\Domain\WatchFileEvent\ExtractionStatus;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Tests\Integration\AbstractApiTestCase;
use OpenSearch\Client;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class WatchFileEventApiTest extends AbstractApiTestCase
{
    protected function tearDown(): void
    {
        $this->cleanupWatchFileEventsOpenSearch();
        parent::tearDown();
    }

    public function testGetWatchFileEventsAsOwner(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();

        // Check pagination structure (OpenSearch returns 'member' not 'hydra:member')
        $this->assertArrayHasKey('member', $responseData);
        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertIsArray($responseData['member']);

        // We should have events
        $this->assertGreaterThan(0, $responseData['totalItems']);

        // Verify each event has the expected structure
        foreach ($responseData['member'] as $event) {
            $this->assertIsArray($event);
            $this->assertArrayHasKey('@id', $event);
            $this->assertArrayHasKey('@type', $event);
            $this->assertArrayHasKey('id', $event);
            $this->assertArrayHasKey('title', $event);
            $this->assertIsArray($event['title']);
            $this->assertArrayHasKey('fr', $event['title']);
            $this->assertArrayHasKey('en', $event['title']);
            $this->assertArrayHasKey('startDate', $event);
            $this->assertArrayHasKey('description', $event);
            $this->assertArrayHasKey('eventType', $event);
            $this->assertArrayHasKey('extractionStatus', $event);
            $this->assertArrayHasKey('createdAt', $event);
        }
    }

    public function testGetWatchFileEventsAsViewer(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->create();

        /** @var string $watchFileId */
        $watchFileId = $watchFile->getId();

        // Create and index events
        $events = $this->createEvents($watchFileId);
        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        $client = $this->createAuthenticatedClient($viewer);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events', $watchFileId));

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertGreaterThan(0, $responseData['totalItems']);
    }

    public function testGetWatchFileEventsAsUnauthenticatedUser(): void
    {
        $testData = $this->createTestDataWithEvents();
        $watchFile = $testData['watchFile'];

        $client = static::createClient();
        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events', $watchFile->getId()));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetWatchFileEventsAsNonMember(): void
    {
        $testData = $this->createTestDataWithEvents();
        $watchFile = $testData['watchFile'];

        $otherUser = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($otherUser);
        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events', $watchFile->getId()));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGetWatchFileEventItemAsOwner(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        /** @var Client $openSearch */
        $openSearch = self::getContainer()->get(Client::class);

        $response = $openSearch->search([
            'index' => WatchFileEvent::INDEX_NAME,
            'body' => [
                'query' => [
                    'term' => [
                        'watchFile.id' => $watchFile->getId(),
                    ],
                ],
                'size' => 1,
            ],
        ]);

        $hits = $response['hits']['hits'];
        $this->assertNotEmpty($hits);
        $eventId = $hits[0]['_id'];

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/watch_file_events/' . $eventId);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();

        $this->assertArrayHasKey('@id', $responseData);
        $this->assertArrayHasKey('@type', $responseData);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('title', $responseData);
        $this->assertArrayHasKey('startDate', $responseData);
        $this->assertArrayHasKey('eventType', $responseData);
    }

    public function testGetWatchFileEventItemAsNonMember(): void
    {
        $testData = $this->createTestDataWithEvents();
        $watchFile = $testData['watchFile'];

        /** @var Client $openSearch */
        $openSearch = self::getContainer()->get(Client::class);

        $response = $openSearch->search([
            'index' => WatchFileEvent::INDEX_NAME,
            'body' => [
                'query' => [
                    'term' => [
                        'watchFile.id' => $watchFile->getId(),
                    ],
                ],
                'size' => 1,
            ],
        ]);

        $hits = $response['hits']['hits'];
        $this->assertNotEmpty($hits);
        $eventId = $hits[0]['_id'];

        $otherUser = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($otherUser);
        $client->request('GET', '/api/watch_file_events/' . $eventId);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGetWatchFileEventsFilterBySingleActorId(): void
    {
        $testData = $this->createTestDataWithMultipleActors();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];
        $actorId = $testData['actorIds'][0];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?actors.id=%s', $watchFile->getId(), $actorId)
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);

        // All events should have the specified actor
        foreach ($responseData['member'] as $event) {
            $this->assertIsArray($event);
            $this->assertArrayHasKey('actors', $event);
            $this->assertIsArray($event['actors']);

            $hasActor = false;
            foreach ($event['actors'] as $actor) {
                $this->assertIsArray($actor);
                $this->assertArrayHasKey('id', $actor);
                if ($actor['id'] === $actorId) {
                    $hasActor = true;
                    break;
                }
            }

            $this->assertTrue($hasActor, 'Event should contain the filtered actor');
        }
    }

    public function testGetWatchFileEventsFilterByMultipleActorIds(): void
    {
        $testData = $this->createTestDataWithMultipleActors();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];
        $actorIds = [$testData['actorIds'][0], $testData['actorIds'][1]];

        $client = $this->createAuthenticatedClient($owner);

        $queryString = \sprintf(
            '/api/watch_files/%s/events?actors.id[]=%s&actors.id[]=%s',
            $watchFile->getId(),
            $actorIds[0],
            $actorIds[1]
        );

        $response = $client->request('GET', $queryString);

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);

        // All events should have at least one of the specified actors
        foreach ($responseData['member'] as $event) {
            $this->assertIsArray($event);
            $this->assertArrayHasKey('actors', $event);
            $this->assertIsArray($event['actors']);

            $hasActor = false;
            foreach ($event['actors'] as $actor) {
                $this->assertIsArray($actor);
                $this->assertArrayHasKey('id', $actor);
                if (\in_array($actor['id'], $actorIds, true)) {
                    $hasActor = true;
                    break;
                }
            }

            $this->assertTrue($hasActor, 'Event should contain at least one of the filtered actors');
        }
    }

    public function testGetWatchFileEventsFilterByDateRange(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $startDate = '2024-01-01T00:00:00Z';
        $endDate = '2024-12-31T23:59:59Z';

        $client = $this->createAuthenticatedClient($owner);

        $queryString = \sprintf(
            '/api/watch_files/%s/events?startDate=%s&endDate=%s',
            $watchFile->getId(),
            urlencode($startDate),
            urlencode($endDate)
        );

        $response = $client->request('GET', $queryString);

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);

        // All events should overlap with the specified date range
        foreach ($responseData['member'] as $event) {
            $this->assertIsArray($event);
            $this->assertArrayHasKey('startDate', $event);
            $this->assertIsString($event['startDate']);

            $eventStartDate = new \DateTimeImmutable($event['startDate']);
            $filterEndDate = new \DateTimeImmutable($endDate);

            // Event should start before or at the filter end date
            $this->assertLessThanOrEqual($filterEndDate->getTimestamp(), $eventStartDate->getTimestamp());
        }
    }

    public function testGetWatchFileEventsFilterByStartDateOnly(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $startDate = '2024-01-01T00:00:00Z';

        $client = $this->createAuthenticatedClient($owner);

        $queryString = \sprintf(
            '/api/watch_files/%s/events?startDate=%s',
            $watchFile->getId(),
            urlencode($startDate)
        );

        $response = $client->request('GET', $queryString);

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetWatchFileEventsFilterByEndDateOnly(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $endDate = '2024-12-31T23:59:59Z';

        $client = $this->createAuthenticatedClient($owner);

        $queryString = \sprintf('/api/watch_files/%s/events?endDate=%s', $watchFile->getId(), urlencode($endDate));

        $response = $client->request('GET', $queryString);

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetWatchFileEventsCombinedFilters(): void
    {
        $testData = $this->createTestDataWithMultipleActors();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];
        $actorId = $testData['actorIds'][0];

        $startDate = '2024-01-01T00:00:00Z';
        $endDate = '2024-12-31T23:59:59Z';

        $client = $this->createAuthenticatedClient($owner);

        $queryString = \sprintf(
            '/api/watch_files/%s/events?actors.id=%s&startDate=%s&endDate=%s',
            $watchFile->getId(),
            $actorId,
            urlencode($startDate),
            urlencode($endDate)
        );

        $response = $client->request('GET', $queryString);

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetWatchFileEventsPagination(): void
    {
        $testData = $this->createTestDataWithManyEvents(35);
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // First page
        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?page=1&itemsPerPage=10', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertArrayHasKey('view', $responseData);

        $this->assertEquals(35, $responseData['totalItems']);
        $this->assertCount(10, $responseData['member']);

        // Check pagination links
        $this->assertArrayHasKey('first', $responseData['view']);
        $this->assertArrayHasKey('next', $responseData['view']);
        $this->assertArrayHasKey('last', $responseData['view']);

        // Second page
        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?page=2&itemsPerPage=10', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertCount(10, $responseData['member']);
    }

    public function testGetWatchFileEventsEmptyResult(): void
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        /** @var string $watchFileId */
        $watchFileId = $watchFile->getId();

        // Don't index any events

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events', $watchFileId));

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(0, $responseData['totalItems']);
        $this->assertEmpty($responseData['member']);
    }

    public function testGetWatchFileEventsWithNonExistentWatchFile(): void
    {
        $owner = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($owner);

        $nonExistentId = Uuid::v4()->toString();
        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events', $nonExistentId));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetWatchFileEventsFilterBySingleEventType(): void
    {
        $testData = $this->createTestDataWithDifferentEventTypes();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf(
                '/api/watch_files/%s/events?eventType=%s',
                $watchFile->getId(),
                EventType::COMMERCIAL_BUSINESS->value
            )
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertArrayHasKey('totalItems', $responseData);

        foreach ($responseData['member'] as $event) {
            $this->assertIsArray($event);
            $this->assertArrayHasKey('eventType', $event);
            $this->assertEquals(EventType::COMMERCIAL_BUSINESS->value, $event['eventType']);
        }
    }

    public function testGetWatchFileEventsFilterByMultipleEventTypes(): void
    {
        $testData = $this->createTestDataWithDifferentEventTypes();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $queryString = \sprintf(
            '/api/watch_files/%s/events?eventType[]=%s&eventType[]=%s',
            $watchFile->getId(),
            EventType::COMMERCIAL_BUSINESS->value,
            EventType::FINANCIAL->value
        );

        $response = $client->request('GET', $queryString);

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertArrayHasKey('totalItems', $responseData);

        $allowedTypes = [EventType::COMMERCIAL_BUSINESS->value, EventType::FINANCIAL->value];

        foreach ($responseData['member'] as $event) {
            $this->assertIsArray($event);
            $this->assertArrayHasKey('eventType', $event);
            $this->assertContains(
                $event['eventType'],
                $allowedTypes,
                'Event should have one of the filtered event types'
            );
        }
    }

    public function testGetWatchFileEventsFilterByEventTypeCombinedWithOtherFilters(): void
    {
        $testData = $this->createTestDataWithDifferentEventTypesAndActors();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];
        $actorId = $testData['actorIds'][0];

        $client = $this->createAuthenticatedClient($owner);

        $queryString = \sprintf(
            '/api/watch_files/%s/events?eventType=%s&actors.id=%s',
            $watchFile->getId(),
            EventType::COMMERCIAL_BUSINESS->value,
            $actorId
        );

        $response = $client->request('GET', $queryString);

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);

        foreach ($responseData['member'] as $event) {
            $this->assertIsArray($event);
            $this->assertArrayHasKey('eventType', $event);
            $this->assertEquals(EventType::COMMERCIAL_BUSINESS->value, $event['eventType']);

            $this->assertArrayHasKey('actors', $event);
            $this->assertIsArray($event['actors']);

            $hasActor = false;
            foreach ($event['actors'] as $actor) {
                $this->assertIsArray($actor);
                $this->assertArrayHasKey('id', $actor);
                if ($actor['id'] === $actorId) {
                    $hasActor = true;
                    break;
                }
            }

            $this->assertTrue($hasActor, 'Event should contain the filtered actor');
        }
    }

    public function testGetWatchFileEventsFilterByEventTypeWithDateRange(): void
    {
        $testData = $this->createTestDataWithDifferentEventTypes();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $startDate = '2024-01-01T00:00:00Z';
        $endDate = '2024-12-31T23:59:59Z';

        $queryString = \sprintf(
            '/api/watch_files/%s/events?eventType=%s&startDate=%s&endDate=%s',
            $watchFile->getId(),
            EventType::COMMERCIAL_BUSINESS->value,
            $startDate,
            $endDate
        );

        $response = $client->request('GET', $queryString);

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);

        foreach ($responseData['member'] as $event) {
            $this->assertIsArray($event);
            $this->assertArrayHasKey('eventType', $event);
            $this->assertEquals(EventType::COMMERCIAL_BUSINESS->value, $event['eventType']);

            $this->assertArrayHasKey('startDate', $event);
            $this->assertIsString($event['startDate']);
            $eventStartDate = new \DateTimeImmutable($event['startDate']);
            $filterStartDate = new \DateTimeImmutable($startDate);
            $filterEndDate = new \DateTimeImmutable($endDate);

            $this->assertLessThanOrEqual($filterEndDate, $eventStartDate, 'Event start date should be within range');
        }
    }

    public function testGetWatchFileEventsFilterByInvalidEventType(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?eventType=invalid_event_type', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertIsInt($responseData['totalItems']);
    }

    public function testGetWatchFileEventsOrderByStartDate(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?order[startDate]=asc', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $events = $responseData['member'] ?? [];

        if (\count($events) > 1) {
            $previousDate = null;
            foreach ($events as $event) {
                $currentDate = new \DateTimeImmutable($event['startDate']);

                if (null !== $previousDate) {
                    $this->assertGreaterThanOrEqual($previousDate->getTimestamp(), $currentDate->getTimestamp());
                }

                $previousDate = $currentDate;
            }
        }
    }

    public function testGetWatchFileEventsSearchByTitleInEnglish(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?title.en=meeting', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertGreaterThan(0, $responseData['totalItems']);

        foreach ($responseData['member'] as $event) {
            $this->assertArrayHasKey('title', $event);
            $this->assertArrayHasKey('en', $event['title']);
            $this->assertStringContainsStringIgnoringCase('meeting', $event['title']['en']);
        }
    }

    public function testGetWatchFileEventsSearchByTitleInFrench(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?title.fr=conseil', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertGreaterThan(0, $responseData['totalItems']);

        foreach ($responseData['member'] as $event) {
            $this->assertArrayHasKey('title', $event);
            $this->assertArrayHasKey('fr', $event['title']);
            $this->assertStringContainsStringIgnoringCase('conseil', $event['title']['fr']);
        }
    }

    public function testGetWatchFileEventsSearchByTitlePartialMatch(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?title.en=launch', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertGreaterThan(0, $responseData['totalItems']);

        foreach ($responseData['member'] as $event) {
            $this->assertArrayHasKey('title', $event);
            $this->assertArrayHasKey('en', $event['title']);
            $this->assertStringContainsStringIgnoringCase('launch', $event['title']['en']);
        }
    }

    public function testGetWatchFileEventsSearchByTitleNoResults(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events?title.en=NonexistentTerm', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertEquals(0, $responseData['totalItems']);
        $this->assertEmpty($responseData['member']);
    }

    /**
     * @return array{owner: User, watchFile: WatchFile}
     */
    private function createTestDataWithEvents(): array
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        /** @var string $watchFileId */
        $watchFileId = $watchFile->getId();

        // Create and index events
        $events = $this->createEvents($watchFileId);
        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
        ];
    }

    /**
     * @return array{owner: User, watchFile: WatchFile, actorIds: array<string>}
     */
    private function createTestDataWithMultipleActors(): array
    {
        $owner = UserFactory::createOne();

        $actors = [
            ActorFactory::createOne([
                'label' => 'Actor 1',
                'primaryDomain' => 'actor1.com',
            ]),
            ActorFactory::createOne([
                'label' => 'Actor 2',
                'primaryDomain' => 'actor2.com',
            ]),
            ActorFactory::createOne([
                'label' => 'Actor 3',
                'primaryDomain' => 'actor3.com',
            ]),
        ];

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        /** @var string $watchFileId */
        $watchFileId = $watchFile->getId();

        $actorData = array_map(fn ($a) => [
            'id' => $a->getId() ?? '',
            'name' => $a->getLabel(),
        ], $actors);

        $actorIds = array_map(fn ($a) => $a->getId() ?? '', $actors);

        // Create events with different actors
        $events = $this->createEventsWithActors($watchFileId, $actorData);
        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
            'actorIds' => $actorIds,
        ];
    }

    /**
     * @return array{owner: User, watchFile: WatchFile}
     */
    private function createTestDataWithDifferentEventTypes(): array
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        /** @var string $watchFileId */
        $watchFileId = $watchFile->getId();

        $events = $this->createEventsWithDifferentEventTypes($watchFileId);
        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
        ];
    }

    /**
     * @return array{owner: User, watchFile: WatchFile, actorIds: array<int, string>}
     */
    private function createTestDataWithDifferentEventTypesAndActors(): array
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        /** @var string $watchFileId */
        $watchFileId = $watchFile->getId();

        $actors = [
            ActorFactory::createOne([
                'label' => 'Test Actor 1',
                'primaryDomain' => 'test1.com',
            ]),
            ActorFactory::createOne([
                'label' => 'Test Actor 2',
                'primaryDomain' => 'test2.com',
            ]),
        ];

        $actorIds = array_map(fn ($a) => $a->getId() ?? '', $actors);
        $actorData = array_map(fn ($a) => [
            'id' => $a->getId() ?? '',
            'name' => $a->getLabel(),
        ], $actors);

        $events = $this->createEventsWithDifferentEventTypesAndActors($watchFileId, $actorData);
        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
            'actorIds' => $actorIds,
        ];
    }

    /**
     * @return array{owner: User, watchFile: WatchFile}
     */
    private function createTestDataWithManyEvents(int $count): array
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        /** @var string $watchFileId */
        $watchFileId = $watchFile->getId();

        // Create many events
        $events = $this->createManyEvents($watchFileId, $count);
        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function createEvents(string $watchFileId): array
    {
        return [
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'fr' => 'Réunion du conseil pour discuter des résultats du T2',
                    'en' => 'Board meeting to discuss Q2 results',
                ],
                'startDate' => '2024-06-15T10:00:00Z',
                'endDate' => '2024-06-15T12:00:00Z',
                'description' => [
                    'en' => 'Board meeting to discuss Q2 results',
                    'fr' => 'Réunion du conseil pour discuter des résultats du T2',
                ],
                'eventType' => EventType::ORGANIZATIONAL_HR->value,
                'actors' => [],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-06-15T09:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'fr' => 'Annonce du lancement de produit',
                    'en' => 'Product launch announcement',
                ],
                'startDate' => '2024-07-20T14:00:00Z',
                'endDate' => null,
                'description' => [
                    'en' => 'Product launch announcement',
                    'fr' => 'Annonce du lancement de produit',
                ],
                'eventType' => EventType::COMMERCIAL_BUSINESS->value,
                'actors' => [],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::PENDING->value,
                'createdAt' => '2024-07-20T13:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'fr' => 'Assemblée générale annuelle',
                    'en' => 'Annual general meeting',
                ],
                'startDate' => '2024-08-10T09:00:00Z',
                'endDate' => '2024-08-10T17:00:00Z',
                'description' => [
                    'en' => 'Annual general meeting',
                    'fr' => 'Assemblée générale annuelle',
                ],
                'eventType' => EventType::ORGANIZATIONAL_HR->value,
                'actors' => [],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-08-10T08:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
        ];
    }

    /**
     * @param array<int, array{id: string, name: string}> $actors
     *
     * @return array<int, array<string, mixed>>
     */
    private function createEventsWithActors(string $watchFileId, array $actors): array
    {
        return [
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'fr' => \sprintf('Changement organisationnel chez %s', $actors[0]['name']),
                    'en' => \sprintf('Organizational change at %s', $actors[0]['name']),
                ],
                'startDate' => '2024-06-15T10:00:00Z',
                'endDate' => '2024-06-15T12:00:00Z',
                'description' => [
                    'en' => 'Event with Actor 1',
                    'fr' => 'Événement avec Acteur 1',
                ],
                'eventType' => EventType::ORGANIZATIONAL_HR->value,
                'actors' => [
                    [
                        'id' => $actors[0]['id'],
                        'name' => $actors[0]['name'],
                        'role' => 'organizer',
                    ],
                ],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-06-15T09:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'fr' => \sprintf('Partenariat commercial avec %s', $actors[1]['name']),
                    'en' => \sprintf('Business partnership with %s', $actors[1]['name']),
                ],
                'startDate' => '2024-07-20T14:00:00Z',
                'endDate' => null,
                'description' => [
                    'en' => 'Event with Actor 2',
                    'fr' => 'Événement avec Acteur 2',
                ],
                'eventType' => EventType::COMMERCIAL_BUSINESS->value,
                'actors' => [
                    [
                        'id' => $actors[1]['id'],
                        'name' => $actors[1]['name'],
                        'role' => 'speaker',
                    ],
                ],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::PENDING->value,
                'createdAt' => '2024-07-20T13:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'fr' => \sprintf('Partenariat entre %s et %s', $actors[0]['name'], $actors[2]['name']),
                    'en' => \sprintf('Partnership between %s and %s', $actors[0]['name'], $actors[2]['name']),
                ],
                'startDate' => '2024-08-10T09:00:00Z',
                'endDate' => '2024-08-10T17:00:00Z',
                'description' => [
                    'en' => 'Event with multiple actors',
                    'fr' => 'Événement avec plusieurs acteurs',
                ],
                'eventType' => EventType::ORGANIZATIONAL_HR->value,
                'actors' => [
                    [
                        'id' => $actors[0]['id'],
                        'name' => $actors[0]['name'],
                        'role' => 'participant',
                    ],
                    [
                        'id' => $actors[2]['id'],
                        'name' => $actors[2]['name'],
                        'role' => 'organizer',
                    ],
                ],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-08-10T08:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function createEventsWithDifferentEventTypes(string $watchFileId): array
    {
        return [
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'en' => 'Commercial business event',
                    'fr' => 'Événement commercial',
                ],
                'startDate' => '2024-06-15T10:00:00Z',
                'endDate' => '2024-06-15T12:00:00Z',
                'description' => [
                    'en' => 'Commercial business event',
                    'fr' => 'Événement commercial',
                ],
                'eventType' => EventType::COMMERCIAL_BUSINESS->value,
                'actors' => [],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-06-15T09:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'en' => 'Financial event',
                    'fr' => 'Événement financier',
                ],
                'startDate' => '2024-07-20T14:00:00Z',
                'endDate' => null,
                'description' => [
                    'en' => 'Financial event',
                    'fr' => 'Événement financier',
                ],
                'eventType' => EventType::FINANCIAL->value,
                'actors' => [],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::PENDING->value,
                'createdAt' => '2024-07-20T13:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'en' => 'Another commercial business event',
                    'fr' => 'Autre événement commercial',
                ],
                'startDate' => '2024-08-10T09:00:00Z',
                'endDate' => '2024-08-10T17:00:00Z',
                'description' => [
                    'en' => 'Another commercial business event',
                    'fr' => 'Autre événement commercial',
                ],
                'eventType' => EventType::COMMERCIAL_BUSINESS->value,
                'actors' => [],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-08-10T08:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'en' => 'Regulatory event',
                    'fr' => 'Événement réglementaire',
                ],
                'startDate' => '2024-09-05T11:00:00Z',
                'endDate' => '2024-09-05T13:00:00Z',
                'description' => [
                    'en' => 'Regulatory event',
                    'fr' => 'Événement réglementaire',
                ],
                'eventType' => EventType::REGULATORY_POLITICAL->value,
                'actors' => [],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-09-05T10:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
        ];
    }

    /**
     * @param array<int, array{id: string, name: string}> $actors
     *
     * @return array<int, array<string, mixed>>
     */
    private function createEventsWithDifferentEventTypesAndActors(string $watchFileId, array $actors): array
    {
        return [
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'en' => 'Commercial business event with Actor 1',
                    'fr' => 'Événement commercial avec Acteur 1',
                ],
                'startDate' => '2024-06-15T10:00:00Z',
                'endDate' => '2024-06-15T12:00:00Z',
                'description' => [
                    'en' => 'Commercial business event with Actor 1',
                    'fr' => 'Événement commercial avec Acteur 1',
                ],
                'eventType' => EventType::COMMERCIAL_BUSINESS->value,
                'actors' => [
                    [
                        'id' => $actors[0]['id'],
                        'name' => $actors[0]['name'],
                        'role' => 'organizer',
                    ],
                ],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-06-15T09:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'en' => 'Financial event with Actor 2',
                    'fr' => 'Événement financier avec Acteur 2',
                ],
                'startDate' => '2024-07-20T14:00:00Z',
                'endDate' => null,
                'description' => [
                    'en' => 'Financial event with Actor 2',
                    'fr' => 'Événement financier avec Acteur 2',
                ],
                'eventType' => EventType::FINANCIAL->value,
                'actors' => [
                    [
                        'id' => $actors[1]['id'],
                        'name' => $actors[1]['name'],
                        'role' => 'speaker',
                    ],
                ],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::PENDING->value,
                'createdAt' => '2024-07-20T13:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'en' => 'Another commercial business event with Actor 1',
                    'fr' => 'Autre événement commercial avec Acteur 1',
                ],
                'startDate' => '2024-08-10T09:00:00Z',
                'endDate' => '2024-08-10T17:00:00Z',
                'description' => [
                    'en' => 'Another commercial business event with Actor 1',
                    'fr' => 'Autre événement commercial avec Acteur 1',
                ],
                'eventType' => EventType::COMMERCIAL_BUSINESS->value,
                'actors' => [
                    [
                        'id' => $actors[0]['id'],
                        'name' => $actors[0]['name'],
                        'role' => 'participant',
                    ],
                ],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => '2024-08-10T08:00:00Z',
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function createManyEvents(string $watchFileId, int $count): array
    {
        $events = [];
        $baseDate = new \DateTimeImmutable('2024-01-01T00:00:00Z');

        for ($i = 0; $i < $count; ++$i) {
            $startDate = $baseDate->modify(\sprintf('+%d days', $i));
            $endDate = $startDate->modify('+2 hours');

            $events[] = [
                'id' => Uuid::v4()->toString(),
                'title' => [
                    'fr' => \sprintf('Événement %d', $i + 1),
                    'en' => \sprintf('Event %d', $i + 1),
                ],
                'startDate' => $startDate->format('Y-m-d\TH:i:s\Z'),
                'endDate' => $endDate->format('Y-m-d\TH:i:s\Z'),
                'description' => [
                    'en' => \sprintf('Event %d', $i + 1),
                    'fr' => \sprintf('Événement %d', $i + 1),
                ],
                'eventType' => EventType::ORGANIZATIONAL_HR->value,
                'actors' => [],
                'documentLinks' => [],
                'extractionStatus' => ExtractionStatus::COMPLETED->value,
                'createdAt' => $startDate->modify('-1 hour')
->format('Y-m-d\TH:i:s\Z'),
                'watchFile' => [
                    'id' => $watchFileId,
                    'name' => 'Test Watch File',
                ],
            ];
        }

        return $events;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function indexEventsToOpenSearch(array $events): void
    {
        /** @var Client $openSearch */
        $openSearch = self::getContainer()->get(Client::class);

        $bulkBody = [];

        foreach ($events as $event) {
            $eventId = $event['id'];

            $bulkBody[] = [
                'index' => [
                    '_index' => WatchFileEvent::INDEX_NAME,
                    '_id' => $eventId,
                ],
            ];

            $bulkBody[] = $event;
        }

        $response = $openSearch->bulk([
            'body' => $bulkBody,
        ]);

        // OpenSearch bulk returns array directly
    }

    /**
     * Refresh OpenSearch index to make events searchable.
     */
    private function refreshWatchFileEventsIndex(): void
    {
        /** @var Client $openSearch */
        $openSearch = self::getContainer()->get(Client::class);

        $openSearch->indices()
->refresh([
    'index' => WatchFileEvent::INDEX_NAME,
]);
    }

    /**
     * Clean up all events from OpenSearch index.
     */
    private function cleanupWatchFileEventsOpenSearch(): void
    {
        /** @var Client $openSearch */
        $openSearch = self::getContainer()->get(Client::class);

        try {
            $openSearch->deleteByQuery([
                'index' => WatchFileEvent::INDEX_NAME,
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),
                    ],
                ],
                'refresh' => true,
            ]);
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }

    /**
     * Test that WatchFileEvent collection returns WatchFileEvent facets, not Document facets.
     * This prevents the bug where DocumentCollectionNormalizer incorrectly matches
     * and returns document facets (sources, domains, statuses) instead of event facets.
     */
    public function testWatchFileEventCollectionReturnsEventFacetsNotDocumentFacets(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Make multiple requests to catch intermittent issues
        for ($i = 0; $i < 5; ++$i) {
            $response = $client->request('GET', \sprintf(
                '/api/watch_files/%s/events?page=%d&itemsPerPage=25',
                $watchFile->getId(),
                $i + 1
            ));

            $this->assertResponseIsSuccessful();
            $responseData = $response->toArray();

            // Verify facets exist
            $this->assertArrayHasKey('facets', $responseData, 'Response should include facets');
            $facets = $responseData['facets'];
            $this->assertIsArray($facets);

            // Verify WatchFileEvent-specific facets are present
            $this->assertArrayHasKey('actors', $facets, 'Event facets should include actors');
            $this->assertArrayHasKey('eventTypes', $facets, 'Event facets should include eventTypes');
            $this->assertArrayHasKey('dateRange', $facets, 'Event facets should include dateRange');

            // Verify facets are arrays/objects
            $this->assertIsArray($facets['actors'], 'Actors facet should be an array');
            $this->assertIsArray($facets['eventTypes'], 'EventTypes facet should be an array');
            $this->assertIsArray($facets['dateRange'], 'DateRange facet should be an array');

            // Verify Document-specific facets are NOT present
            $this->assertArrayNotHasKey(
                'sources',
                $facets,
                'Event facets should NOT include sources (this is a Document facet)'
            );
            $this->assertArrayNotHasKey(
                'domains',
                $facets,
                'Event facets should NOT include domains (this is a Document facet)'
            );
            $this->assertArrayNotHasKey(
                'statuses',
                $facets,
                'Event facets should NOT include statuses (this is a Document facet)'
            );
            $this->assertArrayNotHasKey(
                'validationStatuses',
                $facets,
                'Event facets should NOT include validationStatuses (this is a Document facet)'
            );

            // Verify event facet structure
            if (!empty($facets['actors'])) {
                /** @var array<int, array<string, mixed>> $actors */
                $actors = $facets['actors'];
                $firstActor = $actors[0];
                $this->assertArrayHasKey('id', $firstActor, 'Actor facet should have "id" key');
                $this->assertArrayHasKey('name', $firstActor, 'Actor facet should have "name" key');
                $this->assertArrayHasKey('count', $firstActor, 'Actor facet should have "count" key');
            }

            if (!empty($facets['eventTypes'])) {
                /** @var array<int, array<string, mixed>> $eventTypes */
                $eventTypes = $facets['eventTypes'];
                $firstEventType = $eventTypes[0];
                $this->assertArrayHasKey('type', $firstEventType, 'EventType facet should have "type" key');
                $this->assertArrayHasKey('count', $firstEventType, 'EventType facet should have "count" key');
            }

            // Verify dateRange structure
            /** @var array<string, mixed> $dateRange */
            $dateRange = $facets['dateRange'];
            $this->assertArrayHasKey('maxStartDate', $dateRange, 'DateRange should have maxStartDate');
            $this->assertArrayHasKey('maxEndDate', $dateRange, 'DateRange should have maxEndDate');
        }
    }
}
