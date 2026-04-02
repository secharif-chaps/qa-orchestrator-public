<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentValidation;
use App\Domain\Document\DocumentValidationGatewayInterface;
use App\Domain\Document\Event\DocumentManuallyValidatedEvent;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\User\UserGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Document\BatchManualValidateDocumentOutputDto;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
readonly class BatchManualValidateDocumentHandler
{
    public function __construct(
        private DocumentGatewayInterface $documentGateway,
        private DocumentValidationGatewayInterface $documentValidationGateway,
        private UserGatewayInterface $userGateway,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(BatchManualValidateDocumentAction $action): BatchManualValidateDocumentOutputDto
    {
        $validatedBy = $this->userGateway->get($action->validatedByUserId);
        $validatedAt = new \DateTimeImmutable();

        $results = [];
        $validatedCount = 0;
        $failedCount = 0;
        /** @var array<int, Document> $documentsToSave */
        $documentsToSave = [];
        /** @var array<int, DocumentValidation> $validationsToSave */
        $validationsToSave = [];

        $this->logger?->info('Starting batch manual validation', [
            'document_count' => \count($action->documentIds),
            'action' => $action->action->value,
            'user_id' => $validatedBy->getId(),
        ]);

        /*
         * Documents and validations are accumulated during processing and saved using bulk operations:
         * 1. OPENSEARCH: saveBulk() uses OpenSearch bulk API (1 HTTP request instead of N).
         * 2. DATABASE: saveBulk() batches all persist() calls with a single flush() at the end.
         * Input is limited to 50 documents max for synchronous processing.
         */

        foreach ($action->documentIds as $documentId) {
            try {
                $document = $this->documentGateway->get($documentId);
            } catch (DocumentNotFoundException) {
                $this->logger?->warning('Document not found during batch validation', [
                    'document_id' => $documentId,
                ]);

                ++$failedCount;
                $results[] = [
                    'document_id' => $documentId,
                    'status' => 'error',
                    'reason' => 'Document not found',
                ];
                continue;
            }

            if (!$this->security->isGrantedForUser($validatedBy, WatchFileVoter::EDIT, $document)) {
                $this->logger?->warning('Insufficient permissions during batch validation', [
                    'document_id' => $documentId,
                    'user_id' => $validatedBy->getId(),
                ]);

                ++$failedCount;
                $results[] = [
                    'document_id' => $documentId,
                    'status' => 'error',
                    'reason' => 'Insufficient permissions',
                ];
                continue;
            }

            try {
                $document->manuallyValidate($action->action, $validatedBy);
                $documentsToSave[] = $document;

                $documentValidation = new DocumentValidation(Uuid::fromString(
                    $documentId
                ), $validatedBy, $action->action);
                $validationsToSave[] = $documentValidation;

                $this->logger?->info('Document validated in batch', [
                    'document_id' => $documentId,
                    'action' => $action->action->value,
                ]);

                $this->eventDispatcher->dispatch(
                    new DocumentManuallyValidatedEvent($document, $action->action, $validatedBy)
                );

                ++$validatedCount;
                $results[] = [
                    'document_id' => $documentId,
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                $this->logger?->error('Failed to validate document in batch', [
                    'document_id' => $documentId,
                    'error' => $e->getMessage(),
                ]);

                ++$failedCount;
                $results[] = [
                    'document_id' => $documentId,
                    'status' => 'error',
                    'reason' => 'Validation failed: ' . $e->getMessage(),
                ];
            }
        }

        // Bulk save documents (OpenSearch bulk indexing - single request for all documents)
        if (!empty($documentsToSave)) {
            $this->documentGateway->saveBulk($documentsToSave);
        }

        // Bulk save validations (database batch persist + single flush)
        if (!empty($validationsToSave)) {
            $this->documentValidationGateway->saveBulk($validationsToSave);
        }

        $this->logger?->info('Batch manual validation completed', [
            'validated_count' => $validatedCount,
            'failed_count' => $failedCount,
            'total' => \count($action->documentIds),
        ]);

        return new BatchManualValidateDocumentOutputDto(
            success: true,
            validated_count: $validatedCount,
            failed_count: $failedCount,
            manual_status: $action->action->value,
            validated_by: $validatedBy->getDisplayName(),
            validated_at: $validatedAt,
            results: $results,
        );
    }
}
