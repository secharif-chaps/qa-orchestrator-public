<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\WatchFile;

use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\ActorSuggestion;
use App\Domain\WatchFile\AnalysisResult;
use App\Domain\WatchFile\DeepSearchReadiness;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\SourceSuggestion;
use App\Domain\WatchFile\Topic;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileTypeClassificationItem;
use App\Domain\WatchFile\WatchFileTypeClassificationResult;
use PHPUnit\Framework\TestCase;

class AnalysisResultTest extends TestCase
{
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->watchFile = new WatchFile('Test WatchFile', 'Monitor tech trends', new Organisation(
            'Test Org',
            'test-org-id'
        ));
    }

    public function testConstructorSetsRequiredProperties(): void
    {
        $result = new AnalysisResult($this->watchFile, 'Analysis content', [
            'workflow' => 'test-workflow',
        ]);

        $this->assertEquals('Analysis content', $result->getContent());
        $this->assertSame($this->watchFile, $result->getWatchFile());
        $this->assertEquals([
            'workflow' => 'test-workflow',
        ], $result->getMetadata());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getCreatedAt());
        $this->assertInstanceOf(\Symfony\Component\Uid\Uuid::class, $result->getId());
    }

    public function testAddMetadata(): void
    {
        $result = new AnalysisResult($this->watchFile, 'content', [
            'foo' => 'bar',
        ]);

        $result->addMetadata('baz', 42);

        $this->assertEquals([
            'foo' => 'bar',
            'baz' => 42,
        ], $result->getMetadata());
    }

    public function testSetAndGetBasicAnalysisFields(): void
    {
        $result = new AnalysisResult($this->watchFile, 'content');

        $result->setIdentifiedNeeds(['competitive intelligence', 'market research']);
        $result->setEntities(['Google', 'Microsoft', 'OpenAI']);
        $result->setTemporalScope([
            'from' => '2024-01-01',
            'to' => '2024-12-31',
        ]);
        $result->setMonitoringTypes(['type1', 'type2']);
        $result->setStrategicQuestions(['What is the market trend?']);
        $result->setSuggestedApproach(['daily monitoring', 'weekly reports']);
        $result->setConfidenceScore(85);

        $this->assertEquals(['competitive intelligence', 'market research'], $result->getIdentifiedNeeds());
        $this->assertEquals(['Google', 'Microsoft', 'OpenAI'], $result->getEntities());
        $this->assertEquals([
            'from' => '2024-01-01',
            'to' => '2024-12-31',
        ], $result->getTemporalScope());
        $this->assertEquals(['type1', 'type2'], $result->getMonitoringTypes());
        $this->assertEquals(['What is the market trend?'], $result->getStrategicQuestions());
        $this->assertEquals(['daily monitoring', 'weekly reports'], $result->getSuggestedApproach());
        $this->assertEquals(85, $result->getConfidenceScore());
    }

    public function testSetAndGetClassificationFields(): void
    {
        $result = new AnalysisResult($this->watchFile, 'content');

        $result->setPrimaryClassificationType(MonitoringType::COMPETITIVE);
        $result->setPrimaryClassificationConfidence(85);
        $result->setPrimaryClassificationJustification(
            new TranslatedText('Indicateurs CI forts', 'Strong CI indicators')
        );

        $secondaryTypes = [
            [
                'type' => 'TECHNOLOGY_WATCH',
                'confidence' => 70,
                'justification' => [
                    'en' => 'Some tech aspects',
                    'fr' => 'Quelques aspects tech',
                ],
            ],
        ];
        $result->setSecondaryClassificationTypes($secondaryTypes);

        $result->setClassificationKeywords(['AI', 'machine learning', 'LLM']);
        $result->setClassificationDetectedEntities(['OpenAI', 'Google AI']);
        $result->setClassificationUserObjective('Track AI developments');
        $result->setGeographicScope('Global');

        $sourceSuggestions = [
            [
                'name' => 'TechCrunch',
                'type' => 'news',
                'relevance' => 'high',
                'score' => 85,
                'url' => 'https://techcrunch.com',
            ],
            [
                'name' => 'ArXiv',
                'type' => 'academic',
                'relevance' => 'medium',
                'score' => 70,
                'url' => null,
            ],
        ];
        $result->setSourceSuggestions($sourceSuggestions);

        $actorSuggestions = [
            [
                'name' => 'Sam Altman',
                'type' => 'executive',
                'relevance' => 'high',
                'score' => 90,
            ],
            [
                'name' => 'Sundar Pichai',
                'type' => 'executive',
                'relevance' => 'high',
                'score' => 85,
            ],
        ];
        $result->setActorSuggestions($actorSuggestions);

        $topics = [
            [
                'label' => 'AI Development',
                'keywords' => ['GPT', 'LLM'],
                'relevanceScore' => 95,
                'searchQueryTemplate' => 'AI development news',
            ],
        ];
        $result->setClassificationTopics($topics);

        $this->assertEquals(MonitoringType::COMPETITIVE, $result->getPrimaryClassificationType());
        $this->assertEquals(85, $result->getPrimaryClassificationConfidence());
        $this->assertNotNull($result->getPrimaryClassificationJustification());
        $this->assertEquals('Strong CI indicators', $result->getPrimaryClassificationJustification()->en);
        $this->assertEquals($secondaryTypes, $result->getSecondaryClassificationTypes());
        $this->assertEquals(['AI', 'machine learning', 'LLM'], $result->getClassificationKeywords());
        $this->assertEquals(['OpenAI', 'Google AI'], $result->getClassificationDetectedEntities());
        $this->assertEquals('Track AI developments', $result->getClassificationUserObjective());
        $this->assertEquals('Global', $result->getGeographicScope());
        $this->assertEquals($sourceSuggestions, $result->getSourceSuggestions());
        $this->assertEquals($actorSuggestions, $result->getActorSuggestions());
        $this->assertEquals($topics, $result->getClassificationTopics());
    }

    public function testSetAndGetDeepSearchReadiness(): void
    {
        $result = new AnalysisResult($this->watchFile, 'content');

        $deepSearchReadiness = new DeepSearchReadiness(
            ready: true,
            reason: new TranslatedText('Objectif bien défini', 'Well-defined objective'),
            suggestedSearchQueries: ['OpenAI GPT-5', 'Google Gemini Ultra'],
        );

        $result->setDeepSearchReadiness($deepSearchReadiness);

        $this->assertInstanceOf(DeepSearchReadiness::class, $result->getDeepSearchReadiness());
        $this->assertNotNull($result->getDeepSearchReadiness());
        $this->assertTrue($result->getDeepSearchReadiness()->isReady());
        $this->assertEquals('Well-defined objective', $result->getDeepSearchReadiness()->getReason()->en);
        $this->assertEquals(
            ['OpenAI GPT-5', 'Google Gemini Ultra'],
            $result->getDeepSearchReadiness()
->getSuggestedSearchQueries()
        );
    }

    public function testSetClassificationDataFromWatchFileTypeClassificationResult(): void
    {
        $result = new AnalysisResult($this->watchFile, 'content');

        $classificationResult = new WatchFileTypeClassificationResult(
            primaryType: new WatchFileTypeClassificationItem(
                type: MonitoringType::COMPETITIVE,
                confidenceScore: 90,
                justification: new TranslatedText('Focus CI clair', 'Clear CI focus'),
            ),
            secondaryTypes: [
                new WatchFileTypeClassificationItem(
                    type: MonitoringType::TECHNOLOGICAL,
                    confidenceScore: 65,
                    justification: new TranslatedText('Aspect tech présent', 'Tech aspect present'),
                ),
            ],
            topics: [
                new Topic(
                    label: 'Competitive Intelligence',
                    keywords: ['competitor', 'market'],
                    relevanceScore: 90,
                    searchQueryTemplate: 'competitor analysis {company}',
                ),
            ],
            keywords: ['competitor analysis', 'market share', 'pricing'],
            detectedEntities: ['Company A', 'Company B'],
            userObjective: 'Monitor competitors',
            geographicScope: 'Europe',
            sourceSuggestions: [
                new SourceSuggestion(
                    name: 'Industry Reports',
                    type: 'report',
                    relevance: 'high',
                    score: 85,
                    url: 'https://reports.example.com',
                ),
                new SourceSuggestion(name: 'News Sites', type: 'news', relevance: 'medium', score: 70),
            ],
            actorSuggestions: [
                new ActorSuggestion(name: 'CEO of Company A', type: 'executive', relevance: 'high', score: 90),
                new ActorSuggestion(name: 'CTO of Company B', type: 'executive', relevance: 'medium', score: 75),
            ],
            deepSearchReadiness: new DeepSearchReadiness(
                ready: true,
                reason: new TranslatedText('Périmètre clair', 'Clear scope'),
                suggestedSearchQueries: ['Company A pricing strategy', 'Company B product roadmap'],
            ),
        );

        $result->setClassificationData($classificationResult);

        $this->assertEquals(MonitoringType::COMPETITIVE, $result->getPrimaryClassificationType());
        $this->assertEquals(90, $result->getPrimaryClassificationConfidence());
        $this->assertNotNull($result->getPrimaryClassificationJustification());
        $this->assertEquals('Clear CI focus', $result->getPrimaryClassificationJustification()->en);

        $secondaryTypes = $result->getSecondaryClassificationTypes();
        $this->assertNotNull($secondaryTypes);
        $this->assertCount(1, $secondaryTypes);
        $this->assertEquals('technological', $secondaryTypes[0]['type']);
        $this->assertEquals(65, $secondaryTypes[0]['confidence']);

        $topics = $result->getClassificationTopics();
        $this->assertNotNull($topics);
        $this->assertCount(1, $topics);
        $this->assertEquals('Competitive Intelligence', $topics[0]['label']);

        $this->assertEquals(['competitor analysis', 'market share', 'pricing'], $result->getClassificationKeywords());
        $this->assertEquals(['Company A', 'Company B'], $result->getClassificationDetectedEntities());
        $this->assertEquals('Monitor competitors', $result->getClassificationUserObjective());
        $this->assertEquals('Europe', $result->getGeographicScope());

        $sourceSuggestions = $result->getSourceSuggestions();
        $this->assertNotNull($sourceSuggestions);
        $this->assertCount(2, $sourceSuggestions);
        $this->assertEquals('Industry Reports', $sourceSuggestions[0]['name']);
        $this->assertEquals('report', $sourceSuggestions[0]['type']);
        $this->assertEquals(85, $sourceSuggestions[0]['score']);

        $actorSuggestions = $result->getActorSuggestions();
        $this->assertNotNull($actorSuggestions);
        $this->assertCount(2, $actorSuggestions);
        $this->assertEquals('CEO of Company A', $actorSuggestions[0]['name']);
        $this->assertEquals('executive', $actorSuggestions[0]['type']);

        $this->assertNotNull($result->getDeepSearchReadiness());
        $this->assertTrue($result->getDeepSearchReadiness()->isReady());
        $this->assertEquals('Clear scope', $result->getDeepSearchReadiness()->getReason()->en);
        $this->assertEquals(
            ['Company A pricing strategy', 'Company B product roadmap'],
            $result->getDeepSearchReadiness()
->getSuggestedSearchQueries()
        );
    }

    public function testNullableFields(): void
    {
        $result = new AnalysisResult($this->watchFile, 'content');

        $this->assertNull($result->getConfidenceScore());
        $this->assertNull($result->getPrimaryClassificationType());
        $this->assertNull($result->getPrimaryClassificationConfidence());
        $this->assertNull($result->getPrimaryClassificationJustification());
        $this->assertNull($result->getSecondaryClassificationTypes());
        $this->assertNull($result->getClassificationKeywords());
        $this->assertNull($result->getClassificationDetectedEntities());
        $this->assertNull($result->getClassificationUserObjective());
        $this->assertNull($result->getGeographicScope());
        $this->assertNull($result->getSourceSuggestions());
        $this->assertNull($result->getActorSuggestions());
        $this->assertNull($result->getClassificationTopics());
        $this->assertNull($result->getDeepSearchReadiness());
    }
}
