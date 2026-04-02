<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\ChangeWatchFileStatusAction;
use App\Application\WatchFile\ChangeWatchFileStatusHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\UsageLimit\QuotaLimit;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileStatusChangedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChangeWatchFileStatusHandlerCollectTaskTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileGateway $watchFileGateway;
    private NullSourceGateway $sourceGateway;
    private NullDocumentGateway $documentGateway;
    private Security&MockObject $security;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private UsageLimitConfigInterface&Stub $usageLimitConfig;
    private NullMessageBus $messageBus;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private LoggerInterface&MockObject $logger;
    private ChangeWatchFileStatusHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->documentGateway = new NullDocumentGateway();
        $this->security = $this->createMock(Security::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->usageLimitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $this->messageBus = new NullMessageBus(fn ($message) => null); // Fake handler to satisfy HandleTrait
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Set default unlimited quotas for all tests
        $this->usageLimitConfig->method('watchFileMaxActivePerUser')
            ->willReturn(QuotaLimit::fromNullableInt(null));
        $this->usageLimitConfig->method('sourceMaxActivePerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(null));

        $this->handler = new ChangeWatchFileStatusHandler(
            $this->security,
            $this->eventDispatcher,
            $this->messageBus,
            $this->usageLimitConfig,
            $this->documentGateway,
            $this->realTimeUpdatePublisher,
            new NullMessageGateway(),
            $this->logger,
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
        $this->handler->setSourceGateway($this->sourceGateway);
    }

    public function testActivateCollectTasksWhenWatchFileBecomesEnabled(): void
    {
        $user = new User('user-id', 'test@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setStatus(WatchFileStatus::DRAFT);
        $watchFile->setReferenceSubject(new TranslatedText('Sujet de test', 'Test subject'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
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

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(WatchFileStatusChangedEvent::class));

        // Using NullMessageBus so no expectations needed

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watchfile-id', status: WatchFileStatus::ENABLED);

        $result = $this->handler->__invoke($action);

        $this->assertEquals(WatchFileStatus::ENABLED, $result->getStatus());
    }

    public function testDeactivateCollectTasksWhenWatchFileBecomesDisabled(): void
    {
        $user = new User('user-id', 'test@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $this->watchFileGateway->save($watchFile);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(WatchFileStatusChangedEvent::class));

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watchfile-id', status: WatchFileStatus::ARCHIVED);

        $result = $this->handler->__invoke($action);

        $this->assertEquals(WatchFileStatus::ARCHIVED, $result->getStatus());
    }

    public function testNoCollectTaskLifecycleWhenStatusUnchanged(): void
    {
        $user = new User('user-id', 'test@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $this->watchFileGateway->save($watchFile);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('WatchFile status unchanged', $this->anything());

        $action = new ChangeWatchFileStatusAction(watchFileId: 'watchfile-id', status: WatchFileStatus::ENABLED);

        $result = $this->handler->__invoke($action);

        $this->assertEquals(WatchFileStatus::ENABLED, $result->getStatus());
    }
}
