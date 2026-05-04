<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\ChangeWatchFileStatusAction;
use App\Application\WatchFile\ChangeWatchFileStatusHandler;
use App\Application\WatchFile\CheckWatchFileOwnerQuotaAction;
use App\Domain\Chat\Message;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\QuotaLimit;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileStatusChangedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

class ChangeWatchFileStatusHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private NullWatchFileGateway $watchFileGateway;
    private NullSourceGateway $sourceGateway;
    private NullDocumentGateway $documentGateway;
    private NullMessageGateway $messageGateway;
    private ChangeWatchFileStatusHandler $handler;
    private Security $security;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private UsageLimitConfigInterface&Stub $usageLimitConfig;
    private NullMessageBus $messageBus;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private QuotaLimit $documentQuota;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->documentGateway = new NullDocumentGateway();
        $this->messageGateway = new NullMessageGateway();
        $this->security = $this->createStub(Security::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->usageLimitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->messageBus = new NullMessageBus();
        $this->documentQuota = QuotaLimit::fromNullableInt(null);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new ChangeWatchFileStatusHandler(
            $this->security,
            $this->eventDispatcher,
            $this->messageBus,
            $this->usageLimitConfig,
            $this->documentGateway,
            $this->realTimeUpdatePublisher,
            $this->messageGateway,
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
        $this->handler->setSourceGateway($this->sourceGateway);
        $this->usageLimitConfig->method('documentMaxPerWatchFile')
        ->willReturnCallback(fn () => $this->documentQuota);
    }

    public function testChangeWatchFileStatus(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $eventDispatcherMock = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcherMock;
        $this->usageLimitConfig->method('watchFileMaxActivePerUser')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->usageLimitConfig->method('sourceMaxActivePerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->buildHandler();
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject(new TranslatedText('Sujet de test', 'Test subject'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create an active source (required for activation)
        $source = new Source(
            name: 'Active Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinence FR', 'Relevance EN'),
            actor: null,
            watchFile: $watchFile,
        );
        $source->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($source);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $user = new User('id-1');

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(WatchFileStatusChangedEvent::class));

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ENABLED);

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals(WatchFileStatus::ENABLED, $updatedWatchFile->getStatus());
        $this->assertEquals('Test', $updatedWatchFile->getName());
        $this->assertEquals('Objective', $updatedWatchFile->getUserObjective());
        $this->assertEquals('watch_file_id', $updatedWatchFile->getId());
    }

    public function testWatchFileNotFoundThrowsException(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->buildHandler();

        $user = new User('id-1');

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $action = new ChangeWatchFileStatusAction(
            watchFileId: 'invalid-watchfile-id',
            status: WatchFileStatus::ENABLED,
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');
        ($this->handler)($action);
    }

    public function testSystemTriggerWithNoUserFallsBackToWatchFileCreator(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->buildHandler();

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ENABLED);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');
        ($this->handler)($action);
    }

    public function testEventDispatchedWithCorrectParameters(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $eventDispatcherMock = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcherMock;
        $this->usageLimitConfig->method('watchFileMaxActivePerUser')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->usageLimitConfig->method('sourceMaxActivePerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->buildHandler();
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject(new TranslatedText('Sujet de test', 'Test subject'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $watchFile->setStatus(WatchFileStatus::ARCHIVED);

        $this->watchFileGateway->save($watchFile);

        // Create an active source (required for activation)
        $source = new Source(
            name: 'Active Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinence FR', 'Relevance EN'),
            actor: null,
            watchFile: $watchFile,
        );
        $source->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($source);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $user = new User('id-1');

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (WatchFileStatusChangedEvent $event) use ($watchFile, $user) {
                return $event->watchFile === $watchFile
                    && $event->user === $user
                    && WatchFileStatus::ARCHIVED === $event->oldStatus
                    && WatchFileStatus::ENABLED === $event->status;
            }));

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ENABLED);

        ($this->handler)($action);
    }

    public function testDifferentStatusTransitions(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $eventDispatcherMock = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcherMock;
        $this->usageLimitConfig->method('watchFileMaxActivePerUser')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->usageLimitConfig->method('sourceMaxActivePerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->buildHandler();
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $user = new User('id-1');

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch');

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ARCHIVED);

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals(WatchFileStatus::ARCHIVED, $updatedWatchFile->getStatus());
    }

    public function testSystemTriggerFallsBackToCreatorAndChangesStatus(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $eventDispatcherMock = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildHandler();

        $creator = new User('creator-id');
        $this->forcePropertyValue($creator, 'creator-id');

        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'), $creator);
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (WatchFileStatusChangedEvent $event) use ($creator) {
                return $event->user === $creator
                    && WatchFileStatus::ARCHIVED === $event->status;
            }));

        $action = new ChangeWatchFileStatusAction(
            watchFileId: 'watch_file_id',
            status: WatchFileStatus::ARCHIVED,
        );

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals(WatchFileStatus::ARCHIVED, $updatedWatchFile->getStatus());
    }

    public function testNonUserAuthenticationObjectFallsBackToWatchFileCreator(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->buildHandler();

        $nonUserObject = new class implements UserInterface {
            public function getUserIdentifier(): string
            {
                return 'non_user_object';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
                // No-op
            }
        };

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($nonUserObject);

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ENABLED);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');
        ($this->handler)($action);
    }

    public function testResolvesUserFromMessageWhenNotAuthenticated(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandler();

        $user = new User('id-1');
        $this->forcePropertyValue($user, 'id-1');

        $message = new Message();
        $message->setCreatedBy($user);
        $this->messageGateway->save($message);
        $messageId = $message->getId();

        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $eventDispatcher->expects($this->once())
            ->method('dispatch');

        $action = new ChangeWatchFileStatusAction(
            watchFileId: 'watch_file_id',
            status: WatchFileStatus::ARCHIVED,
            messageId: $messageId,
        );

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals(WatchFileStatus::ARCHIVED, $updatedWatchFile->getStatus());
    }

    public function testThrowsExceptionWhenMessageNotFound(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->buildHandler();

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $action = new ChangeWatchFileStatusAction(
            watchFileId: 'watch_file_id',
            status: WatchFileStatus::ENABLED,
            messageId: 'non-existent-message-id',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve user: message "non-existent-message-id" not found');
        ($this->handler)($action);
    }

    public function testThrowsExceptionWhenMessageHasNoAuthor(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->buildHandler();

        $message = new Message();
        $this->messageGateway->save($message);
        $messageId = $message->getId();

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $action = new ChangeWatchFileStatusAction(
            watchFileId: 'watch_file_id',
            status: WatchFileStatus::ENABLED,
            messageId: $messageId,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve user: message "' . $messageId . '" has no author');
        ($this->handler)($action);
    }

    public function testThrowsExceptionWhenWatchFileQuotaExceeded(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->usageLimitConfig->method('watchFileMaxActivePerUser')
            ->willReturn(QuotaLimit::fromNullableInt(1, false));
        $this->usageLimitConfig->method('sourceMaxActivePerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->buildHandler();
        $user = new User('id-1');
        $this->forcePropertyValue($user, 'id-1');

        // Create watch file to be activated
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        // Associate user with this watch file as OWNER
        $watchFileUser1 = new WatchFileUser($watchFile, $user, WatchFileUserRole::OWNER);
        $this->forcePropertyValue($watchFileUser1, 'wfu-1');
        $watchFile->addWatchFileUser($watchFileUser1);

        $this->watchFileGateway->save($watchFile);

        // Create another active watch file owned by the same user to reach the quota
        $activeWatchFile = new WatchFile('Active', 'Another objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($activeWatchFile, 'active_watch_file_id');
        $activeWatchFile->setStatus(WatchFileStatus::ENABLED);

        // Associate user with this watch file as OWNER
        $watchFileUser2 = new WatchFileUser($activeWatchFile, $user, WatchFileUserRole::OWNER);
        $this->forcePropertyValue($watchFileUser2, 'wfu-2');
        $activeWatchFile->addWatchFileUser($watchFileUser2);

        $this->watchFileGateway->save($activeWatchFile);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ENABLED);

        $this->expectException(QuotaExceededException::class);
        ($this->handler)($action);
    }

    public function testThrowsExceptionWhenSourceQuotaExceeded(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->usageLimitConfig->method('watchFileMaxActivePerUser')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->usageLimitConfig->method('sourceMaxActivePerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(2, false));
        $this->buildHandler();
        $user = new User('id-1');
        $this->forcePropertyValue($user, 'id-1');

        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject(new TranslatedText('Sujet de test', 'Test subject'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        // Associate user with this watch file as OWNER
        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::OWNER);
        $this->forcePropertyValue($watchFileUser, 'wfu-1');
        $watchFile->addWatchFileUser($watchFileUser);

        $this->watchFileGateway->save($watchFile);

        // Create 2 active sources for this watch file
        $source1 = new Source(
            name: 'Test Source 1',
            description: new TranslatedText('Description FR 1', 'Description EN 1'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/1',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinence FR', 'Relevance EN'),
            actor: null,
            watchFile: $watchFile,
        );
        $source1->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            name: 'Test Source 2',
            description: new TranslatedText('Description FR 2', 'Description EN 2'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/2',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinence FR', 'Relevance EN'),
            actor: null,
            watchFile: $watchFile,
        );
        $source2->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($source2);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ENABLED);

        $this->expectException(QuotaExceededException::class);
        ($this->handler)($action);
    }

    public function testDispatchesCheckWatchFileOwnerQuotaActionWhenUnarchiving(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->usageLimitConfig->method('watchFileMaxActivePerUser')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->usageLimitConfig->method('sourceMaxActivePerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->buildHandler();
        $ownerId = '550e8400-e29b-41d4-a716-446655440000';
        $owner = new User($ownerId);
        $this->forcePropertyValue($owner, $ownerId);

        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'), $owner);
        $watchFile->setReferenceSubject(new TranslatedText('Sujet de test', 'Test subject'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $watchFile->setStatus(WatchFileStatus::ARCHIVED);

        // Associate owner with this watch file as OWNER
        $watchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $this->forcePropertyValue($watchFileUser, 'wfu-1');
        $watchFile->addWatchFileUser($watchFileUser);

        $this->watchFileGateway->save($watchFile);

        // Create an active source (required for activation)
        $source = new Source(
            name: 'Active Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinence FR', 'Relevance EN'),
            actor: null,
            watchFile: $watchFile,
        );
        $source->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($source);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $user = new User('id-1');

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ENABLED);

        ($this->handler)($action);

        // Verify CheckWatchFileOwnerQuotaAction was dispatched
        $this->assertTrue($this->messageBus->hasDispatched(CheckWatchFileOwnerQuotaAction::class));
        $this->assertEquals(1, $this->messageBus->countDispatched(CheckWatchFileOwnerQuotaAction::class));

        $allDispatched = $this->messageBus->getDispatchedMessages();
        $quotaActions = array_filter(
            $allDispatched,
            fn ($message) => $message instanceof CheckWatchFileOwnerQuotaAction
        );
        $this->assertCount(1, $quotaActions);
        $quotaAction = reset($quotaActions);
        $this->assertInstanceOf(CheckWatchFileOwnerQuotaAction::class, $quotaAction);
        $this->assertEquals(Uuid::fromString($ownerId), $quotaAction->userId);
    }

    public function testThrowsExceptionWhenDocumentQuotaExceededOnEnable(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->usageLimitConfig->method('watchFileMaxActivePerUser')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->usageLimitConfig->method('sourceMaxActivePerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->documentQuota = QuotaLimit::fromNullableInt(10000, false);
        $this->buildHandler();
        $user = new User('id-1');
        $this->forcePropertyValue($user, 'id-1');

        $watchFile = new WatchFile('Doc heavy', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject(new TranslatedText('Sujet de test', 'Test subject'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::OWNER);
        $this->forcePropertyValue($watchFileUser, 'wfu-doc');
        $watchFile->addWatchFileUser($watchFileUser);
        $this->watchFileGateway->save($watchFile);
        $this->documentGateway->setDocumentCount('watch_file_id', 15000);

        // Create an active source (required for activation validation to pass before document quota check)
        $source = new Source(
            name: 'Active Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinence FR', 'Relevance EN'),
            actor: null,
            watchFile: $watchFile,
        );
        $source->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($source);

        $this->messageBus->fakeHandler = fn () => [$watchFile];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watch_file_id', status: WatchFileStatus::ENABLED);

        $this->expectException(QuotaExceededException::class);
        ($this->handler)($action);
    }
}
