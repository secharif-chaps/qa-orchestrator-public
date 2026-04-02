<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Source;

use App\Application\WatchFile\Source\ChangeSourceStatusAction;
use App\Application\WatchFile\Source\ChangeSourceStatusHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\AccessDeniedException;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class ChangeSourceStatusHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileGateway $watchFileGateway;
    private NullSourceGateway $sourceGateway;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private ChangeSourceStatusHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new ChangeSourceStatusHandler(new NullMessageBus(), $this->eventDispatcher);
        $this->handler->setWatchFileGateway($this->watchFileGateway);
        $this->handler->setSourceGateway($this->sourceGateway);
    }

    public function testChangeSourceStatusToActive(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            null,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source_id');
        $source->deactivate(); // Start with inactive status
        $this->sourceGateway->save($source);

        $action = new ChangeSourceStatusAction(
            watchFileId: 'test-watch-file-id',
            sourceId: 'source_id',
            status: SourceStatus::ACTIVE,
            user: $user
        );

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($source, $watchFile, $user) {
                return $event instanceof SourceStatusChangedEvent
                    && $event->source === $source
                    && $event->watchFile === $watchFile
                    && $event->user === $user
                    && SourceStatus::ACTIVE === $event->status;
            }));

        $updatedSource = ($this->handler)($action);

        $this->assertTrue($updatedSource->isActive());
        $this->assertEquals('Test Source', $updatedSource->getName());
        $this->assertEquals('source_id', $updatedSource->getId());
    }

    public function testChangeSourceStatusToInactive(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            null,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source_id');
        $source->activate(); // Start with active status
        $this->sourceGateway->save($source);

        $action = new ChangeSourceStatusAction(
            watchFileId: 'test-watch-file-id',
            sourceId: 'source_id',
            status: SourceStatus::INACTIVE,
            user: $user
        );

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($source, $watchFile, $user) {
                return $event instanceof SourceStatusChangedEvent
                    && $event->source === $source
                    && $event->watchFile === $watchFile
                    && $event->user === $user
                    && SourceStatus::INACTIVE === $event->status;
            }));

        $updatedSource = ($this->handler)($action);

        $this->assertFalse($updatedSource->isActive());
        $this->assertEquals('Test Source', $updatedSource->getName());
        $this->assertEquals('source_id', $updatedSource->getId());
    }

    public function testChangeSourceStatusWithoutUserDoesNotDispatchEvent(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            null,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source_id');
        $source->deactivate();
        $this->sourceGateway->save($source);

        $action = new ChangeSourceStatusAction(
            watchFileId: 'test-watch-file-id',
            sourceId: 'source_id',
            status: SourceStatus::ACTIVE,
            user: null
        );

        $eventDispatcherMock->expects($this->never())
            ->method('dispatch');

        $updatedSource = ($this->handler)($action);

        $this->assertTrue($updatedSource->isActive());
    }

    public function testWatchFileNotFoundThrowsException(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);

        $action = new ChangeSourceStatusAction('watchfile-id', 'source-id', SourceStatus::ACTIVE);

        ($this->handler)($action);
    }

    public function testSourceNotFoundThrowsException(): void
    {
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $action = new ChangeSourceStatusAction('watchfile-id', 'invalid-source-id', SourceStatus::ACTIVE);

        $this->handler->__invoke($action);
    }

    public function testSourceDoesNotBelongToWatchFileThrowsException(): void
    {
        $watchFile = new WatchFile('test-watch-file-id', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $otherWatchFile = new WatchFile('other-watchfile', 'other-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($otherWatchFile, 'other_watch_file_id');

        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            null,
            $otherWatchFile,
        );
        $this->forcePropertyValue($source, 'source_id');
        $this->sourceGateway->save($source);

        $this->expectException(AccessDeniedException::class);

        $action = new ChangeSourceStatusAction('test-watch-file-id', 'source_id', SourceStatus::ACTIVE);

        ($this->handler)($action);
    }

    public function testChangeSourceStatusWithNullWatchFileId(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            null,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source_id');
        $source->deactivate(); // Start with inactive status
        $this->sourceGateway->save($source);

        $action = new ChangeSourceStatusAction(
            watchFileId: null,
            sourceId: 'source_id',
            status: SourceStatus::ACTIVE,
            user: $user
        );

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch');

        $updatedSource = ($this->handler)($action);

        $this->assertTrue($updatedSource->isActive());
        $this->assertEquals('Test Source', $updatedSource->getName());
        $this->assertEquals('source_id', $updatedSource->getId());
    }

    public function testThrowsExceptionWhenWatchFileIsActive(): void
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            null,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source_id');
        $source->deactivate();
        $this->sourceGateway->save($source);

        $action = new ChangeSourceStatusAction(
            watchFileId: 'test-watch-file-id',
            sourceId: 'source_id',
            status: SourceStatus::ACTIVE,
            user: $user
        );

        $this->expectException(\App\Domain\WatchFile\WatchFileActiveException::class);
        $this->expectExceptionMessage(
            'Cannot change source status on watch file test-watch-file-id because it is in active status. Please set the watch file to draft mode first.'
        );

        ($this->handler)($action);
    }
}
