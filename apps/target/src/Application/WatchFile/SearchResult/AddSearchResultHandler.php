<?php

declare(strict_types=1);

namespace App\Application\WatchFile\SearchResult;

use App\Domain\WatchFile\Exception\SearchQueryNotFoundException;
use App\Domain\WatchFile\SearchQueryGatewayInterface;
use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
readonly class AddSearchResultHandler
{
    public function __construct(
        private SearchQueryGatewayInterface $searchQueryGateway,
        private SearchResultGatewayInterface $searchResultGateway,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AddSearchResultAction $action): SearchResult
    {
        $this->logger?->debug('Add search result action received', [
            'search_query_id' => $action->searchQueryId,
            'url' => $action->url,
        ]);

        try {
            Assert::uuid($action->searchQueryId, 'Search query ID must be a valid UUID');
            Assert::stringNotEmpty($action->url, 'URL cannot be empty');
        } catch (\InvalidArgumentException $e) {
            $this->logger?->error('Invalid action parameters', [
                'search_query_id' => $action->searchQueryId,
                'url' => $action->url,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        // Check for existing search result with same URL and search query (deduplication)
        $existingResult = $this->searchResultGateway->findByUrlAndSearchQuery(
            $action->url,
            $action->searchQueryId,
        );

        if (null !== $existingResult) {
            $this->logger?->debug('Search result already exists, skipping creation', [
                'result_id' => $existingResult->getId(),
                'search_query_id' => $action->searchQueryId,
            ]);

            return $existingResult;
        }

        try {
            $searchQuery = $this->searchQueryGateway->get($action->searchQueryId);
        } catch (SearchQueryNotFoundException $e) {
            $this->logger?->error('Search query not found', [
                'search_query_id' => $action->searchQueryId,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException('SearchQuery not found', 0, $e);
        }

        $searchResult = new SearchResult(
            title: $action->title,
            description: $action->description,
            url: $action->url,
            searchQuery: $searchQuery,
            content: $action->content,
        );

        $this->searchResultGateway->save($searchResult);

        $this->logger?->debug('Search result added successfully', [
            'result_id' => $searchResult->getId(),
            'search_query_id' => $action->searchQueryId,
            'url' => $action->url,
        ]);

        return $searchResult;
    }
}
