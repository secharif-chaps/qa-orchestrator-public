<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile\StrategicQuestion;

use App\Application\WatchFile\StrategicQuestion\AddStrategicQuestionAction;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration tests for AddStrategicQuestionHandler via MessageBus.
 *
 * Note: AddStrategicQuestionAction implements SyncActionInterface,
 * so messages are processed synchronously (not queued).
 *
 * These tests verify the complete flow from N8N workflow:
 * 1. Dispatch action to MessageBus
 * 2. Message is processed synchronously by handler
 * 3. Database state is correct after processing
 */
class AddStrategicQuestionHandlerTest extends AbstractApiTestCase
{
    use Factories;
    use ResetDatabase;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getContainer()
->get(EntityManagerInterface::class);
        $this->messageBus = $this->getContainer()
->get(MessageBusInterface::class);
    }

    private function refreshWatchFile(string $watchFileId): WatchFile
    {
        $this->entityManager->clear();
        $watchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($watchFile, 'WatchFile should exist');

        return $watchFile;
    }

    public function testAddStrategicQuestionCreatesNewQuestion(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddStrategicQuestionAction(
            watchFileId: $watchFileId,
            questionFR: 'Quels sont les nouveaux concurrents sur le marché?',
            questionEN: 'Who are the new competitors in the market?',
            contextFR: 'Identification des acteurs émergents.',
            contextEN: 'Identification of emerging players.',
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 2,
            expectedOutputType: 'summary'
        );

        $this->messageBus->dispatch($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $strategicQuestions = $updatedWatchFile->getStrategicQuestions();

        $this->assertCount(1, $strategicQuestions);
        $question = $strategicQuestions->first();
        $this->assertInstanceOf(StrategicQuestion::class, $question);
        $this->assertEquals('Who are the new competitors in the market?', $question->getQuestion()->en);
        $this->assertEquals('Quels sont les nouveaux concurrents sur le marché?', $question->getQuestion()->fr);
        $this->assertEquals(MonitoringType::COMPETITIVE, $question->getMonitoringDimension());
        $this->assertEquals(2, $question->getPriority());
        $this->assertEquals('summary', $question->getExpectedOutputType());
    }

    public function testAddStrategicQuestionSkipsDuplicateQuestion(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action1 = new AddStrategicQuestionAction(
            watchFileId: $watchFileId,
            questionFR: 'Question identique en français.',
            questionEN: 'Identical question in English.',
            contextFR: 'Contexte original.',
            contextEN: 'Original context.',
            monitoringDimension: MonitoringType::REGULATORY,
            priority: 1,
            expectedOutputType: 'analysis'
        );

        $action2 = new AddStrategicQuestionAction(
            watchFileId: $watchFileId,
            questionFR: 'Différente question FR mais même EN.',
            questionEN: 'Identical question in English.', // Same EN question
            contextFR: 'Contexte différent.',
            contextEN: 'Different context.',
            monitoringDimension: MonitoringType::TECHNOLOGICAL, // Different type
            priority: 3, // Different priority
            expectedOutputType: 'list'
        );

        $this->messageBus->dispatch($action1);
        $this->messageBus->dispatch($action2);

        // Should have only one question (duplicate skipped based on EN question text)
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $strategicQuestions = $updatedWatchFile->getStrategicQuestions();

        $this->assertCount(1, $strategicQuestions);
        $question = $strategicQuestions->first();
        $this->assertInstanceOf(StrategicQuestion::class, $question);
        // First question attributes should be preserved
        $this->assertEquals(MonitoringType::REGULATORY, $question->getMonitoringDimension());
        $this->assertEquals(1, $question->getPriority());
    }

    public function testAddMultipleStrategicQuestionsInBatch(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $actions = [
            new AddStrategicQuestionAction(
                watchFileId: $watchFileId,
                questionFR: 'Question sur la technologie?',
                questionEN: 'Question about technology?',
                contextFR: 'Contexte tech.',
                contextEN: 'Tech context.',
                monitoringDimension: MonitoringType::TECHNOLOGICAL,
                priority: 1,
                expectedOutputType: 'list'
            ),
            new AddStrategicQuestionAction(
                watchFileId: $watchFileId,
                questionFR: 'Question sur la régulation?',
                questionEN: 'Question about regulation?',
                contextFR: 'Contexte réglementaire.',
                contextEN: 'Regulatory context.',
                monitoringDimension: MonitoringType::REGULATORY,
                priority: 2,
                expectedOutputType: 'summary'
            ),
            new AddStrategicQuestionAction(
                watchFileId: $watchFileId,
                questionFR: 'Question sur la concurrence?',
                questionEN: 'Question about competition?',
                contextFR: 'Contexte concurrentiel.',
                contextEN: 'Competition context.',
                monitoringDimension: MonitoringType::COMPETITIVE,
                priority: 3,
                expectedOutputType: 'analysis'
            ),
        ];

        foreach ($actions as $action) {
            $this->messageBus->dispatch($action);
        }

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $strategicQuestions = $updatedWatchFile->getStrategicQuestions();

        $this->assertCount(3, $strategicQuestions);

        $monitoringTypes = [];
        foreach ($strategicQuestions as $question) {
            $monitoringTypes[] = $question->getMonitoringDimension();
        }

        $this->assertContains(MonitoringType::TECHNOLOGICAL, $monitoringTypes);
        $this->assertContains(MonitoringType::REGULATORY, $monitoringTypes);
        $this->assertContains(MonitoringType::COMPETITIVE, $monitoringTypes);
    }

    public function testAddStrategicQuestionWithAllMonitoringTypes(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $monitoringTypes = MonitoringType::cases();

        foreach ($monitoringTypes as $index => $type) {
            $action = new AddStrategicQuestionAction(
                watchFileId: $watchFileId,
                questionFR: \sprintf('Question FR pour %s?', $type->value),
                questionEN: \sprintf('Question EN for %s?', $type->value),
                contextFR: 'Contexte.',
                contextEN: 'Context.',
                monitoringDimension: $type,
                priority: $index + 1,
                expectedOutputType: 'list'
            );
            $this->messageBus->dispatch($action);
        }

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $strategicQuestions = $updatedWatchFile->getStrategicQuestions();

        $this->assertCount(\count($monitoringTypes), $strategicQuestions);
    }

    public function testAddStrategicQuestionWithUnicodeContent(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddStrategicQuestionAction(
            watchFileId: $watchFileId,
            questionFR: 'Émoji: 🎯 Accénts: éàüöñ Symboles: €£¥',
            questionEN: 'Emoji: 🎯 Special chars: "quotes" & ampersand',
            contextFR: 'Contexte avec des caractères spéciaux: ç, œ, æ',
            contextEN: 'Context with special characters: <tag>, "quoted", \'single\'',
            monitoringDimension: MonitoringType::TECHNOLOGICAL,
            priority: 1,
            expectedOutputType: 'analysis'
        );

        $this->messageBus->dispatch($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $strategicQuestions = $updatedWatchFile->getStrategicQuestions();

        $this->assertCount(1, $strategicQuestions);
        $question = $strategicQuestions->first();
        $this->assertInstanceOf(StrategicQuestion::class, $question);
        $this->assertStringContainsString('🎯', $question->getQuestion()->en);
        $this->assertStringContainsString('€£¥', $question->getQuestion()->fr);
    }

    public function testAddStrategicQuestionWithLongContent(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $longQuestionEN = 'What are the key technological innovations and market trends '
            . 'that are expected to significantly impact the competitive landscape '
            . 'in our industry sector over the next 5 to 10 years, considering '
            . 'regulatory changes, emerging players, and shifting consumer preferences?';

        $longContextEN = str_repeat('This is a detailed context paragraph. ', 50);

        $action = new AddStrategicQuestionAction(
            watchFileId: $watchFileId,
            questionFR: 'Question longue en français: ' . str_repeat('texte additionnel ', 20),
            questionEN: $longQuestionEN,
            contextFR: str_repeat('Contexte détaillé. ', 50),
            contextEN: $longContextEN,
            monitoringDimension: MonitoringType::TECHNOLOGICAL,
            priority: 1,
            expectedOutputType: 'comprehensive_report'
        );

        $this->messageBus->dispatch($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $strategicQuestions = $updatedWatchFile->getStrategicQuestions();

        $this->assertCount(1, $strategicQuestions);
        $question = $strategicQuestions->first();
        $this->assertInstanceOf(StrategicQuestion::class, $question);
        $this->assertEquals($longQuestionEN, $question->getQuestion()->en);
    }

    public function testAddStrategicQuestionWithHighPriority(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddStrategicQuestionAction(
            watchFileId: $watchFileId,
            questionFR: 'Question prioritaire?',
            questionEN: 'Priority question?',
            contextFR: 'Contexte prioritaire.',
            contextEN: 'Priority context.',
            monitoringDimension: MonitoringType::REGULATORY,
            priority: 100,
            expectedOutputType: 'urgent_alert'
        );

        $this->messageBus->dispatch($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $question = $updatedWatchFile->getStrategicQuestions()
->first();

        $this->assertInstanceOf(StrategicQuestion::class, $question);
        $this->assertEquals(100, $question->getPriority());
    }

    public function testAddStrategicQuestionWithZeroPriority(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddStrategicQuestionAction(
            watchFileId: $watchFileId,
            questionFR: 'Question non prioritaire?',
            questionEN: 'Non-priority question?',
            contextFR: 'Contexte standard.',
            contextEN: 'Standard context.',
            monitoringDimension: MonitoringType::STRATEGIC,
            priority: 0,
            expectedOutputType: 'report'
        );

        $this->messageBus->dispatch($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $question = $updatedWatchFile->getStrategicQuestions()
->first();

        $this->assertInstanceOf(StrategicQuestion::class, $question);
        $this->assertEquals(0, $question->getPriority());
        $this->assertEquals(MonitoringType::STRATEGIC, $question->getMonitoringDimension());
    }
}
