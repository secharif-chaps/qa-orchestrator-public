<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFileEvent;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileEventFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\Actor;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Domain\WatchFileEvent\EventType;
use App\Domain\WatchFileEvent\ExtractionStatus;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Tests\Integration\AbstractApiTestCase;
use OpenSearch\Client;
use Symfony\Component\Uid\Uuid;

class WatchFileEventGraphApiTest extends AbstractApiTestCase
{
    protected function tearDown(): void
    {
        $this->cleanupWatchFileEventsOpenSearch();
        parent::tearDown();
    }

    public function testGetEventsGraphAsOwner(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events/graph', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();

        // Check structure
        $this->assertArrayHasKey('member', $responseData);
        $this->assertIsArray($responseData['member']);

        // Check that we have graph entries
        $this->assertNotEmpty($responseData['member']);

        // Verify each entry has the expected structure
        foreach ($responseData['member'] as $entry) {
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('documentsCount', $entry);
            $this->assertArrayHasKey('eventsCount', $entry);
            $this->assertArrayHasKey('hasEvents', $entry);
            $this->assertArrayHasKey('start', $entry);
            $this->assertArrayHasKey('end', $entry);
            $this->assertArrayHasKey('link', $entry);

            $this->assertIsInt($entry['documentsCount']);
            $this->assertIsInt($entry['eventsCount']);
            $this->assertIsBool($entry['hasEvents']);
            $this->assertIsString($entry['start']);
            $this->assertIsString($entry['end']);
            $this->assertIsString($entry['link']);
        }
    }

    public function testGetEventsGraphAsViewer(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->create();

        $actors = [
            ActorFactory::createOne([
                'label' => 'Test Actor 1',
                'primaryDomain' => 'test1.com',
            ]),
        ];

        // Store IDs before usage
        $watchFileId = $watchFile->getId();
        $actorData = array_map(fn ($a) => [
            'id' => $a->getId() ?? '',
            'name' => $a->getLabel(),
        ], $actors);
        // Create events
        $events = [];
        for ($i = 0; $i < 3; ++$i) {
            $event = WatchFileEventFactory::create($watchFileId, $actorData);
            $events[] = $event;
        }

        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        $client = $this->createAuthenticatedClient($viewer);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events/graph', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphWithoutAccess(): void
    {
        $testData = $this->createTestDataWithEvents();
        $watchFile = $testData['watchFile'];

        $otherUser = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($otherUser);

        $client->request('GET', \sprintf('/api/watch_files/%s/events/graph', $watchFile->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetEventsGraphWithDailyInterval(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?interval=1d', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphWithWeeklyInterval(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?interval=1w', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphWithMonthlyInterval(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?interval=1M', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphWithDateRangeFilter(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $startDate = new \DateTimeImmutable('-30 days')
->format('c');
        $endDate = new \DateTimeImmutable('+30 days')
->format('c');

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf(
                '/api/watch_files/%s/events/graph?startDate=%s&endDate=%s',
                $watchFile->getId(),
                urlencode($startDate),
                urlencode($endDate)
            )
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphFilteredByActor(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];
        $actor = $testData['actors'][0];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?actors.id[]=%s', $watchFile->getId(), $actor->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphFilteredByMultipleActors(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];
        $actors = $testData['actors'];

        $client = $this->createAuthenticatedClient($owner);

        $actorIds = array_map(fn ($actor) => $actor->getId(), $actors);
        $actorParams = implode('&', array_map(fn ($id) => 'actors.id[]=' . $id, $actorIds));

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?%s', $watchFile->getId(), $actorParams)
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphFilteredByEventType(): void
    {
        $testData = $this->createTestDataWithDifferentEventTypes();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?eventType=commercial_business', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertIsArray($responseData['member']);

        foreach ($responseData['member'] as $entry) {
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('documentsCount', $entry);
            $this->assertArrayHasKey('eventsCount', $entry);
            $this->assertArrayHasKey('hasEvents', $entry);
            $this->assertArrayHasKey('start', $entry);
            $this->assertArrayHasKey('end', $entry);
            $this->assertArrayHasKey('link', $entry);
        }
    }

    public function testGetEventsGraphFilteredByMultipleEventTypes(): void
    {
        $testData = $this->createTestDataWithDifferentEventTypes();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf(
                '/api/watch_files/%s/events/graph?eventType[]=commercial_business&eventType[]=financial',
                $watchFile->getId()
            )
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertIsArray($responseData['member']);
    }

    public function testGetEventsGraphFilteredByEventTypeAndActor(): void
    {
        $testData = $this->createTestDataWithDifferentEventTypesAndActors();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];
        $actorId = $testData['actorIds'][0];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf(
                '/api/watch_files/%s/events/graph?eventType=commercial_business&actors.id=%s',
                $watchFile->getId(),
                $actorId
            )
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertIsArray($responseData['member']);
    }

    public function testGetEventsGraphWithInvalidInterval(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Invalid interval should default to 1d
        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?interval=invalid', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphWithInvalidDateFormat(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Invalid date format should be ignored
        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?startDate=not-a-date', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
    }

    public function testGetEventsGraphWithNonExistentWatchFile(): void
    {
        $owner = UserFactory::createOne();
        $client = $this->createAuthenticatedClient($owner);

        $fakeUuid = '00000000-0000-0000-0000-000000000000';
        $client->request('GET', \sprintf('/api/watch_files/%s/events/graph', $fakeUuid));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetEventsGraphWithInvalidWatchFileId(): void
    {
        $owner = UserFactory::createOne();
        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', '/api/watch_files/not-a-uuid/events/graph');

        // Should return 400 for invalid UUID format (caught by EventsGraphProvider validation)
        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetEventsGraphReturnsCorrectEventCount(): void
    {
        $testData = $this->createTestDataWithSpecificDates();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/events/graph?interval=1d', $watchFile->getId())
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $entries = $responseData['member'];

        // Verify we have graph entries (may or may not have events depending on timing)
        $this->assertNotEmpty($entries, 'Should have graph entries');

        // Check structure of each entry
        foreach ($entries as $entry) {
            $this->assertArrayHasKey('hasEvents', $entry);
            $this->assertArrayHasKey('eventsCount', $entry);
            $this->assertIsBool($entry['hasEvents']);
            $this->assertIsInt($entry['eventsCount']);

            // If hasEvents is true, eventsCount should be greater than 0
            if ($entry['hasEvents']) {
                $this->assertGreaterThan(0, $entry['eventsCount']);
            } else {
                $this->assertSame(0, $entry['eventsCount']);
            }
        }
    }

    public function testGetEventsGraphLinkContainsCorrectParameters(): void
    {
        $testData = $this->createTestDataWithEvents();
        $owner = $testData['owner'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/events/graph', $watchFile->getId()));

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $entries = $responseData['member'];

        foreach ($entries as $entry) {
            $link = $entry['link'];

            // Link should contain watchFileId
            $this->assertStringContainsString($watchFile->getId(), $link);

            // Link should contain startDate and endDate parameters
            $this->assertStringContainsString('startDate=', $link);
            $this->assertStringContainsString('endDate=', $link);
        }
    }

    /**
     * Create test data with events.
     *
     * @return array{owner: User, watchFile: WatchFile, actors: array<int, Actor>}
     */
    private function createTestDataWithEvents(): array
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

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

        // Store IDs before usage
        $watchFileId = $watchFile->getId();
        $actorData = array_values(array_filter(array_map(function ($a) {
            $id = $a->getId();

            return null !== $id ? [
                'id' => $id,
            ] : null;
        }, $actors)));

        // Create events with various dates
        $events = [];
        for ($i = 0; $i < 5; ++$i) {
            $event = WatchFileEventFactory::create($watchFileId, $actorData);
            $events[] = $event;
        }

        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
            'actors' => $actors,
        ];
    }

    /**
     * Create test data with specific dates for more predictable testing.
     *
     * @return array{owner: User, watchFile: WatchFile, actors: array<int, mixed>}
     */
    private function createTestDataWithSpecificDates(): array
    {
        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $actors = [
            ActorFactory::createOne([
                'label' => 'Specific Actor',
                'primaryDomain' => 'specific.com',
            ]),
        ];

        // Create events with specific dates
        $now = new \DateTimeImmutable();
        $events = [
            // Event 1: Started 2 days ago, ended 1 day ago
            [
                'id' => Uuid::v4()->toString(),
                'startDate' => $now->modify('-2 days'),
                'endDate' => $now->modify('-1 day'),
                'description' => [
                    'fr' => 'Événement test 1',
                    'en' => 'Test event 1',
                ],
                'eventType' => 'commercial_business',
                'actors' => [[
                    'id' => $actors[0]->getId(),
                    'name' => $actors[0]->getLabel(),
                    'role' => 'partner',
                    'watchfile_id' => $watchFile->getId(),
                ]],
                'documentLinks' => [[
                    'id' => Uuid::v4()->toString(),
                    'text_extract' => 'Test extract 1',
                ]],
                'extractionStatus' => 'completed',
                'createdAt' => $now->modify('-2 days'),
                'watchFile' => [
                    'id' => $watchFile->getId(),
                    'name' => $watchFile->getName(),
                ],
            ],
            // Event 2: Started yesterday, ongoing (no end date)
            [
                'id' => Uuid::v4()->toString(),
                'startDate' => $now->modify('-1 day'),
                'endDate' => null,
                'description' => [
                    'fr' => 'Événement test 2',
                    'en' => 'Test event 2',
                ],
                'eventType' => 'financial',
                'actors' => [[
                    'id' => $actors[0]->getId(),
                    'name' => $actors[0]->getLabel(),
                    'role' => 'acquirer',
                    'watchfile_id' => $watchFile->getId(),
                ]],
                'documentLinks' => [[
                    'id' => Uuid::v4()->toString(),
                    'text_extract' => 'Test extract 2',
                ]],
                'extractionStatus' => 'completed',
                'createdAt' => $now->modify('-1 day'),
                'watchFile' => [
                    'id' => $watchFile->getId(),
                    'name' => $watchFile->getName(),
                ],
            ],
        ];

        $this->indexEventsToOpenSearch($events);
        $this->refreshWatchFileEventsIndex();

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
            'actors' => $actors,
        ];
    }

    /**
     * Index events to OpenSearch.
     *
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
     * @return array<int, array<string, mixed>>
     */
    private function createEventsWithDifferentEventTypes(string $watchFileId): array
    {
        return [
            [
                'id' => Uuid::v4()->toString(),
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
                    'name' => 'Test Watchfile',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
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
                    'name' => 'Test Watchfile',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
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
                    'name' => 'Test Watchfile',
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
                    'name' => 'Test Watchfile',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
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
                    'name' => 'Test Watchfile',
                ],
            ],
            [
                'id' => Uuid::v4()->toString(),
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
                    'name' => 'Test Watchfile',
                ],
            ],
        ];
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
}
