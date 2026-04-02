<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\SearchResult;

use App\Application\WatchFile\SearchResult\UpdateSearchResultScoringAction;
use App\Application\WatchFile\SearchResult\UpdateSearchResultScoringHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultGatewayInterface;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\WatchFile\NullSearchResultGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

class UpdateSearchResultScoringHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private SearchResultGatewayInterface $searchResultGateway;
    private UpdateSearchResultScoringHandler $handler;

    protected function setUp(): void
    {
        $this->searchResultGateway = new NullSearchResultGateway();

        $this->handler = new UpdateSearchResultScoringHandler($this->searchResultGateway, new NullLogger());
    }

    public function testUpdateSearchResultScoringWithAllFields(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new UpdateSearchResultScoringAction(
            searchResultId: $searchResultId,
            qualityScore: 85,
            isSelected: true,
            selectionRank: 3,
        );

        $result = ($this->handler)($action);

        $this->assertSame($searchResult, $result);
        $this->assertEquals(85, $result->getQualityScore());
        $this->assertTrue($result->isSelected());
        $this->assertEquals(3, $result->getSelectionRank());
    }

    public function testUpdateSearchResultScoringWithPartialUpdate(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        // Update only qualityScore
        $action = new UpdateSearchResultScoringAction(searchResultId: $searchResultId, qualityScore: 75);

        $result = ($this->handler)($action);

        $this->assertEquals(75, $result->getQualityScore());
        $this->assertFalse($result->isSelected()); // Default value unchanged
        $this->assertNull($result->getSelectionRank()); // Unchanged
    }

    public function testUpdateSearchResultScoringWithSelectionOnly(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new UpdateSearchResultScoringAction(
            searchResultId: $searchResultId,
            isSelected: true,
            selectionRank: 5,
        );

        $result = ($this->handler)($action);

        $this->assertNull($result->getQualityScore()); // Unchanged
        $this->assertTrue($result->isSelected());
        $this->assertEquals(5, $result->getSelectionRank());
    }

    public function testUpdateSearchResultScoringWithSearchResultNotFoundThrowsException(): void
    {
        $nonExistentId = (string) Uuid::v4();
        $action = new UpdateSearchResultScoringAction(searchResultId: $nonExistentId, qualityScore: 80);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('SearchResult not found');

        ($this->handler)($action);
    }

    public function testUpdateSearchResultScoringWithInvalidSearchResultIdThrowsException(): void
    {
        $action = new UpdateSearchResultScoringAction(searchResultId: 'invalid-uuid', qualityScore: 80);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Search result ID must be a valid UUID');

        ($this->handler)($action);
    }

    public function testUpdateSearchResultScoringWithInvalidQualityScoreThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new UpdateSearchResultScoringAction(
            searchResultId: $searchResultId,
            qualityScore: 150, // Invalid: > 100
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Quality score must be between 0 and 100');

        ($this->handler)($action);
    }

    public function testUpdateSearchResultScoringWithInvalidSelectionRankThrowsException(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new UpdateSearchResultScoringAction(
            searchResultId: $searchResultId,
            selectionRank: 15, // Invalid: > 10
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Selection rank must be between 1 and 10');

        ($this->handler)($action);
    }

    public function testUpdateSearchResultScoringWithQualityScoreAtBoundaries(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        // Test minimum boundary (0)
        $actionMin = new UpdateSearchResultScoringAction(searchResultId: $searchResultId, qualityScore: 0);
        $resultMin = ($this->handler)($actionMin);
        $this->assertEquals(0, $resultMin->getQualityScore());

        // Test maximum boundary (100)
        $actionMax = new UpdateSearchResultScoringAction(searchResultId: $searchResultId, qualityScore: 100);
        $resultMax = ($this->handler)($actionMax);
        $this->assertEquals(100, $resultMax->getQualityScore());
    }

    public function testUpdateSearchResultScoringWithSelectionRankAtBoundaries(): void
    {
        $searchResult = $this->createSearchResult();
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        // Test minimum boundary (1)
        $actionMin = new UpdateSearchResultScoringAction(searchResultId: $searchResultId, selectionRank: 1);
        $resultMin = ($this->handler)($actionMin);
        $this->assertEquals(1, $resultMin->getSelectionRank());

        // Test maximum boundary (10)
        $actionMax = new UpdateSearchResultScoringAction(searchResultId: $searchResultId, selectionRank: 10);
        $resultMax = ($this->handler)($actionMax);
        $this->assertEquals(10, $resultMax->getSelectionRank());
    }

    public function testUpdateSearchResultScoringUnselectsResult(): void
    {
        $searchResult = $this->createSearchResult();
        $searchResult->updateScoring(isSelected: true, selectionRank: 5);
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        // Unselect the result
        $action = new UpdateSearchResultScoringAction(
            searchResultId: $searchResultId,
            isSelected: false,
            selectionRank: null,
        );

        $result = ($this->handler)($action);

        $this->assertFalse($result->isSelected());
        $this->assertNull($result->getSelectionRank());
    }

    public function testUpdateSearchResultScoringUnselectsResultAutomaticallyClearsSelectionRank(): void
    {
        $searchResult = $this->createSearchResult();
        $searchResult->updateScoring(isSelected: true, selectionRank: 5);
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        // Unselect the result without explicitly passing selectionRank
        // The selectionRank should be automatically cleared by updateScoring logic
        $action = new UpdateSearchResultScoringAction(searchResultId: $searchResultId, isSelected: false);

        $result = ($this->handler)($action);

        $this->assertFalse($result->isSelected());
        $this->assertNull(
            $result->getSelectionRank(),
            'Selection rank should be automatically cleared when isSelected is false'
        );
    }

    public function testUpdateSearchResultScoringIgnoresSelectionRankWhenUnselecting(): void
    {
        $searchResult = $this->createSearchResult();
        $searchResult->updateScoring(isSelected: true, selectionRank: 5);
        $this->searchResultGateway->save($searchResult);

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $action = new UpdateSearchResultScoringAction(
            searchResultId: $searchResultId,
            isSelected: false,
            selectionRank: 7, // This should be ignored
        );

        $result = ($this->handler)($action);

        $this->assertFalse($result->isSelected());
        $this->assertNull(
            $result->getSelectionRank(),
            'Selection rank should be null when isSelected is false, even if selectionRank is provided'
        );
    }

    private function createSearchResult(): SearchResult
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
        $this->forcePropertyValue($searchQuery, (string) Uuid::v4());

        $searchResult = new SearchResult(
            title: 'Test Result Title',
            description: 'Test description',
            url: 'https://example.com/test',
            searchQuery: $searchQuery,
            content: 'Test content',
        );

        return $searchResult;
    }
}
