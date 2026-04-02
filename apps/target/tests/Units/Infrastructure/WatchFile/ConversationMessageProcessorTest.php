<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Post;
use App\Application\WatchFile\Chat\AddMessageAction;
use App\Domain\Chat\Conversation;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\ConversationMessageProcessor;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Dto\Chat\UserMessageDto;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ConversationMessageProcessorTest extends TestCase
{
    use EntityUtilsTrait;
    private MessageBusInterface&Stub $messageBus;
    private NullConversationGateway $conversationGateway;
    private Security $security;
    private ConversationMessageProcessor $processor;

    protected function setUp(): void
    {
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->conversationGateway = new NullConversationGateway();
        $this->security = $this->createStub(Security::class);
        $this->buildProcessor();
    }

    private function buildProcessor(): void
    {
        $this->processor = new ConversationMessageProcessor(
            $this->messageBus,
            $this->createStub(\ApiPlatform\State\ProcessorInterface::class),
            $this->conversationGateway,
            $this->security
        );
    }

    public function testProcessSuccessfullyAddsMessageToConversation(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        // Arrange
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation_id');
        $this->conversationGateway->addConversation($conversation);

        $userMessageDto = new UserMessageDto('Hello world');
        $operation = new Post();
        $uriVariables = [
            'id' => 'conversation_id',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $conversation)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AddMessageAction $action) {
                return 'conversation_id' === $action->conversationId && 'Hello world' === $action->message;
            }))
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($conversation, 'handler')]));

        // Act
        $result = $this->processor->process($userMessageDto, $operation, $uriVariables, $context);

        // Assert
        $this->assertSame($conversation, $result);
    }

    public function testProcessThrowsExceptionWhenConversationIdIsMissing(): void
    {
        // Arrange
        $userMessageDto = new UserMessageDto('Hello world');
        $operation = new Post();
        $uriVariables = [];
        $context = [];

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WatchFile ID is required.');

        $this->processor->process($userMessageDto, $operation, $uriVariables, $context);
    }

    public function testProcessThrowsExceptionWhenConversationIdIsNotString(): void
    {
        // Arrange
        $userMessageDto = new UserMessageDto('Hello world');
        $operation = new Post();
        $uriVariables = [
            'id' => 123,
        ];
        $context = [];

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WatchFile ID is required.');

        $this->processor->process($userMessageDto, $operation, $uriVariables, $context);
    }

    public function testProcessThrowsAccessDeniedExceptionWhenUserNotGranted(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        // Arrange
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation_id');
        $this->conversationGateway->addConversation($conversation);

        $userMessageDto = new UserMessageDto('Hello world');
        $operation = new Post();
        $uriVariables = [
            'id' => 'conversation_id',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $conversation)
            ->willReturn(false);

        // Act & Assert
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You do not have permission to post messages for this conversation.');

        $this->processor->process($userMessageDto, $operation, $uriVariables, $context);
    }

    public function testProcessWithDifferentMessageContent(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        // Arrange
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation_id');
        $this->conversationGateway->addConversation($conversation);

        $userMessageDto = new UserMessageDto('This is a different message');
        $operation = new Post();
        $uriVariables = [
            'id' => 'conversation_id',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $conversation)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AddMessageAction $action) {
                return 'conversation_id' === $action->conversationId && 'This is a different message' === $action->message;
            }))
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($conversation, 'handler')]));

        // Act
        $result = $this->processor->process($userMessageDto, $operation, $uriVariables, $context);

        // Assert
        $this->assertSame($conversation, $result);
    }

    public function testProcessWithComplexMessageContent(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        // Arrange
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation_id');
        $this->conversationGateway->addConversation($conversation);

        $complexMessage = "This is a complex message with multiple lines.\nIt contains special characters: éàçù and numbers: 123.\nIt also has punctuation marks: !@#$%^&*()";
        $userMessageDto = new UserMessageDto($complexMessage);
        $operation = new Post();
        $uriVariables = [
            'id' => 'conversation_id',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $conversation)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AddMessageAction $action) use ($complexMessage) {
                return 'conversation_id' === $action->conversationId && $action->message === $complexMessage;
            }))
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($conversation, 'handler')]));

        // Act
        $result = $this->processor->process($userMessageDto, $operation, $uriVariables, $context);

        // Assert
        $this->assertSame($conversation, $result);
    }

    public function testProcessWithEmptyMessageContent(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        // Arrange
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation_id');
        $this->conversationGateway->addConversation($conversation);

        $userMessageDto = new UserMessageDto('');
        $operation = new Post();
        $uriVariables = [
            'id' => 'conversation_id',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $conversation)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AddMessageAction $action) {
                return 'conversation_id' === $action->conversationId && '' === $action->message;
            }))
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($conversation, 'handler')]));

        // Act
        $result = $this->processor->process($userMessageDto, $operation, $uriVariables, $context);

        // Assert
        $this->assertSame($conversation, $result);
    }
}
