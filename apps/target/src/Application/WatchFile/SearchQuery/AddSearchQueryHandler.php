<?php

declare(strict_types=1);

namespace App\Application\WatchFile\SearchQuery;

use App\Domain\WatchFile\Exception\StrategicQuestionNotFoundException;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\SearchQueryGatewayInterface;
use App\Domain\WatchFile\StrategicQuestionGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
readonly class AddSearchQueryHandler
{
    public function __construct(
        private StrategicQuestionGatewayInterface $strategicQuestionGateway,
        private SearchQueryGatewayInterface $searchQueryGateway,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AddSearchQueryAction $action): SearchQuery
    {
        $this->logger?->debug('Add search query action received', [
            'strategic_question_id' => $action->strategicQuestionId,
            'query_type' => $action->queryType,
        ]);

        if (empty($action->strategicQuestionId)) {
            $this->logger?->error('Invalid strategic question ID provided', [
                'strategic_question_id' => $action->strategicQuestionId,
            ]);

            throw new UnrecoverableMessageHandlingException('Invalid strategic question ID provided.');
        }

        // Check for existing search query with same parameters (deduplication)
        $existingQuery = $this->searchQueryGateway->findByTermAndStrategicQuestion(
            $action->query,
            $action->country,
            $action->language,
            $action->strategicQuestionId,
        );

        if (null !== $existingQuery) {
            $this->logger?->debug('Search query already exists, skipping creation', [
                'query_id' => $existingQuery->getId(),
                'strategic_question_id' => $action->strategicQuestionId,
            ]);

            return $existingQuery;
        }

        try {
            $strategicQuestion = $this->strategicQuestionGateway->get($action->strategicQuestionId);
        } catch (StrategicQuestionNotFoundException $e) {
            $this->logger?->error('Strategic question not found', [
                'strategic_question_id' => $action->strategicQuestionId,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException('StrategicQuestion not found', 0, $e);
        }

        try {
            Assert::length($action->country, 2, 'Country code must be 2 characters (ISO 3166-1 alpha-2)');
            Assert::length($action->language, 2, 'Language code must be 2 characters (ISO 639-1)');
        } catch (\InvalidArgumentException $e) {
            $this->logger?->error('Invalid country or language code', [
                'country' => $action->country,
                'language' => $action->language,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException(
                'Invalid country or language code: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $searchQuery = new SearchQuery(
            searchTerm: $action->query,
            country: $action->country,
            language: $action->language,
            queryType: $action->queryType,
            rationale: $action->rationale,
            strategicQuestion: $strategicQuestion,
        );

        $this->searchQueryGateway->save($searchQuery);

        $this->logger?->debug('Search query added successfully', [
            'query_id' => $searchQuery->getId(),
            'strategic_question_id' => $action->strategicQuestionId,
            'query_type' => $action->queryType,
        ]);

        return $searchQuery;
    }
}
