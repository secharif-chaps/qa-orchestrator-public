<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile\Workflow;

use App\Application\WatchFile\Workflow\ProcessWatchFileClassificationResultAction;
use App\Application\WatchFile\Workflow\ProcessWatchFileClassificationResultHandler;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\AnalysisResult;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProcessWatchFileClassificationResultHandlerTest extends AbstractApiTestCase
{
    use Factories;
    use ResetDatabase;
    private EntityManagerInterface $entityManager;
    private ProcessWatchFileClassificationResultHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getContainer()
->get(EntityManagerInterface::class);
        $this->handler = $this->getContainer()
->get(ProcessWatchFileClassificationResultHandler::class);
    }

    private function refreshWatchFile(string $watchFileId): WatchFile
    {
        $this->entityManager->clear();
        $watchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($watchFile, 'WatchFile should exist');

        return $watchFile;
    }

    public function testHandlerProcessesClassificationResultWithHighConfidence(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne([
            'name' => 'AI Competition Analysis',
            'createdBy' => $user,
        ]);

        $classification = [
            'primaryType' => 'competitive',
            'primarySubtype' => null,
            'confidenceScore' => 85,
            'justification' => [
                'en' => 'Strong competitive intelligence indicators',
                'fr' => 'Indicateurs forts d\'intelligence concurrentielle',
            ],
            'secondaryTypes' => [
                [
                    'type' => 'technological',
                    'subtype' => null,
                    'score' => 60,
                    'justification' => [
                        'en' => 'Some technology aspects',
                        'fr' => 'Quelques aspects technologiques',
                    ],
                ],
            ],
            'topics' => [
                [
                    'label' => 'AI Competition',
                    'keywords' => ['GPT', 'Gemini', 'Claude'],
                    'relevanceScore' => 90,
                    'searchQueryTemplate' => 'AI competition {company}',
                ],
            ],
            'analysis' => [
                'detectedKeywords' => ['competitor', 'market share', 'pricing strategy'],
                'detectedEntities' => ['OpenAI', 'Google', 'Anthropic'],
                'userObjective' => 'Track AI market competitors',
                'geographicScope' => 'Global',
            ],
            'suggestions' => [
                'sources' => [
                    [
                        'name' => 'TechCrunch',
                        'type' => 'news',
                        'relevance' => 'high',
                        'score' => 90,
                        'url' => 'https://techcrunch.com',
                    ],
                    [
                        'name' => 'VentureBeat',
                        'type' => 'news',
                        'relevance' => 'high',
                        'score' => 85,
                    ],
                    [
                        'name' => 'ArXiv',
                        'type' => 'academic',
                        'relevance' => 'medium',
                        'score' => 75,
                    ],
                ],
                'actors' => [
                    [
                        'name' => 'Sam Altman',
                        'type' => 'executive',
                        'relevance' => 'high',
                        'score' => 95,
                    ],
                    [
                        'name' => 'Demis Hassabis',
                        'type' => 'executive',
                        'relevance' => 'high',
                        'score' => 90,
                    ],
                    [
                        'name' => 'Dario Amodei',
                        'type' => 'executive',
                        'relevance' => 'high',
                        'score' => 88,
                    ],
                ],
                'searchQueries' => ['AI competition analysis', 'LLM market share'],
            ],
            'deepSearchReadiness' => [
                'ready' => true,
                'reason' => [
                    'en' => 'Clear objective with specific entities',
                    'fr' => 'Objectif clair avec entités spécifiques',
                ],
                'suggestedSearchQueries' => [
                    'OpenAI GPT-5 release',
                    'Google Gemini pricing',
                    'Anthropic Claude enterprise',
                ],
            ],
        ];

        $action = new ProcessWatchFileClassificationResultAction(
            watchFileId: $watchFile->getId(),
            conversationId: 'test-conversation-id',
            messageId: 'test-message-content-id',
            classification: $classification
        );

        ($this->handler)($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFile->getId());

        $this->assertEquals(MonitoringType::COMPETITIVE, $updatedWatchFile->getMonitoringType());

        $analysisResults = $updatedWatchFile->getAnalysisResults();
        $this->assertCount(1, $analysisResults);

        $analysisResult = $analysisResults->first();
        $this->assertInstanceOf(AnalysisResult::class, $analysisResult);
        $this->assertEquals('WatchFile Type Classification', $analysisResult->getContent());
        $this->assertEquals(MonitoringType::COMPETITIVE, $analysisResult->getPrimaryClassificationType());
        $this->assertEquals(85, $analysisResult->getPrimaryClassificationConfidence());

        $justification = $analysisResult->getPrimaryClassificationJustification();
        $this->assertNotNull($justification);
        $this->assertEquals('Strong competitive intelligence indicators', $justification->en);

        $this->assertEquals(
            ['competitor', 'market share', 'pricing strategy'],
            $analysisResult->getClassificationKeywords()
        );
        $this->assertEquals(['OpenAI', 'Google', 'Anthropic'], $analysisResult->getClassificationDetectedEntities());

        $this->assertEquals('Track AI market competitors', $analysisResult->getClassificationUserObjective());
        $this->assertEquals('Global', $analysisResult->getGeographicScope());

        $sourceSuggestions = $analysisResult->getSourceSuggestions();
        $this->assertNotNull($sourceSuggestions);
        $this->assertCount(3, $sourceSuggestions);
        $this->assertEquals('TechCrunch', $sourceSuggestions[0]['name']);
        $this->assertEquals('news', $sourceSuggestions[0]['type']);

        $actorSuggestions = $analysisResult->getActorSuggestions();
        $this->assertNotNull($actorSuggestions);
        $this->assertCount(3, $actorSuggestions);
        $this->assertEquals('Sam Altman', $actorSuggestions[0]['name']);

        $topics = $analysisResult->getClassificationTopics();
        $this->assertNotNull($topics);
        $this->assertCount(1, $topics);
        $this->assertEquals('AI Competition', $topics[0]['label']);

        $deepSearchReadiness = $analysisResult->getDeepSearchReadiness();
        $this->assertNotNull($deepSearchReadiness);
        $this->assertTrue($deepSearchReadiness->isReady());
        $this->assertEquals('Clear objective with specific entities', $deepSearchReadiness->getReason()->en);
        $this->assertEquals(
            ['OpenAI GPT-5 release', 'Google Gemini pricing', 'Anthropic Claude enterprise'],
            $deepSearchReadiness->getSuggestedSearchQueries()
        );

        $secondaryTypes = $analysisResult->getSecondaryClassificationTypes();
        $this->assertNotNull($secondaryTypes);
        $this->assertCount(1, $secondaryTypes);
        $this->assertEquals('technological', $secondaryTypes[0]['type']);
        $this->assertEquals(60, $secondaryTypes[0]['confidence']);
    }

    public function testHandlerDoesNotSetMonitoringTypeWhenConfidenceIsLow(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne([
            'name' => 'Uncertain Analysis',
            'createdBy' => $user,
        ]);

        $classification = [
            'primaryType' => 'competitive',
            'primarySubtype' => null,
            'confidenceScore' => 45,
            'justification' => [
                'en' => 'Weak indicators',
                'fr' => 'Indicateurs faibles',
            ],
            'secondaryTypes' => [],
            'topics' => [],
            'analysis' => [
                'detectedKeywords' => ['unclear'],
                'detectedEntities' => [],
                'userObjective' => 'Unclear objective',
                'geographicScope' => null,
            ],
            'suggestions' => [
                'sources' => [],
                'actors' => [],
                'searchQueries' => [],
            ],
            'deepSearchReadiness' => [
                'ready' => false,
                'reason' => [
                    'en' => 'Objective too vague',
                    'fr' => 'Objectif trop vague',
                ],
                'suggestedSearchQueries' => [],
            ],
        ];

        $action = new ProcessWatchFileClassificationResultAction(
            watchFileId: $watchFile->getId(),
            conversationId: 'test-conversation-id',
            messageId: 'test-message-content-id',
            classification: $classification
        );

        ($this->handler)($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFile->getId());

        $this->assertNull($updatedWatchFile->getMonitoringType());

        $analysisResults = $updatedWatchFile->getAnalysisResults();
        $this->assertCount(1, $analysisResults);

        $analysisResult = $analysisResults->first();
        $this->assertInstanceOf(AnalysisResult::class, $analysisResult);
        $this->assertEquals(MonitoringType::COMPETITIVE, $analysisResult->getPrimaryClassificationType());
        $this->assertEquals(45, $analysisResult->getPrimaryClassificationConfidence());
        $this->assertEquals(45, $analysisResult->getConfidenceScore());
    }

    public function testHandlerSetsMonitoringTypeAtExactly60PercentConfidence(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne([
            'name' => 'Threshold Test',
            'createdBy' => $user,
        ]);

        $classification = [
            'primaryType' => 'technological',
            'primarySubtype' => null,
            'confidenceScore' => 60,
            'justification' => [
                'en' => 'Moderate confidence',
                'fr' => 'Confiance modérée',
            ],
            'secondaryTypes' => [],
            'topics' => [],
            'analysis' => [
                'detectedKeywords' => ['technology'],
                'detectedEntities' => ['Tech Corp'],
                'userObjective' => 'Monitor tech trends',
                'geographicScope' => null,
            ],
            'suggestions' => [
                'sources' => [
                    [
                        'name' => 'Tech News',
                        'type' => 'news',
                        'relevance' => 'high',
                        'score' => 80,
                    ],
                ],
                'actors' => [
                    [
                        'name' => 'CTO',
                        'type' => 'executive',
                        'relevance' => 'high',
                        'score' => 75,
                    ],
                ],
                'searchQueries' => [],
            ],
            'deepSearchReadiness' => [
                'ready' => true,
                'reason' => [
                    'en' => 'Acceptable clarity',
                    'fr' => 'Clarté acceptable',
                ],
                'suggestedSearchQueries' => ['latest tech trends'],
            ],
        ];

        $action = new ProcessWatchFileClassificationResultAction(
            watchFileId: $watchFile->getId(),
            conversationId: 'test-conversation-id',
            messageId: 'test-message-content-id',
            classification: $classification
        );

        ($this->handler)($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFile->getId());

        $this->assertEquals(MonitoringType::TECHNOLOGICAL, $updatedWatchFile->getMonitoringType());
    }

    public function testHandlerStoresMetadataCorrectly(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::createOne([
            'name' => 'Metadata Test',
            'createdBy' => $user,
        ]);

        $classification = [
            'primaryType' => 'competitive',
            'primarySubtype' => null,
            'confidenceScore' => 75,
            'justification' => [
                'en' => 'Test',
                'fr' => 'Test',
            ],
            'secondaryTypes' => [],
            'topics' => [],
            'analysis' => [
                'detectedKeywords' => [],
                'detectedEntities' => [],
                'userObjective' => 'Test objective',
                'geographicScope' => null,
            ],
            'suggestions' => [
                'sources' => [],
                'actors' => [],
                'searchQueries' => [],
            ],
            'deepSearchReadiness' => [
                'ready' => true,
                'reason' => [
                    'en' => 'Test',
                    'fr' => 'Test',
                ],
                'suggestedSearchQueries' => [],
            ],
        ];

        $action = new ProcessWatchFileClassificationResultAction(
            watchFileId: $watchFile->getId(),
            conversationId: 'test-conversation-id',
            messageId: 'test-message-content-id',
            classification: $classification
        );

        ($this->handler)($action);

        $updatedWatchFile = $this->refreshWatchFile($watchFile->getId());

        $analysisResult = $updatedWatchFile->getAnalysisResults()
->first();
        $this->assertInstanceOf(AnalysisResult::class, $analysisResult);
        $metadata = $analysisResult->getMetadata();

        $this->assertArrayHasKey('workflow', $metadata);
        $this->assertEquals('classify-watchfile', $metadata['workflow']);
        $this->assertArrayHasKey('timestamp', $metadata);
        $this->assertIsString($metadata['timestamp']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $metadata['timestamp']);
    }
}
