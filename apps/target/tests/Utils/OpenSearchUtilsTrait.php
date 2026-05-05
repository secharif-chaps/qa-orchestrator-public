<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Document\DocumentFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\Actor;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\ManualValidationStatus;
use App\Domain\Document\ValidationReason;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use OpenSearch\Client;

trait OpenSearchUtilsTrait
{
    /**
     * Clean up all documents from OpenSearch index.
     */
    protected function cleanupOpenSearch(): void
    {
        /** @var Client $openSearch */
        $openSearch = self::getContainer()->get(Client::class);

        try {
            $openSearch->deleteByQuery([
                'index' => Document::INDEX_NAME,
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),
                    ],
                ],
                'refresh' => true,
            ]);
        } catch (\Exception $e) {
        }
    }

    /**
     * Refresh OpenSearch index to make documents searchable.
     */
    protected function refreshOpenSearchIndex(): void
    {
        /** @var Client $openSearch */
        $openSearch = self::getContainer()->get(Client::class);

        $openSearch->indices()
            ->refresh([
                'index' => Document::INDEX_NAME,
            ]);
    }

    /**
     * Targeted cleanup: delete only documents whose id contains the
     * given tag. Used by tests that namespace their fixtures with a
     * per-suite tag so concurrent test runs don't wipe each other's
     * data — preferred over {@see cleanupOpenSearch()} (match_all)
     * whenever the test owns its own tag.
     */
    protected function cleanupOpenSearchByTag(string $tag): void
    {
        /** @var Client $openSearch */
        $openSearch = self::getContainer()->get(Client::class);

        try {
            $openSearch->deleteByQuery([
                'index' => Document::INDEX_NAME,
                'body' => [
                    'query' => [
                        'wildcard' => [
                            'id' => '*' . $tag . '*',
                        ],
                    ],
                ],
                'refresh' => true,
                'conflicts' => 'proceed',
            ]);
        } catch (\Exception) {
            // best effort
        }
    }

    /**
     * Create test documents for a watch file with specific status distribution.
     *
     * @return array{watchFile: WatchFile, actor1: Actor, actor2: Actor, source1: Source, source2: Source}
     */
    protected function createDocumentsForTesting(
        User $owner,
        int $validatedCount = 3,
        int $rejectedCount = 2,
        int $pendingCount = 5,
    ): array {
        /** @var DocumentGatewayInterface $documentGateway */
        $documentGateway = self::getContainer()->get(DocumentGatewayInterface::class);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $actor1 = ActorFactory::createOne([
            'label' => 'Test Actor - Orange',
            'primaryDomain' => 'orange.com',
        ]);
        $actor2 = ActorFactory::createOne([
            'label' => 'Test Actor - Chapsvision.com',
            'primaryDomain' => 'chapsvision.com',
        ]);

        $source1 = SourceFactory::new()
            ->create([
                'name' => 'Google News',
                'primaryDomain' => 'news.google.com',
            ]);

        $source2 = SourceFactory::new()
            ->create([
                'name' => 'ChapsVision Website',
                'primaryDomain' => 'chapsvision.com',
            ]);

        $return = [
            'watchFile' => $watchFile,
            'actor1' => $actor1,
            'actor2' => $actor2,
            'source1' => $source1,
            'source2' => $source2,
        ];

        for ($i = 0; $i < $validatedCount; ++$i) {
            $actor = 0 === $i % 2 ? $actor1 : $actor2;
            $source = 0 === $i % 2 ? $source1 : $source2;

            $document = DocumentFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withSource($source)
                ->withStatus(DocumentStatus::VALIDATED)
                ->create();

            $documentGateway->save($document);
        }

        for ($i = 0; $i < $rejectedCount; ++$i) {
            $actor = 0 === $i % 2 ? $actor1 : $actor2;
            $source = 0 === $i % 2 ? $source1 : $source2;

            $document = DocumentFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withSource($source)
                ->withStatus(DocumentStatus::REJECTED)
                ->create();

            $documentGateway->save($document);
        }

        for ($i = 0; $i < $pendingCount; ++$i) {
            $actor = 0 === $i % 2 ? $actor1 : $actor2;
            $source = 0 === $i % 2 ? $source1 : $source2;

            $document = DocumentFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withSource($source)
                ->withStatus(DocumentStatus::PENDING)
                ->create();

            $documentGateway->save($document);
        }

        $this->refreshOpenSearchIndex();

        return $return;
    }

    /**
     * Create a single validated document for testing.
     *
     * @return array{owner: User, watchFile: WatchFile, actor: Actor, source: Source, document: Document}
     */
    protected function createSingleDocumentForTesting(): array
    {
        /** @var DocumentGatewayInterface $documentGateway */
        $documentGateway = self::getContainer()->get(DocumentGatewayInterface::class);

        $owner = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
            'primaryDomain' => 'test.com',
        ]);

        $source = SourceFactory::new()
            ->create([
                'name' => 'Test Source',
                'primaryDomain' => 'test.com',
            ]);

        $document = DocumentFactory::new()
            ->withWatchFile($watchFile)
            ->withActor($actor)
            ->withSource($source)
            ->withStatus(DocumentStatus::VALIDATED)
            ->create();

        $documentGateway->save($document);

        $this->refreshOpenSearchIndex();

        return [
            'owner' => $owner,
            'watchFile' => $watchFile,
            'actor' => $actor,
            'source' => $source,
            'document' => $document,
        ];
    }

    /**
     * Create test documents with specific validation statuses (AI and manual).
     *
     * @param array<string, int>                                             $validationStatusCounts Map of validation status to count (e.g., ['ai_empty' => 2, 'ai_validated' => 3])
     * @param array{watchFile: WatchFile, actor: Actor, source: Source}|null $existingResources      Optional existing resources to reuse
     *
     * @return array{watchFile: WatchFile, actor: Actor, source: Source, documents: array<Document>}
     */
    protected function createDocumentsWithValidationStatuses(
        User $owner,
        array $validationStatusCounts,
        ?array $existingResources = null,
    ): array {
        /** @var DocumentGatewayInterface $documentGateway */
        $documentGateway = self::getContainer()->get(DocumentGatewayInterface::class);

        if (null !== $existingResources) {
            $watchFile = $existingResources['watchFile'];
            $actor = $existingResources['actor'];
            $source = $existingResources['source'];
        } else {
            $watchFile = WatchFileFactory::new()
                ->withCreatedBy($owner)
                ->withOwnedBy($owner)
                ->create();

            $actor = ActorFactory::createOne([
                'label' => 'Test Actor',
                'primaryDomain' => 'test.com',
            ]);

            $source = SourceFactory::new()
                ->create([
                    'name' => 'Test Source',
                    'primaryDomain' => 'test.com',
                ]);
        }

        $validator = UserFactory::createOne();

        $documents = [];

        foreach ($validationStatusCounts as $validationStatus => $count) {
            for ($i = 0; $i < $count; ++$i) {
                $document = $this->createDocumentWithValidationStatus(
                    $watchFile,
                    $actor,
                    $source,
                    $validationStatus,
                    $validator,
                    $documentGateway
                );
                $documents[] = $document;
            }
        }

        $this->refreshOpenSearchIndex();

        return [
            'watchFile' => $watchFile,
            'actor' => $actor,
            'source' => $source,
            'documents' => $documents,
        ];
    }

    /**
     * Create a single document with a specific validation status.
     *
     * IMPORTANT: When creating ai_* statuses, manualStatus is kept empty.
     * When creating manual_* statuses, aiValidation is kept empty.
     * This ensures documents only match the intended validation status filter.
     */
    private function createDocumentWithValidationStatus(
        WatchFile $watchFile,
        Actor $actor,
        Source $source,
        string $validationStatus,
        User $validator,
        DocumentGatewayInterface $documentGateway,
    ): Document {
        // Prepare AI validation based on requested status
        $aiValidation = null;
        if (str_starts_with($validationStatus, 'ai_') && 'ai_empty' !== $validationStatus) {
            $aiStatus = match ($validationStatus) {
                'ai_validated' => AiValidationStatus::VALIDATED,
                'ai_rejected' => AiValidationStatus::REJECTED,
                'ai_uncertain' => AiValidationStatus::UNCERTAIN,
                'ai_pending' => AiValidationStatus::PENDING,
                'ai_failed' => AiValidationStatus::FAILED,
                default => throw new \InvalidArgumentException(\sprintf(
                    'Unknown AI validation status: %s',
                    $validationStatus
                )),
            };

            // Confidence score must be consistent with status
            $confidenceScore = match ($aiStatus) {
                AiValidationStatus::VALIDATED => 85,
                AiValidationStatus::UNCERTAIN => 50,
                AiValidationStatus::REJECTED, AiValidationStatus::PENDING, AiValidationStatus::FAILED => 15,
            };

            $aiValidation = new AIValidation(
                status: $aiStatus,
                confidenceScore: $confidenceScore,
                validationReason: new ValidationReason(
                    fr: \sprintf('Validation IA: %s', $aiStatus->value),
                    en: \sprintf('AI validation: %s', $aiStatus->value),
                ),
                processedAt: new \DateTimeImmutable(),
                referenceSubject: 'Test Subject'
            );
        }

        // Create document using factory with explicit values to override random defaults
        // Key: Use withAiValidation() for non-null values, and with() to force null for manual_* statuses
        $documentFactory = DocumentFactory::new()
            ->withWatchFile($watchFile)
            ->withActor($actor)
            ->withSource($source)
            ->withStatus(DocumentStatus::PENDING);

        // Set aiValidation explicitly based on the validation status type
        if (null !== $aiValidation) {
            // For ai_* statuses (except ai_empty), use withAiValidation()
            $documentFactory = $documentFactory->withAiValidation($aiValidation);
        } else {
            // For ai_empty and manual_* statuses, force aiValidation to null
            // Use with() to override the random aiValidation from defaults()
            $documentFactory = $documentFactory->with([
                'aiValidation' => null,
            ]);
        }

        $document = $documentFactory->create();

        // For manual_* statuses, apply manual validation AFTER creation
        // This ensures the document has manual validation but no AI validation
        if (str_starts_with($validationStatus, 'manual_')) {
            $manualStatus = match ($validationStatus) {
                'manual_accept' => ManualValidationStatus::ACCEPTED,
                'manual_refuse' => ManualValidationStatus::REFUSED,
                default => throw new \InvalidArgumentException(\sprintf(
                    'Unknown manual validation status: %s',
                    $validationStatus
                )),
            };

            // Remove any manual validation that initialize() might have added (30% chance)
            // We do this by checking if manualStatus exists and clearing it before applying ours
            $reflection = new \ReflectionClass($document);
            $manualStatusProperty = $reflection->getProperty('manualStatus');
            $manualStatusProperty->setValue($document, null);
            $validatedByProperty = $reflection->getProperty('validatedBy');
            $validatedByProperty->setValue($document, null);
            $validatedAtProperty = $reflection->getProperty('validatedAt');
            $validatedAtProperty->setValue($document, null);

            // Now apply the intended manual validation
            $document->manuallyValidate($manualStatus, $validator);
        } else {
            // For ai_* statuses (including ai_empty), ensure NO manual validation exists
            // Remove any manual validation that initialize() might have added (30% chance)
            $reflection = new \ReflectionClass($document);
            $manualStatusProperty = $reflection->getProperty('manualStatus');
            $manualStatusProperty->setValue($document, null);
            $validatedByProperty = $reflection->getProperty('validatedBy');
            $validatedByProperty->setValue($document, null);
            $validatedAtProperty = $reflection->getProperty('validatedAt');
            $validatedAtProperty->setValue($document, null);
        }

        $documentGateway->save($document);

        return $document;
    }
}
