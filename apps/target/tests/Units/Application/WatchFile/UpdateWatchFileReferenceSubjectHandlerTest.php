<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\UpdateWatchFileReferenceSubjectAction;
use App\Application\WatchFile\UpdateWatchFileReferenceSubjectHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileReferenceSubjectUpdatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Tests\Units\Infrastructure\AI\NullLlmOutputSanitizer;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class UpdateWatchFileReferenceSubjectHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private WatchFileGatewayInterface $watchFileGateway;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private LoggerInterface&Stub $logger;
    private UpdateWatchFileReferenceSubjectHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new UpdateWatchFileReferenceSubjectHandler(
            $this->eventDispatcher,
            new NullLlmOutputSanitizer(),
            $this->realTimeUpdatePublisher,
            $this->logger,
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testUpdateWatchFileQuery(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandler();

        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: 'watch_file_id',
            referenceSubject: new TranslatedText('new-reference-subject', 'new-reference-subject'),
        );

        // Capture the event for assertions
        $dispatchedEvent = null;
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvent) {
                $dispatchedEvent = $event;

                return $event;
            });

        $updatedWatchFile = ($this->handler)($action);

        // Assert the dispatched event
        $this->assertInstanceOf(WatchFileReferenceSubjectUpdatedEvent::class, $dispatchedEvent);
        $this->assertNull($dispatchedEvent->oldReferenceSubject);
        $this->assertEquals(
            new TranslatedText('new-reference-subject', 'new-reference-subject'),
            $dispatchedEvent->newReferenceSubject,
        );
        $this->assertIsArray($dispatchedEvent->context);
        $this->assertArrayHasKey('messageId', $dispatchedEvent->context);
        $this->assertNull($dispatchedEvent->context['messageContentId']);

        $this->assertEquals('new-reference-subject', $updatedWatchFile->getReferenceSubject()?->en);
        $this->assertEquals('watchfile-name', $updatedWatchFile->getName());
        $this->assertEquals('watchfile-objective', $updatedWatchFile->getUserObjective());
        $this->assertEquals('watch_file_id', $updatedWatchFile->getId());
    }

    public function testWatchFileNotFoundThrowsException(): void
    {
        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: 'invalid-watchfile-id',
            referenceSubject: new TranslatedText('new-reference-subject', 'new-reference-subject'),
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');

        ($this->handler)($action);
    }

    public function testUpdateWatchFileQueryWithoutCreatedByUser(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        // Don't set createdBy user
        $this->watchFileGateway->save($watchFile);

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: 'watch_file_id',
            referenceSubject: new TranslatedText('new-reference-subject', 'new-reference-subject'),
        );

        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandler();

        // Expect the event dispatcher NOT to be called since there's no user
        $eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals('new-reference-subject', $updatedWatchFile->getReferenceSubject()?->en);
    }

    public function testUpdateWatchFileReferenceSubjectWithLlmVersion(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandler();

        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: 'watch_file_id',
            referenceSubject: new TranslatedText('sujet-fr', 'subject-en'),
            referenceSubjectLlm: 'LLM optimized version for document filtering',
        );

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals('subject-en', $updatedWatchFile->getReferenceSubject()?->en);
        $this->assertEquals('sujet-fr', $updatedWatchFile->getReferenceSubject()?->fr);
        $this->assertEquals(
            'LLM optimized version for document filtering',
            $updatedWatchFile->getReferenceSubjectLlm()
        );
    }

    public function testUpdateWatchFileReferenceSubjectWithoutLlmVersion(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandler();

        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: 'watch_file_id',
            referenceSubject: new TranslatedText('sujet-fr', 'subject-en'),
            // No LLM version provided
        );

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals('subject-en', $updatedWatchFile->getReferenceSubject()?->en);
        $this->assertNull($updatedWatchFile->getReferenceSubjectLlm());
    }
}
