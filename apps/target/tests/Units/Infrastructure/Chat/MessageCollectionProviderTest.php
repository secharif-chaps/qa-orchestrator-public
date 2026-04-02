<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Chat\MessageCollectionProvider;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[AllowMockObjectsWithoutExpectations]
class MessageCollectionProviderTest extends TestCase
{
    private MessageCollectionProvider $provider;

    /**
     * @var ProviderInterface<Message>&MockObject
     */
    private ProviderInterface&MockObject $apiPlatformProvider;
    private NullConversationGateway $conversationGateway;
    private Security&MockObject $security;
    private Operation&Stub $operation;

    protected function setUp(): void
    {
        $this->apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $this->conversationGateway = new NullConversationGateway();
        $this->security = $this->createMock(Security::class);
        $this->operation = $this->createStub(Operation::class);

        $this->provider = new MessageCollectionProvider(
            $this->apiPlatformProvider,
            $this->conversationGateway,
            $this->security
        );
    }

    public function testProvideWithValidConversationAndGrantedAccess(): void
    {
        $conversationId = 'conv_id';
        $uriVariables = [
            'id' => $conversationId,
        ];
        $context = [];
        $expectedMessages = [new Message()];

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $conversation = new Conversation($watchFile);
        $this->conversationGateway->addConversation($conversation);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $conversation)
            ->willReturn(true);

        $this->apiPlatformProvider
            ->expects($this->once())
            ->method('provide')
            ->with($this->operation, $uriVariables, $context)
            ->willReturn($expectedMessages);

        $result = $this->provider->provide($this->operation, $uriVariables, $context);

        $this->assertSame($expectedMessages, $result);
    }

    public function testProvideWithMissingConversationId(): void
    {
        $uriVariables = [];
        $context = [];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Conversation ID is required.');

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithNonStringConversationId(): void
    {
        $uriVariables = [
            'id' => 123,
        ];
        $context = [];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Conversation ID is required.');

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithConversationNotFound(): void
    {
        $conversationId = 'non-existent-id';
        $uriVariables = [
            'id' => $conversationId,
        ];
        $context = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Conversation not found');

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithAccessDenied(): void
    {
        $conversationId = 'conv_id';
        $uriVariables = [
            'id' => $conversationId,
        ];
        $context = [];

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $conversation = new Conversation($watchFile);
        $this->conversationGateway->addConversation($conversation);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $conversation)
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You do not have permission to view messages for this conversation.');

        $this->provider->provide($this->operation, $uriVariables, $context);
    }
}
