<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Chat\Content\TextContent;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Source\Source;
use App\Domain\WatchFile\WatchFileActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RecoverMissingMessageLinksCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;
    private Application $application;
    private CommandTester $commandTester;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $command = $this->application->find('app:recover-message-links');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithNoEntitiesWithoutLinks(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Processing WatchFileActors', $output);
        $this->assertStringContainsString('Processing Sources', $output);
    }

    public function testExecuteRecoverActorViaSystemMessage(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before actor creation
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor Test Actor',
            $baseTime->modify('-30 seconds')
        );

        // Create WatchFileActor without addedByMessage
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // System message after actor creation (confirming the add)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Acteur ajouté : Test Actor',
            $baseTime->modify('+10 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Changes have been persisted', $output);

        // Verify the link was recovered
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testExecuteRecoverActorViaTimestampFallback(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Fallback Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before actor creation (within 5 min window)
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Please add this actor',
            $baseTime->modify('-2 minutes')
        );

        // Create WatchFileActor without addedByMessage (no system message confirmation)
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify the link was recovered via fallback
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testExecuteRecoverSourceViaSystemMessage(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne();

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before source creation
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add source Test Source',
            $baseTime->modify('-30 seconds')
        );

        // Create Source without addedByMessage
        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'name' => 'Test Source',
        ]);
        $sourceId = $source->getId();
        $this->setEntityCreatedAt(Source::class, $sourceId, $baseTime);

        // System message after source creation (confirming the add)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Source ajoutée : Test Source',
            $baseTime->modify('+10 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--sources-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify the link was recovered
        $this->entityManager->clear();
        $recoveredSource = $this->entityManager->find(Source::class, $sourceId);
        $this->assertNotNull($recoveredSource);
        $this->assertNotNull($recoveredSource->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredSource->getAddedByMessage()->getId());
    }

    public function testExecuteRecoverSourceViaTimestampFallback(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne();

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before source creation (within 5 min window)
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Please add this source',
            $baseTime->modify('-3 minutes')
        );

        // Create Source without addedByMessage (no system message confirmation)
        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'name' => 'Fallback Source',
        ]);
        $sourceId = $source->getId();
        $this->setEntityCreatedAt(Source::class, $sourceId, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--sources-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify the link was recovered via fallback
        $this->entityManager->clear();
        $recoveredSource = $this->entityManager->find(Source::class, $sourceId);
        $this->assertNotNull($recoveredSource);
        $this->assertNotNull($recoveredSource->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredSource->getAddedByMessage()->getId());
    }

    public function testExecuteDryRunDoesNotPersistChanges(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'DryRun Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before actor creation
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor DryRun Actor',
            $baseTime->modify('-30 seconds')
        );

        // Create WatchFileActor without addedByMessage
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // System message after actor creation
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Acteur ajouté : DryRun Actor',
            $baseTime->modify('+10 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--dry-run' => true,
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('DRY RUN MODE', $output);
        $this->assertStringNotContainsString('Changes have been persisted', $output);

        // Verify the link was NOT persisted
        $this->entityManager->clear();
        $unchangedActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($unchangedActor);
        $this->assertNull($unchangedActor->getAddedByMessage());
    }

    public function testExecuteActorsOnlyOption(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $actor = ActorFactory::createOne();

        // Create entities without links
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Processing WatchFileActors', $output);
        $this->assertStringNotContainsString('Processing Sources', $output);
    }

    public function testExecuteSourcesOnlyOption(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $actor = ActorFactory::createOne();

        // Create entities without links
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--sources-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringNotContainsString('Processing WatchFileActors', $output);
        $this->assertStringContainsString('Processing Sources', $output);
    }

    public function testExecuteWithLimitOption(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();

        // Create 3 actors without links
        for ($i = 0; $i < 3; ++$i) {
            $actor = ActorFactory::createOne([
                'label' => "Actor {$i}",
            ]);
            WatchFileActorFactory::createOne([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ]);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
            '--limit' => '2',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // The summary table should show found = 2 (limited)
        $this->assertStringContainsString('2', $output);
    }

    public function testExecuteNoMatchingMessageFails(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'Orphan Actor',
        ]);

        // Create WatchFileActor without any messages in the conversation
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify the actor still has no link (recovery failed)
        $this->entityManager->clear();
        $unchangedActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($unchangedActor);
        $this->assertNull($unchangedActor->getAddedByMessage());
    }

    public function testExecuteMessageOutsideTimeWindowNotMatched(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Timeout Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message WAY before actor creation (outside 5 min window)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor Timeout Actor',
            $baseTime->modify('-10 minutes')
        );

        // Create WatchFileActor without addedByMessage
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify the actor still has no link (message was outside time window)
        $this->entityManager->clear();
        $unchangedActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($unchangedActor);
        $this->assertNull($unchangedActor->getAddedByMessage());
    }

    public function testExecuteEnglishSystemMessagePattern(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'English Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before actor creation
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor English Actor',
            $baseTime->modify('-30 seconds')
        );

        // Create WatchFileActor without addedByMessage
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // System message with English pattern
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Actor added: English Actor',
            $baseTime->modify('+10 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify the link was recovered
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testExecuteEnglishSourceAddedPattern(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne();

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before source creation
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add source English Source',
            $baseTime->modify('-30 seconds')
        );

        // Create Source without addedByMessage
        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'name' => 'English Source',
        ]);
        $sourceId = $source->getId();
        $this->setEntityCreatedAt(Source::class, $sourceId, $baseTime);

        // System message with English pattern
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Source added: English Source',
            $baseTime->modify('+10 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--sources-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify the link was recovered
        $this->entityManager->clear();
        $recoveredSource = $this->entityManager->find(Source::class, $sourceId);
        $this->assertNotNull($recoveredSource);
        $this->assertNotNull($recoveredSource->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredSource->getAddedByMessage()->getId());
    }

    public function testActorWithExistingLinkIsNotProcessed(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Already Linked Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // Create message that will be the existing link
        $existingMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Original message',
            $baseTime->modify('-1 hour')
        );

        // Create WatchFileActor WITH addedByMessage already set
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'addedByMessage' => $existingMessage,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show 0 found (actors with existing links are excluded)
        $this->assertStringContainsString('0', $output);

        // Verify the link is still the original one
        $this->entityManager->clear();
        $unchangedActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($unchangedActor);
        $this->assertNotNull($unchangedActor->getAddedByMessage());
        $this->assertSame($existingMessage->getId(), $unchangedActor->getAddedByMessage()->getId());
    }

    public function testMultipleUserMessagesSelectsMostRecent(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Multi Message Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // Older user message (should NOT be selected)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'First message - older',
            $baseTime->modify('-4 minutes')
        );

        // More recent user message (should be selected)
        $recentMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Second message - recent',
            $baseTime->modify('-1 minute')
        );

        // Create WatchFileActor without addedByMessage
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify the most recent message was selected
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($recentMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testSystemMessageWithWrongEntityNameDoesNotMatch(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Correct Actor Name',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before actor creation
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor',
            $baseTime->modify('-30 seconds')
        );

        // Create WatchFileActor
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // System message with WRONG actor name (should not match via system message strategy)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Acteur ajouté : Wrong Actor Name',
            $baseTime->modify('+10 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Should still recover via timestamp fallback
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testMultipleWatchFilesAreIsolated(): void
    {
        UserFactory::createOne();

        // WatchFile 1 with its own conversation and actor
        $watchFile1 = WatchFileFactory::createOne();
        $conversation1 = ConversationFactory::createOne([
            'watchFile' => $watchFile1,
        ]);
        $actor1 = ActorFactory::createOne([
            'label' => 'Actor WF1',
        ]);

        // WatchFile 2 with its own conversation and actor
        $watchFile2 = WatchFileFactory::createOne();
        $conversation2 = ConversationFactory::createOne([
            'watchFile' => $watchFile2,
        ]);
        $actor2 = ActorFactory::createOne([
            'label' => 'Actor WF2',
        ]);

        $conversationId1 = $this->assertNotNullAndGetId($conversation1);
        $conversationId2 = $this->assertNotNullAndGetId($conversation2);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // Messages for WatchFile 1
        $userMessage1 = $this->createMessageWithTime(
            $conversationId1,
            MessageRole::User,
            'Add actor for WF1',
            $baseTime->modify('-30 seconds')
        );
        $this->createMessageWithTime(
            $conversationId1,
            MessageRole::System,
            'Acteur ajouté : Actor WF1',
            $baseTime->modify('+10 seconds')
        );

        // Messages for WatchFile 2
        $userMessage2 = $this->createMessageWithTime(
            $conversationId2,
            MessageRole::User,
            'Add actor for WF2',
            $baseTime->modify('-20 seconds')
        );
        $this->createMessageWithTime(
            $conversationId2,
            MessageRole::System,
            'Acteur ajouté : Actor WF2',
            $baseTime->modify('+15 seconds')
        );

        // Create actors
        $watchFileActor1 = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile1,
            'actor' => $actor1,
        ]);
        $watchFileActor2 = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile2,
            'actor' => $actor2,
        ]);

        $watchFileActorId1 = $this->assertNotNullAndGetId($watchFileActor1);
        $watchFileActorId2 = $this->assertNotNullAndGetId($watchFileActor2);

        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId1, $baseTime);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId2, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verify each actor is linked to the correct message from its own WatchFile
        $this->entityManager->clear();

        $recoveredActor1 = $this->entityManager->find(WatchFileActor::class, $watchFileActorId1);
        $this->assertNotNull($recoveredActor1);
        $this->assertNotNull($recoveredActor1->getAddedByMessage());
        $this->assertSame($userMessage1->getId(), $recoveredActor1->getAddedByMessage()->getId());

        $recoveredActor2 = $this->entityManager->find(WatchFileActor::class, $watchFileActorId2);
        $this->assertNotNull($recoveredActor2);
        $this->assertNotNull($recoveredActor2->getAddedByMessage());
        $this->assertSame($userMessage2->getId(), $recoveredActor2->getAddedByMessage()->getId());
    }

    public function testMessageAtExactTimeWindowBoundaryBefore(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Boundary Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message exactly at 5 minutes before (boundary - should be included)
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Message at boundary',
            $baseTime->modify('-300 seconds') // Exactly 5 minutes
        );

        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Message at exact boundary should be recovered
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testMessageJustOutsideTimeWindowBoundary(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Outside Boundary Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message just outside 5 minutes before (should NOT be included)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Message outside boundary',
            $baseTime->modify('-301 seconds') // 5 minutes + 1 second
        );

        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Message just outside boundary should NOT be recovered
        $this->entityManager->clear();
        $unchangedActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($unchangedActor);
        $this->assertNull($unchangedActor->getAddedByMessage());
    }

    public function testSystemMessageOutsideAfterWindowDoesNotMatch(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Late System Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User message before actor creation
        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor',
            $baseTime->modify('-30 seconds')
        );

        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // System message way after (outside 1 minute window - should not be found via system message)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Acteur ajouté : Late System Actor',
            $baseTime->modify('+2 minutes')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Should still recover via timestamp fallback
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testProcessBothActorsAndSourcesInSameRun(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Dual Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // User messages
        $userMessageActor = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor',
            $baseTime->modify('-30 seconds')
        );
        $userMessageSource = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add source',
            $baseTime->modify('+30 seconds')
        );

        // Create actor
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // Create source
        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'name' => 'Dual Source',
        ]);
        $sourceId = $source->getId();
        $this->setEntityCreatedAt(Source::class, $sourceId, $baseTime->modify('+1 minute'));

        $this->entityManager->flush();
        $this->entityManager->clear();

        // Run without --actors-only or --sources-only (process both)
        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Processing WatchFileActors', $output);
        $this->assertStringContainsString('Processing Sources', $output);

        // Verify both were recovered
        $this->entityManager->clear();

        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessageActor->getId(), $recoveredActor->getAddedByMessage()->getId());

        $recoveredSource = $this->entityManager->find(Source::class, $sourceId);
        $this->assertNotNull($recoveredSource);
        $this->assertNotNull($recoveredSource->getAddedByMessage());
        $this->assertSame($userMessageSource->getId(), $recoveredSource->getAddedByMessage()->getId());
    }

    public function testModelRoleMessageIsNotSelectedAsUserMessage(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Model Role Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        // Model message (AI response) - should NOT be selected
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::Model,
            'I will add the actor for you',
            $baseTime->modify('-30 seconds')
        );

        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Model messages should not be selected, so no recovery
        $this->entityManager->clear();
        $unchangedActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($unchangedActor);
        $this->assertNull($unchangedActor->getAddedByMessage());
    }

    public function testLimitZeroProcessesAllRecords(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();

        // Create 5 actors without links
        for ($i = 0; $i < 5; ++$i) {
            $actor = ActorFactory::createOne([
                'label' => "Actor Limit0 {$i}",
            ]);
            WatchFileActorFactory::createOne([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ]);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        // limit=0 should process all (default behavior)
        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
            '--limit' => '0',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show all 5 found
        $this->assertStringContainsString('5', $output);
    }

    public function testSystemMessageAtExactAfterWindowBoundary(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Boundary After Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor',
            $baseTime->modify('-30 seconds')
        );

        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // System message exactly at 60 seconds after (at boundary - should be included)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Acteur ajouté : Boundary After Actor',
            $baseTime->modify('+60 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Message at exact boundary should be matched via system message
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testSystemMessageJustOutsideAfterWindowBoundary(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Outside After Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor',
            $baseTime->modify('-30 seconds')
        );

        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // System message just outside 60 seconds after (61 seconds - should NOT match via system message)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Acteur ajouté : Outside After Actor',
            $baseTime->modify('+61 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // System message outside window, but should still recover via timestamp fallback
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testSystemMessageBeforeEntityCreationIsIgnored(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Before Creation Actor',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor',
            $baseTime->modify('-30 seconds')
        );

        // System message BEFORE entity creation (invalid scenario - should be ignored)
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Acteur ajouté : Before Creation Actor',
            $baseTime->modify('-5 seconds')
        );

        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // System message before creation is ignored, fallback to timestamp
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    public function testPartialPatternMatchInSystemMessage(): void
    {
        UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $actor = ActorFactory::createOne([
            'label' => 'Partial Match',
        ]);

        $conversationId = $this->assertNotNullAndGetId($conversation);
        $baseTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        $userMessage = $this->createMessageWithTime(
            $conversationId,
            MessageRole::User,
            'Add actor',
            $baseTime->modify('-30 seconds')
        );

        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
        ]);
        $watchFileActorId = $this->assertNotNullAndGetId($watchFileActor);
        $this->setEntityCreatedAt(WatchFileActor::class, $watchFileActorId, $baseTime);

        // System message with extra text around the pattern
        $this->createMessageWithTime(
            $conversationId,
            MessageRole::System,
            'Operation completed! Acteur ajouté : Partial Match. Thank you.',
            $baseTime->modify('+10 seconds')
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $exitCode = $this->commandTester->execute([
            '--actors-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Pattern matching with LIKE should work even with surrounding text
        $this->entityManager->clear();
        $recoveredActor = $this->entityManager->find(WatchFileActor::class, $watchFileActorId);
        $this->assertNotNull($recoveredActor);
        $this->assertNotNull($recoveredActor->getAddedByMessage());
        $this->assertSame($userMessage->getId(), $recoveredActor->getAddedByMessage()->getId());
    }

    private function createMessageWithTime(
        string $conversationId,
        MessageRole $role,
        string $content,
        \DateTimeImmutable $createdAt,
    ): Message {
        $conversation = $this->entityManager->find(Conversation::class, $conversationId);
        $this->assertNotNull($conversation);
        $message = new Message($conversation);
        $message->setRole($role);
        $message->setCreatedAt($createdAt);
        $message->addContent(new TextContent($content));
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     */
    private function setEntityCreatedAt(string $entityClass, string $entityId, \DateTimeImmutable $createdAt): void
    {
        $entity = $this->entityManager->find($entityClass, $entityId);
        $this->assertNotNull($entity);
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('createdAt');
        $property->setValue($entity, $createdAt);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @param WatchFileActor|Conversation|object $proxy
     */
    private function assertNotNullAndGetId(object $proxy): string
    {
        \assert(method_exists($proxy, 'getId'));
        $id = $proxy->getId();
        $this->assertNotNull($id);
        \assert(\is_string($id));

        return $id;
    }
}
