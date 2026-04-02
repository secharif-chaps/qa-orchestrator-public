<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow\SearchQuery;

use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\StrategicQuestionGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class RunSearchQueryHandler
{
    public function __construct(
        private StrategicQuestionGatewayInterface $strategicQuestionGateway,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RunSearchQueryAction $action): void
    {
        try {
            $strategicQuestion = $this->strategicQuestionGateway->get($action->strategicQuestionId);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to retrieve strategic question', [
                'strategic_question_id' => $action->strategicQuestionId,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException('Strategic Question not found', 0, $e);
        }

        if ($strategicQuestion->getSearchQueries()->isEmpty()) {
            $this->logger?->info('No search queries found for strategic question', [
                'strategic_question_id' => $action->strategicQuestionId,
            ]);

            return;
        }

        $searchTerms = $strategicQuestion->getSearchQueries()
            ->map(fn (SearchQuery $searchQuery) => $searchQuery->getSearchTerm())
            ->toArray();

        $this->logger?->info('Running search queries', [
            'strategic_question_id' => $action->strategicQuestionId,
            'search_terms' => $searchTerms,
        ]);

        // @todo: Implement the search query execution logic
    }
}
