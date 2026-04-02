<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile\SearchQuery;

use App\Application\WatchFile\SearchQuery\AddSearchQueryAction;
use App\Application\WatchFile\StrategicQuestion\AddStrategicQuestionAction;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\StrategicQuestion;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration tests for AddSearchQueryHandler via MessageBus.
 *
 * Note: AddSearchQueryAction implements SyncActionInterface,
 * so messages are processed synchronously (not queued).
 *
 * These tests verify the complete flow from N8N workflow:
 * 1. Dispatch action to MessageBus
 * 2. Message is processed synchronously by handler
 * 3. Database state is correct after processing
 */
class AddSearchQueryHandlerTest extends AbstractApiTestCase
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

    private function createStrategicQuestion(): StrategicQuestion
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $action = new AddStrategicQuestionAction(
            watchFileId: $watchFile->getId(),
            questionFR: 'Question stratégique test',
            questionEN: 'Strategic question test',
            contextFR: 'Contexte test',
            contextEN: 'Test context',
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 1,
            expectedOutputType: 'summary'
        );

        $this->messageBus->dispatch($action);

        // Fetch the created strategic question from database
        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $this->assertCount(1, $questions);

        return $questions[0];
    }

    public function testAddSearchQueryCreatesNewQuery(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $action = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'FR',
            language: 'fr',
            query: 'analyse concurrentielle marché français',
            queryType: 'web_search',
            rationale: 'Search for competitive analysis in French market'
        );

        $this->messageBus->dispatch($action);

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(1, $searchQueries);
        $searchQuery = $searchQueries[0];
        $this->assertEquals('analyse concurrentielle marché français', $searchQuery->getSearchTerm());
        $this->assertEquals('FR', $searchQuery->getCountry());
        $this->assertEquals('fr', $searchQuery->getLanguage());
        $this->assertEquals('web_search', $searchQuery->getQueryType());
        $this->assertEquals('Search for competitive analysis in French market', $searchQuery->getRationale());
    }

    public function testAddSearchQuerySkipsDuplicateQuery(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $action1 = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'US',
            language: 'en',
            query: 'market competition analysis',
            queryType: 'web_search',
            rationale: 'Initial rationale'
        );

        $action2 = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'US',
            language: 'en',
            query: 'market competition analysis', // Same query
            queryType: 'news_search', // Different type - should still be deduplicated
            rationale: 'Different rationale'
        );

        $this->messageBus->dispatch($action1);
        $this->messageBus->dispatch($action2);

        // Should have only one query (duplicate skipped based on term+country+language+strategicQuestion)
        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(1, $searchQueries);
        // First query attributes should be preserved
        $searchQuery = $searchQueries[0];
        $this->assertEquals('web_search', $searchQuery->getQueryType());
        $this->assertEquals('Initial rationale', $searchQuery->getRationale());
    }

    public function testAddSearchQueryAllowsSameQueryDifferentCountry(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $actionFR = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'FR',
            language: 'fr',
            query: 'analyse de marché',
            queryType: 'web_search',
            rationale: 'French market analysis'
        );

        $actionUS = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'US',
            language: 'fr',
            query: 'analyse de marché', // Same query
            queryType: 'web_search',
            rationale: 'US market analysis with French query'
        );

        $this->messageBus->dispatch($actionFR);
        $this->messageBus->dispatch($actionUS);

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(2, $searchQueries);
        $countries = array_map(fn ($q) => $q->getCountry(), $searchQueries);
        $this->assertContains('FR', $countries);
        $this->assertContains('US', $countries);
    }

    public function testAddSearchQueryAllowsSameQueryDifferentLanguage(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $actionFR = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'FR',
            language: 'fr',
            query: 'market analysis',
            queryType: 'web_search',
            rationale: 'French language query'
        );

        $actionEN = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'FR',
            language: 'en', // Different language
            query: 'market analysis', // Same query
            queryType: 'web_search',
            rationale: 'English language query'
        );

        $this->messageBus->dispatch($actionFR);
        $this->messageBus->dispatch($actionEN);

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(2, $searchQueries);
        $languages = array_map(fn ($q) => $q->getLanguage(), $searchQueries);
        $this->assertContains('fr', $languages);
        $this->assertContains('en', $languages);
    }

    public function testAddMultipleSearchQueriesInBatch(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $actions = [
            new AddSearchQueryAction(
                strategicQuestionId: $strategicQuestionId,
                country: 'FR',
                language: 'fr',
                query: 'concurrents principaux',
                queryType: 'web_search',
                rationale: 'Find main competitors'
            ),
            new AddSearchQueryAction(
                strategicQuestionId: $strategicQuestionId,
                country: 'US',
                language: 'en',
                query: 'main competitors',
                queryType: 'web_search',
                rationale: 'Find main competitors in English'
            ),
            new AddSearchQueryAction(
                strategicQuestionId: $strategicQuestionId,
                country: 'DE',
                language: 'de',
                query: 'hauptkonkurrenten',
                queryType: 'news_search',
                rationale: 'Find main competitors in German news'
            ),
        ];

        foreach ($actions as $action) {
            $this->messageBus->dispatch($action);
        }

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(3, $searchQueries);

        $countries = array_map(fn ($q) => $q->getCountry(), $searchQueries);
        $this->assertContains('FR', $countries);
        $this->assertContains('US', $countries);
        $this->assertContains('DE', $countries);
    }

    public function testAddSearchQueryWithInvalidCountryCodeThrowsException(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $action = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'FRANCE', // Invalid - should be 2 chars
            language: 'fr',
            query: 'test query',
            queryType: 'web_search',
            rationale: 'Test rationale'
        );

        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('Invalid country or language code');

        $this->messageBus->dispatch($action);
    }

    public function testAddSearchQueryWithInvalidLanguageCodeThrowsException(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $action = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'FR',
            language: 'french', // Invalid - should be 2 chars
            query: 'test query',
            queryType: 'web_search',
            rationale: 'Test rationale'
        );

        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('Invalid country or language code');

        $this->messageBus->dispatch($action);
    }

    public function testAddSearchQueryWithNonExistentStrategicQuestionThrowsException(): void
    {
        $action = new AddSearchQueryAction(
            strategicQuestionId: '00000000-0000-0000-0000-000000000000',
            country: 'FR',
            language: 'fr',
            query: 'test query',
            queryType: 'web_search',
            rationale: 'Test rationale'
        );

        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('StrategicQuestion not found');

        $this->messageBus->dispatch($action);
    }

    public function testAddSearchQueryWithEmptyStrategicQuestionIdThrowsException(): void
    {
        $action = new AddSearchQueryAction(
            strategicQuestionId: '',
            country: 'FR',
            language: 'fr',
            query: 'test query',
            queryType: 'web_search',
            rationale: 'Test rationale'
        );

        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('Invalid strategic question ID');

        $this->messageBus->dispatch($action);
    }

    public function testAddSearchQueryWithUnicodeContent(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $action = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'JP',
            language: 'ja',
            query: '競争分析 日本市場 🎯',
            queryType: 'web_search',
            rationale: 'Japanese market analysis with emoji: 📊'
        );

        $this->messageBus->dispatch($action);

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(1, $searchQueries);
        $searchQuery = $searchQueries[0];
        $this->assertStringContainsString('競争分析', $searchQuery->getSearchTerm());
        $this->assertStringContainsString('🎯', $searchQuery->getSearchTerm());
        $this->assertStringContainsString('📊', $searchQuery->getRationale());
    }

    public function testAddSearchQueryWithLongContent(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $longQuery = 'competitive landscape analysis ' . str_repeat('keyword ', 30);
        $longRationale = str_repeat('This is a detailed rationale paragraph explaining the search strategy. ', 20);

        $action = new AddSearchQueryAction(
            strategicQuestionId: $strategicQuestionId,
            country: 'US',
            language: 'en',
            query: $longQuery,
            queryType: 'comprehensive_search',
            rationale: $longRationale
        );

        $this->messageBus->dispatch($action);

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(1, $searchQueries);
        $searchQuery = $searchQueries[0];
        $this->assertEquals($longQuery, $searchQuery->getSearchTerm());
        $this->assertEquals($longRationale, $searchQuery->getRationale());
    }

    public function testAddSearchQueryDifferentQueryTypes(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $strategicQuestionId = $strategicQuestion->getId();
        $this->assertNotNull($strategicQuestionId);

        $queryTypes = ['web_search', 'news_search', 'academic_search', 'patent_search', 'company_search'];

        foreach ($queryTypes as $index => $queryType) {
            $action = new AddSearchQueryAction(
                strategicQuestionId: $strategicQuestionId,
                country: 'US',
                language: 'en',
                query: \sprintf('test query for %s type %d', $queryType, $index),
                queryType: $queryType,
                rationale: \sprintf('Testing %s query type', $queryType)
            );
            $this->messageBus->dispatch($action);
        }

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(\count($queryTypes), $searchQueries);

        $savedQueryTypes = array_map(fn ($q) => $q->getQueryType(), $searchQueries);
        foreach ($queryTypes as $expectedType) {
            $this->assertContains($expectedType, $savedQueryTypes);
        }
    }
}
