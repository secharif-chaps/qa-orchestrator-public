<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Mercure;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Infrastructure\Mercure\MercureUpdatePublisher;
use App\Infrastructure\Shared\EntityEnrichmentOrchestrator;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(MercureUpdatePublisher::class)]
class MercureUpdatePublisherTest extends TestCase
{
    use EntityUtilsTrait;
    private HubInterface&MockObject $hub;
    private RealTimeTopicGeneratorInterface&MockObject $topicGenerator;
    private WatchFileUserGatewayInterface&MockObject $watchFileUserGateway;
    private SerializerInterface&MockObject $serializer;
    private EntityEnrichmentOrchestrator&Stub $entityEnricher;
    private LoggerInterface&MockObject $logger;
    private MercureUpdatePublisher $publisher;

    protected function setUp(): void
    {
        $this->hub = $this->createMock(HubInterface::class);
        $this->topicGenerator = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->watchFileUserGateway = $this->createMock(WatchFileUserGatewayInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->entityEnricher = $this->createStub(EntityEnrichmentOrchestrator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Configure entityEnricher to return the entity unchanged by default
        $this->entityEnricher
            ->method('enrich')
            ->willReturnCallback(fn (object $entity) => $entity);

        // No debounce cache for tests - pass null, then logger
        $this->publisher = new MercureUpdatePublisher(
            $this->hub,
            $this->topicGenerator,
            $this->watchFileUserGateway,
            $this->serializer,
            $this->entityEnricher,
            null, // No debounce cache for unit tests
            $this->logger
        );
    }

    public function testImplementsRealTimeUpdatePublisherInterface(): void
    {
        $this->assertInstanceOf(RealTimeUpdatePublisherInterface::class, $this->publisher);
    }

    public function testPublishWatchFileUpdatePublishesToEachAuthorizedUserTopic(): void
    {
        // Arrange
        $owner = new User('owner-id', 'owner@example.com');
        $sharedUser = new User('shared-user-id', 'shared@example.com');

        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        // Add owner and shared user via WatchFileUser relationships
        $ownerWatchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $sharedWatchFileUser = new WatchFileUser($watchFile, $sharedUser, WatchFileUserRole::EDITOR);
        $watchFile->addWatchFileUser($ownerWatchFileUser);
        $watchFile->addWatchFileUser($sharedWatchFileUser);

        // Configure gateway to return authorized users
        $this->watchFileUserGateway
            ->expects($this->once())
            ->method('getUsersWithRealTimeAccess')
            ->with($watchFile)
            ->willReturn([$owner, $sharedUser]);

        $serializedData = '{"id":"watchfile-id","name":"Test Watch File"}';

        // Serialization happens per-user due to per-user enrichment
        $this->serializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturn($serializedData);

        $this->topicGenerator
            ->expects($this->exactly(2))
            ->method('forWatchFile')
            ->willReturnCallback(function (User $user, WatchFile $wf) {
                return '/users/' . $user->getId() . '/watch-files/' . $wf->getId();
            });

        $publishedUpdates = [];
        $this->hub
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act
        $this->publisher->publishWatchFileUpdate($watchFile);

        // Assert
        $this->assertCount(2, $publishedUpdates);

        // Verify updates are for owner and shared user
        $topics = array_map(fn (Update $u) => $u->getTopics()[0], $publishedUpdates);
        $this->assertContains('/users/owner-id/watch-files/watchfile-id', $topics);
        $this->assertContains('/users/shared-user-id/watch-files/watchfile-id', $topics);

        // Verify all updates have the same serialized data
        foreach ($publishedUpdates as $update) {
            $this->assertSame($serializedData, $update->getData());
        }
    }

    public function testPublishWatchFileUpdateUsesCorrectSerializationGroup(): void
    {
        // Arrange
        $owner = new User('owner-id', 'owner@example.com');
        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $ownerWatchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $watchFile->addWatchFileUser($ownerWatchFileUser);

        $this->watchFileUserGateway
            ->method('getUsersWithRealTimeAccess')
            ->willReturn([$owner]);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(
                $watchFile,
                'json',
                $this->callback(function (array $context) {
                    return isset($context['groups'])
                        && $context['groups'] === ['watch_file:read'];
                })
            )
            ->willReturn('{}');

        $this->topicGenerator
            ->method('forWatchFile')
            ->willReturn('/users/owner-id/watch-files/watchfile-id');

        $this->hub
            ->method('publish')
            ->willReturn('message-id');

        // Act
        $this->publisher->publishWatchFileUpdate($watchFile);
    }

    public function testPublishMessageUpdatePublishesToConversationUserTopics(): void
    {
        // Arrange
        $owner = new User('owner-id', 'owner@example.com');
        $sharedUser = new User('shared-user-id', 'shared@example.com');

        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        // Add users via WatchFileUser relationships
        $ownerWatchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $sharedWatchFileUser = new WatchFileUser($watchFile, $sharedUser, WatchFileUserRole::EDITOR);
        $watchFile->addWatchFileUser($ownerWatchFileUser);
        $watchFile->addWatchFileUser($sharedWatchFileUser);

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');

        $message = new Message($conversation);
        $this->forcePropertyValue($message, 'message-id');

        $this->watchFileUserGateway
            ->expects($this->once())
            ->method('getUsersWithRealTimeAccess')
            ->with($watchFile)
            ->willReturn([$owner, $sharedUser]);

        $serializedData = '{"id":"message-id","role":"user"}';

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message, 'json', [
                'groups' => ['message:read'],
            ])
            ->willReturn($serializedData);

        $this->topicGenerator
            ->expects($this->exactly(2))
            ->method('forConversationMessages')
            ->willReturnCallback(function (User $user, Conversation $conv) {
                return '/users/' . $user->getId() . '/conversations/' . $conv->getId() . '/messages';
            });

        $publishedUpdates = [];
        $this->hub
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act
        $this->publisher->publishMessageUpdate($message);

        // Assert
        $this->assertCount(2, $publishedUpdates);
    }

    public function testPublishMessageUpdateUsesCorrectSerializationGroup(): void
    {
        // Arrange
        $owner = new User('owner-id', 'owner@example.com');
        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $ownerWatchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $watchFile->addWatchFileUser($ownerWatchFileUser);

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');

        $message = new Message($conversation);
        $this->forcePropertyValue($message, 'message-id');

        $this->watchFileUserGateway
            ->method('getUsersWithRealTimeAccess')
            ->willReturn([$owner]);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(
                $message,
                'json',
                $this->callback(function (array $context) {
                    return isset($context['groups'])
                        && $context['groups'] === ['message:read'];
                })
            )
            ->willReturn('{}');

        $this->topicGenerator
            ->method('forConversationMessages')
            ->willReturn('/users/owner-id/conversations/conversation-id/messages');

        $this->hub
            ->method('publish')
            ->willReturn('message-id');

        // Act
        $this->publisher->publishMessageUpdate($message);
    }

    public function testAllUpdatesArePublishedWithPrivateTrue(): void
    {
        // Arrange
        $owner = new User('owner-id', 'owner@example.com');
        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $ownerWatchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $watchFile->addWatchFileUser($ownerWatchFileUser);

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');

        $message = new Message($conversation);
        $this->forcePropertyValue($message, 'message-id');

        $this->watchFileUserGateway
            ->method('getUsersWithRealTimeAccess')
            ->willReturn([$owner]);

        $this->serializer
            ->method('serialize')
            ->willReturn('{}');

        $this->topicGenerator
            ->method('forWatchFile')
            ->willReturn('/users/owner-id/watch-files/watchfile-id');

        $this->topicGenerator
            ->method('forConversationMessages')
            ->willReturn('/users/owner-id/conversations/conversation-id/messages');

        $capturedUpdates = [];
        $this->hub
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdates) {
                $capturedUpdates[] = $update;

                return 'message-id';
            });

        // Act - Test WatchFile update
        $this->publisher->publishWatchFileUpdate($watchFile);

        // Act - Test Message update
        $this->publisher->publishMessageUpdate($message);

        // Assert - Both updates should be private
        $this->assertCount(2, $capturedUpdates);
        foreach ($capturedUpdates as $update) {
            $this->assertTrue($update->isPrivate(), 'Update should be private');
        }
    }

    public function testNoWatchFileUsersResultsInNoPublications(): void
    {
        // Arrange
        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        // No WatchFileUsers added

        // Gateway returns empty array (no authorized users)
        $this->watchFileUserGateway
            ->expects($this->once())
            ->method('getUsersWithRealTimeAccess')
            ->with($watchFile)
            ->willReturn([]);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('No authorized users found'));

        $this->serializer
            ->expects($this->never())
            ->method('serialize');

        $this->topicGenerator
            ->expects($this->never())
            ->method('forWatchFile');

        $this->hub
            ->expects($this->never())
            ->method('publish');

        // Act
        $this->publisher->publishWatchFileUpdate($watchFile);
    }

    public function testNullConversationInPublishMessageUpdateReturnsEarly(): void
    {
        // Arrange
        $message = new Message();
        $this->forcePropertyValue($message, 'message-id');
        // Message has no conversation (null)

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('message-id'));

        $this->serializer
            ->expects($this->never())
            ->method('serialize');

        $this->topicGenerator
            ->expects($this->never())
            ->method('forConversationMessages');

        $this->hub
            ->expects($this->never())
            ->method('publish');

        // Act
        $this->publisher->publishMessageUpdate($message);
    }

    public function testOnlyUsersWithCanReceiveRealTimeUpdatesRoleReceiveUpdates(): void
    {
        // Arrange
        $owner = new User('owner-id', 'owner@example.com');
        $editor = new User('editor-id', 'editor@example.com');
        $viewer = new User('viewer-id', 'viewer@example.com');

        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        // Add owner (should receive), editor (should receive), and viewer (should NOT receive)
        $ownerWatchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $editorWatchFileUser = new WatchFileUser($watchFile, $editor, WatchFileUserRole::EDITOR);
        $viewerWatchFileUser = new WatchFileUser($watchFile, $viewer, WatchFileUserRole::VIEWER);
        $watchFile->addWatchFileUser($ownerWatchFileUser);
        $watchFile->addWatchFileUser($editorWatchFileUser);
        $watchFile->addWatchFileUser($viewerWatchFileUser);

        // Gateway returns only owner and editor (users with real-time access), not viewer
        $this->watchFileUserGateway
            ->expects($this->once())
            ->method('getUsersWithRealTimeAccess')
            ->with($watchFile)
            ->willReturn([$owner, $editor]);

        $serializedData = '{"id":"watchfile-id","name":"Test Watch File"}';

        // Serialization happens per-user due to per-user enrichment
        $this->serializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturn($serializedData);

        // Only owner and editor should receive updates (2 calls), not viewer
        $this->topicGenerator
            ->expects($this->exactly(2))
            ->method('forWatchFile')
            ->willReturnCallback(function (User $user, WatchFile $wf) {
                return '/users/' . $user->getId() . '/watch-files/' . $wf->getId();
            });

        $publishedUpdates = [];
        $this->hub
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act
        $this->publisher->publishWatchFileUpdate($watchFile);

        // Assert
        $this->assertCount(2, $publishedUpdates);

        // Verify updates are only for owner and editor, NOT for viewer
        $topics = array_map(fn (Update $u) => $u->getTopics()[0], $publishedUpdates);
        $this->assertContains('/users/owner-id/watch-files/watchfile-id', $topics);
        $this->assertContains('/users/editor-id/watch-files/watchfile-id', $topics);
        $this->assertNotContains('/users/viewer-id/watch-files/watchfile-id', $topics);
    }

    public function testViewerRoleDoesNotReceiveMessageUpdates(): void
    {
        // Arrange
        $owner = new User('owner-id', 'owner@example.com');
        $viewer = new User('viewer-id', 'viewer@example.com');

        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        // Add owner (should receive) and viewer (should NOT receive)
        $ownerWatchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $viewerWatchFileUser = new WatchFileUser($watchFile, $viewer, WatchFileUserRole::VIEWER);
        $watchFile->addWatchFileUser($ownerWatchFileUser);
        $watchFile->addWatchFileUser($viewerWatchFileUser);

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');

        $message = new Message($conversation);
        $this->forcePropertyValue($message, 'message-id');

        // Gateway returns only owner (user with real-time access), not viewer
        $this->watchFileUserGateway
            ->expects($this->once())
            ->method('getUsersWithRealTimeAccess')
            ->with($watchFile)
            ->willReturn([$owner]);

        $serializedData = '{"id":"message-id","role":"user"}';

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message, 'json', [
                'groups' => ['message:read'],
            ])
            ->willReturn($serializedData);

        // Only owner should receive updates (1 call), not viewer
        $this->topicGenerator
            ->expects($this->once())
            ->method('forConversationMessages')
            ->with($owner, $conversation)
            ->willReturn('/users/owner-id/conversations/conversation-id/messages');

        $publishedUpdates = [];
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act
        $this->publisher->publishMessageUpdate($message);

        // Assert
        $this->assertCount(1, $publishedUpdates);
        $this->assertSame(
            '/users/owner-id/conversations/conversation-id/messages',
            $publishedUpdates[0]->getTopics()[0]
        );
    }
}
