<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\SearchResult;

use App\Application\WatchFile\SearchResult\AddSearchResultAction;
use App\Application\WatchFile\SearchResult\AddSearchResultHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\SearchQueryGatewayInterface;
use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultGatewayInterface;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\WatchFile\NullSearchQueryGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullSearchResultGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

class AddSearchResultHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private SearchQueryGatewayInterface $searchQueryGateway;
    private SearchResultGatewayInterface $searchResultGateway;
    private AddSearchResultHandler $handler;

    protected function setUp(): void
    {
        $this->searchQueryGateway = new NullSearchQueryGateway();
        $this->searchResultGateway = new NullSearchResultGateway();

        $this->handler = new AddSearchResultHandler($this->searchQueryGateway, $this->searchResultGateway);
    }

    public function testAddSearchResult(): void
    {
        $searchQuery = $this->createSearchQuery();
        $this->searchQueryGateway->save($searchQuery);

        $searchQueryId = $searchQuery->getId();
        $this->assertNotNull($searchQueryId);

        $action = new AddSearchResultAction(
            searchQueryId: $searchQueryId,
            title: 'Test Result Title',
            description: 'This is a test description for the search result.',
            url: 'https://example.com/test-article',
        );

        $result = ($this->handler)($action);

        $this->assertNotNull($result->getId());
        $this->assertEquals('Test Result Title', $result->getTitle());
        $this->assertEquals('This is a test description for the search result.', $result->getDescription());
        $this->assertEquals('https://example.com/test-article', $result->getUrl());
        $this->assertSame($searchQuery, $result->getSearchQuery());
    }

    public function testAddSearchResultReturnExistingWhenDuplicate(): void
    {
        $searchQuery = $this->createSearchQuery();
        $this->searchQueryGateway->save($searchQuery);

        $searchQueryId = $searchQuery->getId();
        $this->assertNotNull($searchQueryId);

        $existingResult = new SearchResult(
            title: 'Existing Title',
            description: 'Existing description',
            url: 'https://example.com/existing',
            searchQuery: $searchQuery,
        );
        $this->searchResultGateway->save($existingResult);

        $action = new AddSearchResultAction(
            searchQueryId: $searchQueryId,
            title: 'New Title that should be ignored',
            description: 'New description that should be ignored',
            url: 'https://example.com/existing',
        );

        $result = ($this->handler)($action);

        $this->assertSame($existingResult, $result);
        $this->assertEquals('Existing Title', $result->getTitle());
        $this->assertEquals('Existing description', $result->getDescription());
    }

    public function testAddSearchResultAllowsSameUrlForDifferentSearchQueries(): void
    {
        $searchQuery1 = $this->createSearchQuery();
        $this->searchQueryGateway->save($searchQuery1);

        $searchQuery2 = $this->createSearchQuery();
        $this->searchQueryGateway->save($searchQuery2);

        $searchQuery2Id = $searchQuery2->getId();
        $this->assertNotNull($searchQuery2Id);

        $existingResult = new SearchResult(
            title: 'Title 1',
            description: 'Description 1',
            url: 'https://example.com/same-url',
            searchQuery: $searchQuery1,
        );
        $this->searchResultGateway->save($existingResult);

        $action = new AddSearchResultAction(
            searchQueryId: $searchQuery2Id,
            title: 'Title 2',
            description: 'Description 2',
            url: 'https://example.com/same-url',
        );

        $result = ($this->handler)($action);

        $this->assertNotSame($existingResult, $result);
        $this->assertSame($searchQuery2, $result->getSearchQuery());
    }

    public function testAddSearchResultWithSearchQueryNotFoundThrowsException(): void
    {
        $nonExistentId = (string) Uuid::v4();
        $action = new AddSearchResultAction(
            searchQueryId: $nonExistentId,
            title: 'Test Title',
            description: 'Test description',
            url: 'https://example.com/test',
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('SearchQuery not found');

        ($this->handler)($action);
    }

    public function testAddSearchResultWithEmptySearchQueryIdThrowsException(): void
    {
        $action = new AddSearchResultAction(
            searchQueryId: '',
            title: 'Test Title',
            description: 'Test description',
            url: 'https://example.com/test',
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Search query ID must be a valid UUID');

        ($this->handler)($action);
    }

    public function testAddSearchResultWithEmptyUrlThrowsException(): void
    {
        $searchQuery = $this->createSearchQuery();
        $this->searchQueryGateway->save($searchQuery);

        $searchQueryId = $searchQuery->getId();
        $this->assertNotNull($searchQueryId);

        $action = new AddSearchResultAction(
            searchQueryId: $searchQueryId,
            title: 'Test Title',
            description: 'Test description',
            url: '',
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('URL cannot be empty');

        ($this->handler)($action);
    }

    public function testAddMultipleSearchResultsForSameSearchQuery(): void
    {
        $searchQuery = $this->createSearchQuery();
        $this->searchQueryGateway->save($searchQuery);

        $searchQueryId = $searchQuery->getId();
        $this->assertNotNull($searchQueryId);

        $action1 = new AddSearchResultAction(
            searchQueryId: $searchQueryId,
            title: 'Result 1',
            description: 'Description 1',
            url: 'https://example.com/result-1',
        );

        $action2 = new AddSearchResultAction(
            searchQueryId: $searchQueryId,
            title: 'Result 2',
            description: 'Description 2',
            url: 'https://example.com/result-2',
        );

        $result1 = ($this->handler)($action1);
        $result2 = ($this->handler)($action2);

        $this->assertNotSame($result1, $result2);
        $this->assertEquals('https://example.com/result-1', $result1->getUrl());
        $this->assertEquals('https://example.com/result-2', $result2->getUrl());
        $this->assertEquals(2, $this->searchResultGateway->countBySearchQuery($searchQueryId));
    }

    private function createSearchQuery(?string $id = null): SearchQuery
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
        $this->forcePropertyValue($searchQuery, $id ?? (string) Uuid::v4());

        return $searchQuery;
    }
}
