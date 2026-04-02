<?php

declare(strict_types=1);

namespace App\Application\WatchFile\SearchResult;

use App\Domain\WatchFile\Exception\SearchResultNotFoundException;
use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
readonly class UpdateSearchResultScoringHandler
{
    public function __construct(
        private SearchResultGatewayInterface $searchResultGateway,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(UpdateSearchResultScoringAction $action): SearchResult
    {
        $this->logger?->debug('Update search result scoring action received', [
            'search_result_id' => $action->searchResultId,
            'quality_score' => $action->qualityScore,
            'is_selected' => $action->isSelected,
            'selection_rank' => $action->selectionRank,
        ]);

        try {
            Assert::uuid($action->searchResultId, 'Search result ID must be a valid UUID');
        } catch (\InvalidArgumentException $e) {
            $this->logger?->error('Invalid search result ID provided', [
                'search_result_id' => $action->searchResultId,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        if (null !== $action->qualityScore) {
            try {
                Assert::range($action->qualityScore, 0, 100, 'Quality score must be between 0 and 100');
            } catch (\InvalidArgumentException $e) {
                $this->logger?->error('Invalid quality score provided', [
                    'quality_score' => $action->qualityScore,
                    'exception' => $e->getMessage(),
                ]);

                throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
            }
        }

        if (null !== $action->selectionRank) {
            try {
                Assert::range($action->selectionRank, 1, 10, 'Selection rank must be between 1 and 10');
            } catch (\InvalidArgumentException $e) {
                $this->logger?->error('Invalid selection rank provided', [
                    'selection_rank' => $action->selectionRank,
                    'exception' => $e->getMessage(),
                ]);

                throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
            }
        }

        try {
            $searchResult = $this->searchResultGateway->get($action->searchResultId);
        } catch (SearchResultNotFoundException $e) {
            $this->logger?->error('Search result not found', [
                'search_result_id' => $action->searchResultId,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException('SearchResult not found', 0, $e);
        }

        $searchResult->updateScoring(
            qualityScore: $action->qualityScore,
            isSelected: $action->isSelected,
            selectionRank: $action->selectionRank,
        );

        $this->searchResultGateway->save($searchResult);

        $this->logger?->debug('Search result scoring updated successfully', [
            'search_result_id' => $searchResult->getId(),
            'quality_score' => $searchResult->getQualityScore(),
            'is_selected' => $searchResult->isSelected(),
            'selection_rank' => $searchResult->getSelectionRank(),
        ]);

        return $searchResult;
    }
}
