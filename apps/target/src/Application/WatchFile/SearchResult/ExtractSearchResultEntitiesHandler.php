<?php

declare(strict_types=1);

namespace App\Application\WatchFile\SearchResult;

use App\Domain\WatchFile\Exception\InvalidExtractionDataException;
use App\Domain\WatchFile\Exception\SearchResultNotFoundException;
use App\Domain\WatchFile\ExtractionType;
use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultExtraction;
use App\Domain\WatchFile\SearchResultExtractionGatewayInterface;
use App\Domain\WatchFile\SearchResultGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
readonly class ExtractSearchResultEntitiesHandler
{
    private const float MIN_CONFIDENCE_SCORE = 0.6;

    public function __construct(
        private SearchResultGatewayInterface $searchResultGateway,
        private SearchResultExtractionGatewayInterface $extractionGateway,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ExtractSearchResultEntitiesAction $action): SearchResult
    {
        $this->logger?->debug('Extract search result entities action received', [
            'search_result_id' => $action->searchResultId,
            'extractions_count' => \count($action->extractions),
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

        try {
            $searchResult = $this->searchResultGateway->get($action->searchResultId);
        } catch (SearchResultNotFoundException $e) {
            $this->logger?->error('Search result not found', [
                'search_result_id' => $action->searchResultId,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException('SearchResult not found', 0, $e);
        }

        $savedCount = 0;
        $rejectedCount = 0;

        foreach ($action->extractions as $index => $extraction) {
            try {
                Assert::keyExists($extraction, 'type', \sprintf('Extraction #%d must have type', $index));
                Assert::keyExists($extraction, 'data', \sprintf('Extraction #%d must have data', $index));
                Assert::keyExists(
                    $extraction,
                    'confidenceScore',
                    \sprintf('Extraction #%d must have confidenceScore', $index)
                );

                $type = $extraction['type'];
                Assert::string($type, \sprintf('Extraction #%d type must be a string', $index));
                $data = $extraction['data'];
                $confidenceScore = $extraction['confidenceScore'];

                Assert::oneOf(
                    $type,
                    [ExtractionType::ACTOR->value, ExtractionType::SOURCE->value, ExtractionType::TOPIC->value],
                    'Type must be one of: actor, source, topic'
                );

                Assert::isArray($data, \sprintf('Extraction #%d data must be an array', $index));

                Assert::numeric($confidenceScore, \sprintf('Extraction #%d confidenceScore must be numeric', $index));
                $confidenceScore = (float) $confidenceScore;

                if ($confidenceScore < self::MIN_CONFIDENCE_SCORE) {
                    $this->logger?->debug('Extraction rejected due to low confidence', [
                        'search_result_id' => $action->searchResultId,
                        'extraction_index' => $index,
                        'confidence_score' => $confidenceScore,
                        'minimum_required' => self::MIN_CONFIDENCE_SCORE,
                    ]);
                    ++$rejectedCount;

                    continue;
                }

                $this->validateEntityData($type, $data, $index);

                $extractionEntity = new SearchResultExtraction(
                    searchResult: $searchResult,
                    type: ExtractionType::from($type),
                    data: $data,
                    confidenceScore: $confidenceScore,
                );

                $this->extractionGateway->save($extractionEntity);
                ++$savedCount;

                $this->logger?->debug('Extraction saved successfully', [
                    'search_result_id' => $action->searchResultId,
                    'extraction_id' => $extractionEntity->getId(),
                    'type' => $type,
                    'confidence_score' => $confidenceScore,
                ]);
            } catch (\InvalidArgumentException|InvalidExtractionDataException $e) {
                $this->logger?->warning('Invalid extraction data, skipping', [
                    'search_result_id' => $action->searchResultId,
                    'extraction_index' => $index,
                    'exception' => $e->getMessage(),
                ]);
                ++$rejectedCount;
            }
        }

        $this->logger?->info('Search result entities extraction completed', [
            'search_result_id' => $searchResult->getId(),
            'saved_count' => $savedCount,
            'rejected_count' => $rejectedCount,
            'total_extractions' => \count($action->extractions),
        ]);

        return $searchResult;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateEntityData(string $type, array $data, int $index): void
    {
        $extractionType = ExtractionType::tryFrom($type);
        if (null === $extractionType) {
            throw new InvalidExtractionDataException(\sprintf('Unknown entity type: %s', $type));
        }

        match ($extractionType) {
            ExtractionType::ACTOR => $this->validateActorData($data, $index),
            ExtractionType::SOURCE => $this->validateSourceData($data, $index),
            ExtractionType::TOPIC => $this->validateTopicData($data, $index),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateActorData(array $data, int $index): void
    {
        try {
            $label = $data['label'] ?? $data['name'] ?? null;
            Assert::notNull($label, \sprintf('Actor extraction #%d must have label or name', $index));
            Assert::string($label, 'Actor label must be a string');
            Assert::notEmpty($label, 'Actor label must not be empty');

            if (isset($data['explanation_fr'])) {
                Assert::string($data['explanation_fr'], 'Actor explanation_fr must be a string');
            }
            if (isset($data['explanation_en'])) {
                Assert::string($data['explanation_en'], 'Actor explanation_en must be a string');
            }

            if (isset($data['type'])) {
                Assert::string($data['type'], 'Actor type must be a string');
            }
        } catch (\InvalidArgumentException $e) {
            throw new InvalidExtractionDataException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateSourceData(array $data, int $index): void
    {
        try {
            Assert::keyExists($data, 'url', \sprintf('Source extraction #%d must have url', $index));
            Assert::string($data['url'], 'Source URL must be a string');
            Assert::notEmpty($data['url'], 'Source URL must not be empty');

            if (isset($data['name'])) {
                Assert::string($data['name'], 'Source name must be a string');
            }

            if (isset($data['description_fr'])) {
                Assert::string($data['description_fr'], 'Source description_fr must be a string');
            }
            if (isset($data['description_en'])) {
                Assert::string($data['description_en'], 'Source description_en must be a string');
            }

            if (isset($data['relevance_fr'])) {
                Assert::string($data['relevance_fr'], 'Source relevance_fr must be a string');
            }
            if (isset($data['relevance_en'])) {
                Assert::string($data['relevance_en'], 'Source relevance_en must be a string');
            }

            if (isset($data['query'])) {
                Assert::string($data['query'], 'Source query must be a string');
            }

            if (isset($data['primaryDomain'])) {
                Assert::string($data['primaryDomain'], 'Source primaryDomain must be a string');
            }

            if (isset($data['type'])) {
                Assert::string($data['type'], 'Source type must be a string');
            }
        } catch (\InvalidArgumentException $e) {
            throw new InvalidExtractionDataException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateTopicData(array $data, int $index): void
    {
        try {
            Assert::keyExists($data, 'name', \sprintf('Topic extraction #%d must have name', $index));
            Assert::string($data['name'], 'Topic name must be a string');
            Assert::notEmpty($data['name'], 'Topic name must not be empty');

            if (isset($data['explanation'])) {
                Assert::string($data['explanation'], 'Topic explanation must be a string');
            }
        } catch (\InvalidArgumentException $e) {
            throw new InvalidExtractionDataException($e->getMessage(), 0, $e);
        }
    }
}
