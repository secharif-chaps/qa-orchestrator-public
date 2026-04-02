<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
class EnrichDocumentWithAiValidationHandler
{
    public function __construct(
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(EnrichDocumentWithAiValidationAction $action): void
    {
        if (AiValidationStatus::FAILED === $action->aiValidationStatus) {
            $this->handleValidationError($action);
        } else {
            $this->handleValidationSuccess($action);
        }
    }

    private function handleValidationSuccess(EnrichDocumentWithAiValidationAction $action): void
    {
        Assert::notNull($action->aiValidation, 'aiValidation must not be null in success case');

        try {
            $this->logger?->info('Processing document AI validation enrichment', [
                'document_id' => $action->documentId,
                'validation_status' => $action->aiValidation->status->value,
                'confidence_score' => $action->aiValidation->confidenceScore,
            ]);

            $document = $this->documentGateway->get($action->documentId);

            $document->updateAiValidation($action->aiValidation);

            $this->documentGateway->save($document);

            $this->logger?->info('Document AI validation enrichment completed successfully', [
                'document_id' => $action->documentId,
            ]);

            if (AiValidationStatus::VALIDATED === $action->aiValidation->status) {
                $this->messageBus->dispatch(new TriggerEventExtractionAction(documentId: $document->getId()));

                // Trigger summary generation (checks will be performed in the handler)
                $this->logger?->info('Triggering summary generation for AI validated document', [
                    'document_id' => $document->getId(),
                ]);

                $this->messageBus->dispatch(new TriggerDocumentSummaryAction(documentId: $document->getId()));
            }
        } catch (DocumentNotFoundException $e) {
            $this->logger?->warning('Document not found for AI validation enrichment', [
                'document_id' => $action->documentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to enrich document with AI validation', [
                'document_id' => $action->documentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function handleValidationError(EnrichDocumentWithAiValidationAction $action): void
    {
        try {
            $this->logger?->info('Processing document AI validation error', [
                'document_id' => $action->documentId,
                'validation_error' => $action->validationError,
            ]);

            $document = $this->documentGateway->get($action->documentId);

            $aiValidation = new AIValidation(
                status: AiValidationStatus::FAILED,
                confidenceScore: 0,
                validationReason: null,
                processedAt: new \DateTimeImmutable(),
                referenceSubject: null
            );

            $document->updateAiValidation($aiValidation);

            $this->documentGateway->save($document);

            $this->logger?->info('Document AI validation error processed successfully', [
                'document_id' => $action->documentId,
                'status' => AiValidationStatus::FAILED->value,
                'confidence_score' => 0,
            ]);
        } catch (DocumentNotFoundException $e) {
            $this->logger?->warning('Document not found for AI validation error processing', [
                'document_id' => $action->documentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to process document AI validation error', [
                'document_id' => $action->documentId,
                'validation_error' => $action->validationError,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
