<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\AI;

use App\Application\Agent\ChatSessionMessageAgent;
use App\Domain\Actor\ActorType;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Organisation\Organisation;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\AI\N8nChatSession;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AllowMockObjectsWithoutExpectations]
class N8nChatSessionTest extends TestCase
{
    use EntityUtilsTrait;
    private const string WATCHFILE_ID = 'watchfile_id';
    private const string CONVERSATION_ID = 'conv_id';
    private const string MESSAGE_ID = 'message_id';
    private Security&MockObject $security;
    private NormalizerInterface&MockObject $normalizer;
    private NullMessageBus $messageBus;
    private N8nChatSession $chatSession;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->messageBus = new NullMessageBus(fn ($message) => []); // Return empty array for GetCollectorListAction

        $this->chatSession = new N8nChatSession(
            $this->security,
            $this->normalizer,
            new NullLogger(),
            $this->messageBus,
        );
    }

    private function createWatchFile(string $id = self::WATCHFILE_ID): WatchFile
    {
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $id);

        return $watchFile;
    }

    private function createConversation(?WatchFile $watchFile = null, string $id = self::CONVERSATION_ID): Conversation
    {
        $watchFile ??= $this->createWatchFile();
        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, $id);

        return $conversation;
    }

    private function createMessage(string $text = 'Test message', string $messageId = self::MESSAGE_ID): Message
    {
        $message = new Message();
        $this->forcePropertyValue($message, $messageId);
        $message->setTextContent($text);

        return $message;
    }

    /**
     * @param array<string, mixed> $normalizedWatchFile
     */
    private function setupNormalizerWithWatchFile(WatchFile $watchFile, array $normalizedWatchFile): void
    {
        $this->normalizer->expects($this->exactly(2))
            ->method('normalize')
            ->willReturnMap([
                [
                    $watchFile,
                    'json',
                    [
                        'groups' => ['watch_file:llm'],
                    ],
                    $normalizedWatchFile,
                ],
                [
                    [],
                    'json',
                    [
                        'groups' => ['collector:llm'],
                    ],
                    [],
                ],
            ]);
    }

    private function getDispatchedAgent(): ChatSessionMessageAgent
    {
        $this->assertSame(1, $this->messageBus->countDispatched(ChatSessionMessageAgent::class));

        $agent = $this->messageBus->getLastDispatched(ChatSessionMessageAgent::class);
        $this->assertInstanceOf(ChatSessionMessageAgent::class, $agent);

        return $agent;
    }

    public function testSendMessageWithAuthenticatedUser(): void
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = $this->createWatchFile();
        $conversation = $this->createConversation($watchFile);
        $message = $this->createMessage();

        $normalizedWatchFile = [
            'id' => self::WATCHFILE_ID,
            'name' => 'Test Watchfile',
        ];

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->setupNormalizerWithWatchFile($watchFile, $normalizedWatchFile);

        $result = $this->chatSession->sendMessage($conversation, $message);

        $this->assertSame($message, $result);

        $agent = $this->getDispatchedAgent();
        $this->assertSame(self::WATCHFILE_ID, $agent->watchFileId);
        $this->assertSame('user-id', $agent->userId);
        $this->assertSame(
            [
                'watchFile' => $normalizedWatchFile,
                'userMessageContentText' => 'Test message',
                'userMessageId' => self::MESSAGE_ID,
                'conversationId' => self::CONVERSATION_ID,
                'conversationLanguage' => 'en',
                'metadata' => [
                    'collectors_list' => [],
                    'source_types' => SourceType::values(),
                    'actor_types' => ActorType::values(),
                ],
            ],
            $agent->data,
        );
    }

    public function testSendMessageWithUnauthenticatedUser(): void
    {
        $watchFile = $this->createWatchFile();
        $conversation = $this->createConversation($watchFile);
        $message = $this->createMessage();

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->setupNormalizerWithWatchFile($watchFile, [
            'id' => self::WATCHFILE_ID,
            'name' => 'Test Watchfile',
        ]);

        $result = $this->chatSession->sendMessage($conversation, $message);

        $this->assertSame($message, $result);

        $agent = $this->getDispatchedAgent();
        $this->assertSame(self::WATCHFILE_ID, $agent->watchFileId);
        $this->assertNull($agent->userId);
    }

    public function testSendMessageWithNonUserObject(): void
    {
        $nonUserObject = $this->createStub(UserInterface::class);
        $watchFile = $this->createWatchFile();
        $conversation = $this->createConversation($watchFile);
        $message = $this->createMessage();

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($nonUserObject);

        $this->setupNormalizerWithWatchFile($watchFile, [
            'id' => self::WATCHFILE_ID,
            'name' => 'Test Watchfile',
        ]);

        $result = $this->chatSession->sendMessage($conversation, $message);

        $this->assertSame($message, $result);

        $agent = $this->getDispatchedAgent();
        $this->assertSame(self::WATCHFILE_ID, $agent->watchFileId);
        $this->assertNull($agent->userId);
    }
}
