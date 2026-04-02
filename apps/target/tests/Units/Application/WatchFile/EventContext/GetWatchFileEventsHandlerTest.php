<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\EventContext;

use App\Application\WatchFile\EventContext\GetWatchFileEventsAction;
use App\Application\WatchFile\EventContext\GetWatchFileEventsHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileEvent\EventActor;
use App\Domain\WatchFileEvent\EventType;
use App\Domain\WatchFileEvent\ExtractionStatus;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileEventGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(GetWatchFileEventsHandler::class)]
class GetWatchFileEventsHandlerTest extends TestCase
{
    private GetWatchFileEventsHandler $handler;
    private NullWatchFileEventGateway $watchFileEventGateway;
    private NullWatchFileGateway $watchFileGateway;
    private ArrayAdapter $cache;
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->watchFileEventGateway = new NullWatchFileEventGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->cache = new ArrayAdapter();
        $this->logger = new NullLogger();

        $this->handler = new GetWatchFileEventsHandler(
            $this->watchFileEventGateway,
            $this->watchFileGateway,
            $this->cache,
            $this->logger,
        );
    }

    public function testGetWatchFileEventsReturnsContextWithEventsActorsAndReferenceSubject(): void
    {
        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Monitor test events',
            organisation: new Organisation('Test Org', 'test-org-id')
        );
        $watchFile->setReferenceSubject(
            new TranslatedText('Surveillance de l\'industrie nucléaire', 'Nuclear industry monitoring')
        );
        $this->watchFileGateway->save($watchFile);

        $events = [
            new WatchFileEvent(
                id: 'event-1',
                startDate: new \DateTimeImmutable('2025-01-01T10:00:00'),
                description: new TranslatedText(fr: 'Acquisition d\'une entreprise', en: 'Company acquisition'),
                eventType: EventType::COMMERCIAL_BUSINESS,
                watchFile: $watchFile,
                actors: [new EventActor(id: 'actor-1', name: 'TechCorp', role: 'acquirer')],
                documentLinks: [],
                extractionStatus: ExtractionStatus::COMPLETED,
                endDate: null,
                createdAt: new \DateTimeImmutable('2025-01-01T10:00:00'),
                title: new TranslatedText('Partenariat commercial avec TechCorp', 'Business partnership with TechCorp'),
            ),
        ];

        $this->watchFileEventGateway->addEventsForWatchFile($watchFile->getId(), $events);

        $action = new GetWatchFileEventsAction($watchFile->getId(), 15);

        $context = ($this->handler)($action);

        $this->assertArrayHasKey('events', $context);
        $this->assertArrayHasKey('actors', $context);
        $this->assertArrayHasKey('referenceSubject', $context);

        $this->assertCount(1, $context['events']);
        $this->assertEquals('event-1', $context['events'][0]->getId());
        $this->assertEquals(
            new TranslatedText('Surveillance de l\'industrie nucléaire', 'Nuclear industry monitoring'),
            $context['referenceSubject']
        );
    }

    public function testGetWatchFileEventsCachesResults(): void
    {
        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Monitor test events',
            organisation: new Organisation('Test Org', 'test-org-id')
        );
        $this->watchFileGateway->save($watchFile);

        $events = [
            new WatchFileEvent(
                id: 'event-1',
                startDate: new \DateTimeImmutable('2025-01-01T10:00:00'),
                description: new TranslatedText(fr: 'Test event', en: 'Test event'),
                eventType: EventType::FINANCIAL,
                watchFile: $watchFile,
                actors: [],
                documentLinks: [],
                extractionStatus: ExtractionStatus::COMPLETED,
                endDate: null,
                createdAt: new \DateTimeImmutable('2025-01-01T10:00:00'),
                title: new TranslatedText('Événement test', 'Test event'),
            ),
        ];

        $this->watchFileEventGateway->addEventsForWatchFile($watchFile->getId(), $events);

        $action = new GetWatchFileEventsAction($watchFile->getId(), 15);

        $context1 = ($this->handler)($action);

        $this->watchFileEventGateway->clear();

        $context2 = ($this->handler)($action);

        $this->assertEquals($context1, $context2);
        $this->assertCount(1, $context2['events']);
    }

    public function testGetWatchFileEventsReturnsEmptyEventsWhenNoEventsExist(): void
    {
        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Monitor test events',
            organisation: new Organisation('Test Org', 'test-org-id')
        );
        $this->watchFileGateway->save($watchFile);

        $action = new GetWatchFileEventsAction($watchFile->getId(), 15);

        $context = ($this->handler)($action);

        $this->assertArrayHasKey('events', $context);
        $this->assertCount(0, $context['events']);
    }

    public function testInvalidateCacheClearsStoredContext(): void
    {
        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Monitor test events',
            organisation: new Organisation('Test Org', 'test-org-id')
        );
        $this->watchFileGateway->save($watchFile);

        $initialEvents = [
            new WatchFileEvent(
                id: 'event-1',
                startDate: new \DateTimeImmutable('2025-01-01T10:00:00'),
                description: new TranslatedText(fr: 'Initial', en: 'Initial'),
                eventType: EventType::FINANCIAL,
                watchFile: $watchFile,
                actors: [],
                documentLinks: [],
                extractionStatus: ExtractionStatus::COMPLETED,
                endDate: null,
                createdAt: new \DateTimeImmutable('2025-01-01T10:00:00'),
                title: new TranslatedText('Événement test', 'Test event'),
            ),
        ];

        $this->watchFileEventGateway->addEventsForWatchFile($watchFile->getId(), $initialEvents);

        $action = new GetWatchFileEventsAction($watchFile->getId(), 15);

        $context1 = ($this->handler)($action);
        $this->assertCount(1, $context1['events']);

        $this->handler->invalidateCache($watchFile->getId());

        $newEvents = [
            new WatchFileEvent(
                id: 'event-2',
                startDate: new \DateTimeImmutable('2025-01-02T10:00:00'),
                description: new TranslatedText(fr: 'New', en: 'New'),
                eventType: EventType::FINANCIAL,
                watchFile: $watchFile,
                actors: [],
                documentLinks: [],
                extractionStatus: ExtractionStatus::COMPLETED,
                endDate: null,
                createdAt: new \DateTimeImmutable('2025-01-02T10:00:00'),
                title: new TranslatedText('Événement test', 'Test event'),
            ),
        ];
        $this->watchFileEventGateway->clear();
        $this->watchFileEventGateway->addEventsForWatchFile($watchFile->getId(), $newEvents);

        $context2 = ($this->handler)($action);

        $this->assertCount(1, $context2['events']);
        $this->assertEquals('event-2', $context2['events'][0]->getId());
    }

    public function testGetWatchFileEventsRespectsMaxLimit(): void
    {
        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Monitor test events',
            organisation: new Organisation('Test Org', 'test-org-id')
        );
        $this->watchFileGateway->save($watchFile);

        $events = [];
        for ($i = 1; $i <= 1005; ++$i) {
            $events[] = new WatchFileEvent(
                id: "event-{$i}",
                startDate: new \DateTimeImmutable('2025-01-01T10:00:00'),
                description: new TranslatedText(fr: "Événement {$i}", en: "Event {$i}"),
                eventType: EventType::FINANCIAL,
                watchFile: $watchFile,
                actors: [],
                documentLinks: [],
                extractionStatus: ExtractionStatus::COMPLETED,
                endDate: null,
                createdAt: new \DateTimeImmutable('2025-01-01T10:00:00'),
                title: new TranslatedText(\sprintf('Événement %d', $i), \sprintf('Event %d', $i)),
            );
        }

        $this->watchFileEventGateway->addEventsForWatchFile($watchFile->getId(), $events);

        $action = new GetWatchFileEventsAction($watchFile->getId(), 15);

        $context = ($this->handler)($action);

        $this->assertCount(1000, $context['events']);
        $this->assertEquals('event-1', $context['events'][0]->getId());
        $this->assertEquals('event-1000', $context['events'][999]->getId());
    }

    public function testGetWatchFileEventsHandlesCorruptedActors(): void
    {
        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Monitor test events',
            organisation: new Organisation('Test Org', 'test-org-id')
        );
        $this->watchFileGateway->save($watchFile);

        $corruptedEvents = [
            new WatchFileEvent(
                id: 'event-1',
                startDate: new \DateTimeImmutable('2025-01-01T10:00:00'),
                description: new TranslatedText(fr: 'Test event', en: 'Test event'),
                eventType: EventType::FINANCIAL,
                watchFile: $watchFile,
                actors: [new EventActor(id: 'actor-1', name: 'Test Actor', role: 'participant')],
                documentLinks: [],
                extractionStatus: ExtractionStatus::COMPLETED,
                endDate: null,
                createdAt: new \DateTimeImmutable('2025-01-01T10:00:00'),
                title: new TranslatedText(
                    'Événement financier impliquant Test Actor',
                    'Financial event involving Test Actor'
                ),
            ),
        ];

        $this->watchFileEventGateway->addEventsForWatchFile($watchFile->getId(), $corruptedEvents);

        $action = new GetWatchFileEventsAction($watchFile->getId(), 15);

        $context = ($this->handler)($action);

        $this->assertArrayHasKey('events', $context);
        $this->assertArrayHasKey('actors', $context);
        $this->assertArrayHasKey('referenceSubject', $context);

        $this->assertCount(1, $context['events']);
        $this->assertEquals('event-1', $context['events'][0]->getId());
    }
}
