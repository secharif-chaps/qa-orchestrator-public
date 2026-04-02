<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Post;
use App\Application\WatchFile\Chat\CreateConversationAction;
use App\Application\WatchFile\CheckWatchFileOwnerQuotaAction;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileCreatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\WatchFileProcessor;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Dto\Chat\UserMessageDto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class WatchFileProcessorTest extends TestCase
{
    use EntityUtilsTrait;
    private Security&MockObject $security;
    private MessageBusInterface&MockObject $messageBus;
    private NullWatchFileGateway $watchFileGateway;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private WatchFileProcessor $processor;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new WatchFileProcessor(
            $this->messageBus,
            $this->watchFileGateway,
            $this->eventDispatcher,
            $this->security,
            $this->translator,
            $this->createStub(TenantContext::class),
            $this->logger,
        );
    }

    public function testProcessCreatesWatchFileWithConversation(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId, 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, $userId);

        $userMessageDto = new UserMessageDto('Test message content');

        $operation = new Post();
        $uriVariables = [];
        $context = [];

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('watch_file.untitled', [], 'messages')
            ->willReturn('WatchFile without title');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (WatchFileCreatedEvent $event) {
                return true;
            }));

        $this->messageBus->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function ($message) use ($user) {
                if ($message instanceof CheckWatchFileOwnerQuotaAction) {
                    $userId = $user->getId();
                    $this->assertNotNull($userId);
                    $this->assertEquals(Uuid::fromString($userId), $message->userId);

                    return new Envelope($message);
                }

                return new Envelope(new \stdClass());
            });

        $this->logger->expects($this->once())
            ->method('info')
            ->with('WatchFile created with conversation', $this->callback(function (array $context) {
                return isset($context['watch_file_id']) && 'Test message content' === $context['message'];
            }));

        $result = $this->processor->process($userMessageDto, $operation, $uriVariables, $context);

        $this->assertInstanceOf(WatchFile::class, $result);
        $this->assertEquals('WatchFile without title', $result->getName());
        $this->assertEquals('', $result->getUserObjective());
        $this->assertSame($user, $result->getCreatedBy());
    }

    public function testProcessCreatesWatchFileWithoutUser(): void
    {
        $userMessageDto = new UserMessageDto('Test message content');

        $operation = new Post();
        $uriVariables = [];
        $context = [];

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('watch_file.untitled', [], 'messages')
            ->willReturn('WatchFile without title');

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (CreateConversationAction $action) {
                return 'Test message content' === $action->message;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('WatchFile created with conversation', $this->callback(function (array $context) {
                return isset($context['watch_file_id']) && 'Test message content' === $context['message'];
            }));

        $result = $this->processor->process($userMessageDto, $operation, $uriVariables, $context);

        $this->assertInstanceOf(WatchFile::class, $result);
        $this->assertEquals('WatchFile without title', $result->getName());
        $this->assertEquals('', $result->getUserObjective());
        $this->assertNull($result->getCreatedBy());
    }
}
