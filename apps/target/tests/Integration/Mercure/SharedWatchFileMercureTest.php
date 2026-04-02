<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mercure;

use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\Chat\MessageFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Infrastructure\Mercure\MercureTopicGenerator;
use App\Infrastructure\Mercure\MercureUpdatePublisher;
use App\Infrastructure\Shared\EntityEnrichmentOrchestrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration tests for Mercure publication to shared WatchFiles.
 *
 * These tests verify that when a WatchFile is shared between multiple users,
 * each authorized user (owner + editors) receives updates on their own topic,
 * while viewers do NOT receive updates.
 */
#[CoversClass(MercureUpdatePublisher::class)]
class SharedWatchFileMercureTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;
    private HubInterface&MockObject $hub;
    private MercureUpdatePublisher $publisher;
    private RealTimeTopicGeneratorInterface $topicGenerator;
    private WatchFileUserGatewayInterface $watchFileUserGateway;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->hub = $this->createMock(HubInterface::class);
        $this->topicGenerator = new MercureTopicGenerator();

        /** @var SerializerInterface $serializer */
        $serializer = self::getContainer()->get(SerializerInterface::class);

        /** @var WatchFileUserGatewayInterface $watchFileUserGateway */
        $watchFileUserGateway = self::getContainer()->get(WatchFileUserGatewayInterface::class);
        $this->watchFileUserGateway = $watchFileUserGateway;

        /** @var EntityEnrichmentOrchestrator $entityEnricher */
        $entityEnricher = self::getContainer()->get(EntityEnrichmentOrchestrator::class);

        $this->publisher = new MercureUpdatePublisher(
            $this->hub,
            $this->topicGenerator,
            $this->watchFileUserGateway,
            $serializer,
            $entityEnricher,
            null, // No debounce cache for integration tests
            new NullLogger(),
        );
    }

    public function testPublishWatchFileUpdateToOwnerAndEditors(): void
    {
        // Arrange: Create owner, editor, and viewer users
        $owner = UserFactory::createOne();
        $editor = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        // Create WatchFile with owner
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        // Add editor (should receive updates)
        WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $editor,
                'role' => WatchFileUserRole::EDITOR,
            ])
            ->create();

        // Add viewer (should NOT receive updates)
        WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $viewer,
                'role' => WatchFileUserRole::VIEWER,
            ])
            ->create();

        // Capture published updates
        $publishedUpdates = [];
        $this->hub
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act: Publish WatchFile update
        $this->publisher->publishWatchFileUpdate($watchFile);

        // Assert: Verify only owner and editor received updates
        $this->assertCount(2, $publishedUpdates);

        $topics = array_map(fn (Update $u) => $u->getTopics()[0], $publishedUpdates);

        // Owner should receive update
        $ownerTopic = $this->topicGenerator->forWatchFile($owner, $watchFile);
        $this->assertContains($ownerTopic, $topics, 'Owner should receive WatchFile update');

        // Editor should receive update
        $editorTopic = $this->topicGenerator->forWatchFile($editor, $watchFile);
        $this->assertContains($editorTopic, $topics, 'Editor should receive WatchFile update');

        // Viewer should NOT receive update
        $viewerTopic = $this->topicGenerator->forWatchFile($viewer, $watchFile);
        $this->assertNotContains($viewerTopic, $topics, 'Viewer should NOT receive WatchFile update');

        // Verify all updates are private
        foreach ($publishedUpdates as $update) {
            $this->assertTrue($update->isPrivate(), 'All updates should be private');
        }
    }

    public function testPublishMessageUpdateToOwnerAndEditorsOnly(): void
    {
        // Arrange: Create owner, editor, and viewer users
        $owner = UserFactory::createOne();
        $editor = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        // Create WatchFile with owner
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        // Add editor and viewer
        WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $editor,
                'role' => WatchFileUserRole::EDITOR,
            ])
            ->create();

        WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $viewer,
                'role' => WatchFileUserRole::VIEWER,
            ])
            ->create();

        // Create conversation and message
        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $message = MessageFactory::new()
            ->with([
                'conversation' => $conversation,
            ])
            ->create();

        // Capture published updates
        $publishedUpdates = [];
        $this->hub
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act: Publish Message update
        $this->publisher->publishMessageUpdate($message);

        // Assert: Verify only owner and editor received updates
        $this->assertCount(2, $publishedUpdates);

        $topics = array_map(fn (Update $u) => $u->getTopics()[0], $publishedUpdates);

        // Owner should receive update on messages topic
        $ownerTopic = $this->topicGenerator->forConversationMessages($owner, $conversation);
        $this->assertContains($ownerTopic, $topics, 'Owner should receive Message update');

        // Editor should receive update on messages topic
        $editorTopic = $this->topicGenerator->forConversationMessages($editor, $conversation);
        $this->assertContains($editorTopic, $topics, 'Editor should receive Message update');

        // Viewer should NOT receive update
        $viewerTopic = $this->topicGenerator->forConversationMessages($viewer, $conversation);
        $this->assertNotContains($viewerTopic, $topics, 'Viewer should NOT receive Message update');
    }

    public function testPublishWatchFileUpdateWithMultipleEditors(): void
    {
        // Arrange: Create owner and multiple editors
        $owner = UserFactory::createOne();
        $editor1 = UserFactory::createOne();
        $editor2 = UserFactory::createOne();
        $editor3 = UserFactory::createOne();

        // Create WatchFile with owner
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        // Add multiple editors
        foreach ([$editor1, $editor2, $editor3] as $editor) {
            WatchFileUserFactory::new()
                ->with([
                    'watchFile' => $watchFile,
                    'user' => $editor,
                    'role' => WatchFileUserRole::EDITOR,
                ])
                ->create();
        }

        // Capture published updates
        $publishedUpdates = [];
        $this->hub
            ->expects($this->exactly(4)) // 1 owner + 3 editors
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act: Publish WatchFile update
        $this->publisher->publishWatchFileUpdate($watchFile);

        // Assert: Verify all 4 users received updates
        $this->assertCount(4, $publishedUpdates);

        $topics = array_map(fn (Update $u) => $u->getTopics()[0], $publishedUpdates);

        // Verify each user has their own topic
        $this->assertContains($this->topicGenerator->forWatchFile($owner, $watchFile), $topics);
        $this->assertContains($this->topicGenerator->forWatchFile($editor1, $watchFile), $topics);
        $this->assertContains($this->topicGenerator->forWatchFile($editor2, $watchFile), $topics);
        $this->assertContains($this->topicGenerator->forWatchFile($editor3, $watchFile), $topics);
    }

    public function testPublishWatchFileUpdateSerializationUsesCorrectGroup(): void
    {
        // Arrange: Create owner
        $owner = UserFactory::createOne();

        // Create WatchFile with specific data
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Test WatchFile for Serialization',
            ])
            ->create();

        // Capture published updates
        $publishedUpdates = [];
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act: Publish WatchFile update
        $this->publisher->publishWatchFileUpdate($watchFile);

        // Assert: Verify serialized data contains expected fields
        $this->assertCount(1, $publishedUpdates);
        $data = json_decode($publishedUpdates[0]->getData(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertEquals('Test WatchFile for Serialization', $data['name']);
    }

    public function testTopicFormatMatchesSpecification(): void
    {
        // Arrange: Create owner and WatchFile
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $ownerId = $owner->getId();
        $watchFileId = $watchFile->getId();

        // Capture published updates
        $publishedUpdates = [];
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdates) {
                $publishedUpdates[] = $update;

                return 'message-id';
            });

        // Act: Publish WatchFile update
        $this->publisher->publishWatchFileUpdate($watchFile);

        // Assert: Verify topic format matches specification
        $this->assertCount(1, $publishedUpdates);
        $topic = $publishedUpdates[0]->getTopics()[0];

        // Topic format should be: /users/{userId}/watch-files/{watchFileId}
        $expectedTopic = "/users/{$ownerId}/watch-files/{$watchFileId}";
        $this->assertEquals($expectedTopic, $topic);
    }
}
