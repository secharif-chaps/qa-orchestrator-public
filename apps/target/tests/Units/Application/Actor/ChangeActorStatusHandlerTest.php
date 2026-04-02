<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Actor;

use App\Application\Actor\ChangeActorStatusAction;
use App\Application\Actor\ChangeActorStatusHandler;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorStatusChangedEvent;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActiveException;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileActorGatewayInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileActorGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Dto\Actor\ChangeActorStatusOutputDto;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChangeActorStatusHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private ChangeActorStatusHandler $handler;
    private WatchFileActorGatewayInterface $watchFileActorGateway;
    private SourceGatewayInterface $sourceGateway;
    private WatchFileGatewayInterface $watchFileGateway;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private LoggerInterface&Stub $logger;

    protected function setUp(): void
    {
        $this->watchFileActorGateway = new NullWatchFileActorGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new ChangeActorStatusHandler(
            $this->sourceGateway,
            $this->eventDispatcher,
            $this->logger,
            $this->watchFileActorGateway,
            $this->watchFileGateway,
        );
    }

    public function testChangeActorStatusToActive(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');

        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));

        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $watchFileActor->setStatus(ActorStatus::INACTIVE);

        $source1 = $this->createSource('source-1', SourceStatus::AUTO_DISABLED, $actor);
        $source2 = $this->createSource('source-2', SourceStatus::AUTO_DISABLED, $actor);

        $action = new ChangeActorStatusAction(
            watchFileId: 'test-watch-file-id',
            actorId: 'actor-id',
            sourceIds: ['source-1', 'source-2'],
            newStatus: ActorStatus::ACTIVE,
            user: $user,
        );

        $this->watchFileGateway->save($watchFile);
        $this->watchFileActorGateway->save($watchFileActor);
        $this->sourceGateway->save($source1);
        $this->sourceGateway->save($source2);

        $eventDispatcherMock->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ActorStatusChangedEvent || $event instanceof SourceStatusChangedEvent;
            }));

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
        $this->assertSame($actor, $result->actor);
        $this->assertCount(2, $result->sources);
        $this->assertSame(ActorStatus::ACTIVE, $watchFileActor->getStatus());
        $this->assertSame(SourceStatus::ACTIVE, $source1->getStatus());
        $this->assertSame(SourceStatus::ACTIVE, $source2->getStatus());
    }

    public function testChangeActorStatusToInactive(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');

        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));

        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $watchFileActor->setStatus(ActorStatus::ACTIVE);

        $source1 = $this->createSource('source-1', SourceStatus::ACTIVE, $actor);
        $source2 = $this->createSource('source-2', SourceStatus::INACTIVE, $actor); // Manually deactivated
        $source3 = $this->createSource('source-3', SourceStatus::ACTIVE, $actor);

        $action = new ChangeActorStatusAction(
            watchFileId: 'test-watch-file-id',
            actorId: 'actor-id',
            sourceIds: ['source-1', 'source-2', 'source-3'],
            newStatus: ActorStatus::INACTIVE,
            user: $user,
        );

        $this->watchFileGateway->save($watchFile);
        $this->watchFileActorGateway->save($watchFileActor);
        $this->sourceGateway->save($source1);
        $this->sourceGateway->save($source2);
        $this->sourceGateway->save($source3);

        $eventDispatcherMock->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ActorStatusChangedEvent || $event instanceof SourceStatusChangedEvent;
            }));

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
        $this->assertSame($actor, $result->actor);
        $this->assertCount(2, $result->sources); // Only active sources should be updated
        $this->assertSame(ActorStatus::INACTIVE, $watchFileActor->getStatus());
        $this->assertSame(SourceStatus::AUTO_DISABLED, $source1->getStatus());
        $this->assertSame(SourceStatus::INACTIVE, $source2->getStatus()); // Should remain manually deactivated
        $this->assertSame(SourceStatus::AUTO_DISABLED, $source3->getStatus());
    }

    public function testLogsWarningWhenSomeSourcesNotFound(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildHandler();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $watchFileActor->setStatus(ActorStatus::ACTIVE);

        $source1 = $this->createSource('source-1', SourceStatus::ACTIVE, $actor);

        $action = new ChangeActorStatusAction(
            watchFileId: 'test-watch-file-id',
            actorId: 'actor-id',
            sourceIds: ['source-1', 'source-2', 'source-3'], // Only source-1 exists
            newStatus: ActorStatus::ACTIVE,
            user: $user,
        );

        $this->watchFileGateway->save($watchFile);
        $this->watchFileActorGateway->save($watchFileActor);
        $this->sourceGateway->save($source1);

        $loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                'Some given sources do not belong to watch file or do not exist',
                $this->callback(function (array $context) {
                    return 'test-watch-file-id' === $context['watchFileId']
                        && 'actor-id' === $context['actorId']
                        && $context['requested_sources'] === ['source-1', 'source-2', 'source-3']
                        && $context['found_sources'] === ['source-1'];
                })
            );

        // Act & Assert
        $this->expectException(\App\Domain\Source\SourceNotFoundException::class);
        ($this->handler)($action);
    }

    public function testDispatchesCorrectEvents(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $watchFileActor->setStatus(ActorStatus::INACTIVE);

        $source = $this->createSource('source-1', SourceStatus::AUTO_DISABLED, $actor);

        $action = new ChangeActorStatusAction(
            watchFileId: 'test-watch-file-id',
            actorId: 'actor-id',
            sourceIds: ['source-1'],
            newStatus: ActorStatus::ACTIVE,
            user: $user,
        );

        $this->watchFileGateway->save($watchFile);
        $this->watchFileActorGateway->save($watchFileActor);
        $this->sourceGateway->save($source);

        $eventDispatcherMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ActorStatusChangedEvent || $event instanceof SourceStatusChangedEvent;
            }));

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
    }

    public function testFiltersOutManuallyDeactivatedSourcesWhenActorBecomesInactive(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $watchFileActor->setStatus(ActorStatus::ACTIVE);

        $activeSource = $this->createSource('active-source', SourceStatus::ACTIVE, $actor);
        $manuallyDeactivatedSource = $this->createSource('manual-source', SourceStatus::INACTIVE, $actor);
        $autoDisabledSource = $this->createSource('auto-source', SourceStatus::AUTO_DISABLED, $actor);

        $action = new ChangeActorStatusAction(
            watchFileId: 'test-watch-file-id',
            actorId: 'actor-id',
            sourceIds: ['active-source', 'manual-source', 'auto-source'],
            newStatus: ActorStatus::INACTIVE,
            user: $user,
        );

        $this->watchFileGateway->save($watchFile);
        $this->watchFileActorGateway->save($watchFileActor);
        $this->sourceGateway->save($activeSource);
        $this->sourceGateway->save($manuallyDeactivatedSource);
        $this->sourceGateway->save($autoDisabledSource);

        $eventDispatcherMock->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ActorStatusChangedEvent || $event instanceof SourceStatusChangedEvent;
            }));

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
        $this->assertCount(2, $result->sources); // Only active and auto-disabled sources
        $this->assertSame(SourceStatus::AUTO_DISABLED, $activeSource->getStatus());
        $this->assertSame(SourceStatus::INACTIVE, $manuallyDeactivatedSource->getStatus()); // Should remain unchanged
        $this->assertSame(SourceStatus::AUTO_DISABLED, $autoDisabledSource->getStatus());
    }

    public function testUpdatesAllSourcesWhenActorBecomesActive(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $watchFileActor->setStatus(ActorStatus::INACTIVE);

        $inactiveSource = $this->createSource('inactive-source', SourceStatus::INACTIVE, $actor);
        $autoDisabledSource = $this->createSource('auto-source', SourceStatus::AUTO_DISABLED, $actor);

        $action = new ChangeActorStatusAction(
            watchFileId: 'test-watch-file-id',
            actorId: 'actor-id',
            sourceIds: ['inactive-source', 'auto-source'],
            newStatus: ActorStatus::ACTIVE,
            user: $user,
        );

        $this->watchFileGateway->save($watchFile);
        $this->watchFileActorGateway->save($watchFileActor);
        $this->sourceGateway->save($inactiveSource);
        $this->sourceGateway->save($autoDisabledSource);

        $eventDispatcherMock->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ActorStatusChangedEvent || $event instanceof SourceStatusChangedEvent;
            }));

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
        $this->assertCount(2, $result->sources);
        $this->assertSame(SourceStatus::ACTIVE, $inactiveSource->getStatus());
        $this->assertSame(SourceStatus::ACTIVE, $autoDisabledSource->getStatus());
    }

    public function testFiltersSourcesByRequestedSourceIds(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');

        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));

        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $watchFileActor->setStatus(ActorStatus::INACTIVE);

        // Create sources with different IDs
        $source1 = $this->createSource('source-1', SourceStatus::AUTO_DISABLED, $actor);
        $source2 = $this->createSource('source-2', SourceStatus::AUTO_DISABLED, $actor);
        $source3 = $this->createSource('source-3', SourceStatus::AUTO_DISABLED, $actor);
        $source4 = $this->createSource('source-4', SourceStatus::AUTO_DISABLED, $actor);

        // Only request source-1 and source-3, but not source-2 and source-4
        $action = new ChangeActorStatusAction(
            watchFileId: 'test-watch-file-id',
            actorId: 'actor-id',
            sourceIds: ['source-1', 'source-3'],
            newStatus: ActorStatus::ACTIVE,
            user: $user,
        );

        $this->watchFileGateway->save($watchFile);
        $this->watchFileActorGateway->save($watchFileActor);
        $this->sourceGateway->save($source1);
        $this->sourceGateway->save($source2);
        $this->sourceGateway->save($source3);
        $this->sourceGateway->save($source4);

        $eventDispatcherMock->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ActorStatusChangedEvent || $event instanceof SourceStatusChangedEvent;
            }));

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
        $this->assertSame($actor, $result->actor);

        // Should only contain the requested sources (source-1 and source-3)
        $this->assertCount(2, $result->sources);

        $sourceIds = array_map(fn (Source $source) => $source->getId(), $result->sources);
        $this->assertContains('source-1', $sourceIds);
        $this->assertContains('source-3', $sourceIds);
        $this->assertNotContains('source-2', $sourceIds);
        $this->assertNotContains('source-4', $sourceIds);

        // Verify that only the filtered sources were updated
        $this->assertSame(SourceStatus::ACTIVE, $source1->getStatus());
        $this->assertSame(SourceStatus::ACTIVE, $source3->getStatus());
        $this->assertSame(SourceStatus::AUTO_DISABLED, $source2->getStatus()); // Should remain unchanged
        $this->assertSame(SourceStatus::AUTO_DISABLED, $source4->getStatus()); // Should remain unchanged
    }

    private function createSource(string $id, SourceStatus $status, Actor $actor): Source
    {
        $source = new Source(
            name: 'Test Source',
            description: TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            actor: $actor,
        );

        $this->forcePropertyValue($source, $id, 'id');
        $source->setStatus($status);

        return $source;
    }

    public function testThrowsExceptionWhenWatchFileIsActive(): void
    {
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');

        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setStatus(WatchFileStatus::ENABLED);

        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $watchFileActor->setStatus(ActorStatus::INACTIVE);

        $action = new ChangeActorStatusAction(
            watchFileId: 'test-watch-file-id',
            actorId: 'actor-id',
            sourceIds: [],
            newStatus: ActorStatus::ACTIVE,
            user: $user,
        );

        $this->watchFileGateway->save($watchFile);
        $this->watchFileActorGateway->save($watchFileActor);

        $this->expectException(WatchFileActiveException::class);
        $this->expectExceptionMessage(
            'Cannot change actor status on watch file test-watch-file-id because it is in active status. Please set the watch file to draft mode first.'
        );

        ($this->handler)($action);
    }
}
