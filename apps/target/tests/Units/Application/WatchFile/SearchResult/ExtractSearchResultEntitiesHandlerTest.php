<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\SearchResult;

use App\Application\WatchFile\SearchResult\ExtractSearchResultEntitiesAction;
use App\Application\WatchFile\SearchResult\ExtractSearchResultEntitiesHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\ExtractionType;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultExtractionGatewayInterface;
use App\Domain\WatchFile\SearchResultGatewayInterface;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\WatchFile\NullSearchResultExtractionGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullSearchResultGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

class ExtractSearchResultEntitiesHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private SearchResultGatewayInterface $searchResultGateway;
    private SearchResultExtractionGatewayInterface $extractionGateway;
    private LoggerInterface $logger;
    private ExtractSearchResultEntitiesHandler $handler;

    protected function setUp(): void
    {
        $this->searchResultGateway = new NullSearchResultGateway();
        $this->extractionGateway = new NullSearchResultExtractionGateway();
        $this->logger = new NullLogger();

        $this->handler = new ExtractSearchResultEntitiesHandler(
            $this->searchResultGateway,
            $this->extractionGateway,
            $this->logger,
        );
    }

    public function testExtractEntitiesSuccessfully(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Tesla Inc.',
                        'type' => 'company',
                        'primaryDomain' => 'tesla.com',
                        'score' => 0.95,
                        'description' => 'Electric vehicle manufacturer',
                        'confidence' => 0.9,
                    ],
                    'confidenceScore' => 0.9,
                ],
                [
                    'type' => 'source',
                    'data' => [
                        'url' => 'https://techcrunch.com/article',
                        'name' => 'TechCrunch',
                        'type' => 'news',
                        'primaryDomain' => 'techcrunch.com',
                        'score' => 0.85,
                        'description' => 'Technology news source',
                        'confidence' => 0.8,
                    ],
                    'confidenceScore' => 0.8,
                ],
                [
                    'type' => 'topic',
                    'data' => [
                        'name' => 'Electric Vehicles',
                        'score' => 0.75,
                        'description' => 'EV market trends',
                        'confidence' => 0.7,
                    ],
                    'confidenceScore' => 0.7,
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(3, $extractions);

        $actorExtraction = $extractions[0];
        $this->assertEquals(ExtractionType::ACTOR, $actorExtraction->getType());
        $this->assertEquals(0.9, $actorExtraction->getConfidenceScore());
        $this->assertEquals('Tesla Inc.', $actorExtraction->getData()['name']);

        $sourceExtraction = $extractions[1];
        $this->assertEquals(ExtractionType::SOURCE, $sourceExtraction->getType());
        $this->assertEquals(0.8, $sourceExtraction->getConfidenceScore());
        $this->assertEquals('https://techcrunch.com/article', $sourceExtraction->getData()['url']);

        $topicExtraction = $extractions[2];
        $this->assertEquals(ExtractionType::TOPIC, $topicExtraction->getType());
        $this->assertEquals(0.7, $topicExtraction->getConfidenceScore());
        $this->assertEquals('Electric Vehicles', $topicExtraction->getData()['name']);
    }

    public function testExtractEntitiesRejectsLowConfidence(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Tesla Inc.',
                    ],
                    'confidenceScore' => 0.9, // Accepted
                ],
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Low Confidence Actor',
                    ],
                    'confidenceScore' => 0.5, // Rejected (< 0.6)
                ],
                [
                    'type' => 'source',
                    'data' => [
                        'url' => 'https://example.com',
                    ],
                    'confidenceScore' => 0.65, // Accepted
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(2, $extractions); // Only 2 saved
    }

    public function testExtractEntitiesWithInvalidTypeThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'invalid_type',
                    'data' => [
                        'name' => 'Test',
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(0, $extractions); // Rejected due to invalid type
    }

    public function testExtractEntitiesWithMissingRequiredFieldsThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [], // Empty data (missing required 'name' field)
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(0, $extractions); // Rejected due to missing data
    }

    public function testExtractEntitiesWithInvalidActorDataThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        // Missing required 'name' field
                        'type' => 'company',
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(0, $extractions); // Rejected due to invalid actor data
    }

    public function testExtractEntitiesWithInvalidSourceDataThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'source',
                    'data' => [
                        // Missing required 'url' field
                        'name' => 'Test Source',
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(0, $extractions); // Rejected due to invalid source data
    }

    public function testExtractEntitiesWithInvalidActorTypeThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Test Actor',
                        'type' => 'invalid_actor_type', // Invalid type
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(1, $extractions);
    }

    public function testExtractEntitiesWithEmptyActorNameThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => '', // Empty name
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(0, $extractions); // Rejected due to empty name
    }

    public function testExtractEntitiesWithEmptySourceUrlThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'source',
                    'data' => [
                        'url' => '', // Empty URL
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(0, $extractions); // Rejected due to empty URL
    }

    public function testExtractEntitiesWithSearchResultNotFoundThrowsException(): void
    {
        $nonExistentId = (string) Uuid::v4();

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $nonExistentId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Test Actor',
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('SearchResult not found');

        ($this->handler)($action);
    }

    public function testExtractEntitiesWithInvalidUuidThrowsException(): void
    {
        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: 'invalid-uuid',
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Test Actor',
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Search result ID must be a valid UUID');

        ($this->handler)($action);
    }

    public function testExtractEntitiesWithMixedValidAndInvalidExtractions(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Valid Actor',
                    ],
                    'confidenceScore' => 0.9, // Valid
                ],
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Low Confidence',
                    ],
                    'confidenceScore' => 0.5, // Rejected: low confidence
                ],
                [
                    'type' => 'source',
                    'data' => [
                        'url' => 'https://example.com',
                    ],
                    'confidenceScore' => 0.75, // Valid
                ],
                [
                    'type' => 'actor',
                    'data' => [], // Rejected: missing name
                    'confidenceScore' => 0.8,
                ],
                [
                    'type' => 'topic',
                    'data' => [
                        'name' => 'Valid Topic',
                    ],
                    'confidenceScore' => 0.7, // Valid
                ],
            ],
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(3, $extractions); // Only 3 valid extractions saved
    }

    public function testExtractEntitiesWithEmptyExtractionsArray(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(searchResultId: $searchResultId, extractions: []);

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(0, $extractions);
    }

    public function testExtractEntitiesWithoutLogger(): void
    {
        $handlerWithoutLogger = new ExtractSearchResultEntitiesHandler(
            $this->searchResultGateway,
            $this->extractionGateway,
        );

        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new ExtractSearchResultEntitiesAction(
            searchResultId: $searchResultId,
            extractions: [
                [
                    'type' => 'actor',
                    'data' => [
                        'name' => 'Test Actor',
                    ],
                    'confidenceScore' => 0.8,
                ],
            ],
        );

        $result = ($handlerWithoutLogger)($action);

        $this->assertSame($searchResult, $result);
        $extractions = $this->extractionGateway->findBySearchResult($searchResult);
        $this->assertCount(1, $extractions);
    }

    private function createSearchResult(?string $id = null): SearchResult
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'objective', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile, 'watch-file-id');

        $strategicQuestion = new StrategicQuestion(
            question: new TranslatedText('Question FR', 'Question EN'),
            context: new TranslatedText('Contexte FR', 'Context EN'),
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 1,
            expectedOutputType: 'analysis',
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($strategicQuestion, 'strategic-question-id');

        $searchQuery = new SearchQuery(
            searchTerm: 'test search term',
            country: 'FR',
            language: 'fr',
            queryType: 'general',
            rationale: 'Test rationale',
            strategicQuestion: $strategicQuestion,
        );
        $this->forcePropertyValue($searchQuery, 'search-query-id');

        $searchResult = new SearchResult(
            title: 'Test Result',
            description: 'Test description',
            url: 'https://example.com/test',
            searchQuery: $searchQuery,
        );
        $this->forcePropertyValue($searchResult, $id ?? (string) Uuid::v4());

        return $searchResult;
    }
}
