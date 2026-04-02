<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\Agent\ValidateDocumentAITriggerAgent;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Exception\MissingReferenceSubjectException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler]
readonly class TriggerDocumentAiValidationHandler
{
    public function __construct(
        private DocumentGatewayInterface $documentGateway,
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(TriggerDocumentAiValidationAction $action): void
    {
        try {
            $document = $this->documentGateway->get($action->documentId);
        } catch (DocumentNotFoundException $e) {
            $this->logger?->error('Document not found for AI validation trigger', [
                'document_id' => $action->documentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Check if AI validation is already done or in progress
        $aiValidation = $document->getAiValidation();
        if (null !== $aiValidation && AiValidationStatus::FAILED !== $aiValidation->status) {
            $this->logger?->info('Skipping AI validation trigger - AI validation already exists', [
                'document_id' => $action->documentId,
                'ai_validation_status' => $aiValidation->status->value,
            ]);

            return;
        }

        // Check if manual validation is already done
        $manualStatus = $document->getManualStatus();
        if (null !== $manualStatus) {
            $this->logger?->info('Skipping AI validation trigger - document is already manually validated', [
                'document_id' => $action->documentId,
                'manual_status' => $manualStatus->value,
            ]);

            return;
        }

        // Get reference subject from watch file
        $watchFile = $document->getWatchFile();
        $referenceSubject = $watchFile?->getReferenceSubject();

        // Prefer LLM-optimized version, fallback to human-readable English version
        $referenceSubjectLlm = $watchFile?->getReferenceSubjectLlm();
        $referenceSubjectForValidation = $referenceSubjectLlm ?? $referenceSubject?->en;

        // Validate reference subject exists and is not empty
        if (null === $referenceSubjectForValidation || '' === trim($referenceSubjectForValidation)) {
            $this->logger?->error('Cannot trigger AI validation - missing reference subject', [
                'document_id' => $action->documentId,
                'watch_file_id' => $watchFile?->getId(),
            ]);

            throw MissingReferenceSubjectException::forDocumentId($action->documentId);
        }

        // Set AI validation status to PENDING
        $pendingValidation = new AIValidation(
            status: AiValidationStatus::PENDING,
            confidenceScore: 0,
            validationReason: null,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: $referenceSubjectForValidation
        );

        $document->updateAiValidation($pendingValidation);
        $this->documentGateway->save($document);

        $this->logger?->info('Document marked as AI validation pending', [
            'document_id' => $action->documentId,
        ]);

        // Dispatch the AI validation trigger agent
        $this->messageBus->dispatch(
            new ValidateDocumentAITriggerAgent([
                'id' => $document->getId(),
                'content' => $document->getContent(),
                'referenceSubject' => $referenceSubjectForValidation,
            ]),
            [new DispatchAfterCurrentBusStamp()],
        );

        $this->logger?->info('AI validation trigger agent dispatched', [
            'document_id' => $action->documentId,
        ]);
    }
}
