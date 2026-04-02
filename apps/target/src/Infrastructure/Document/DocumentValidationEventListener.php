<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use App\Application\Document\TriggerDocumentSummaryAction;
use App\Application\Document\TriggerEventExtractionAction;
use App\Domain\Document\Event\DocumentManuallyValidatedEvent;
use App\Domain\Document\ManualValidationStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class DocumentValidationEventListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[AsEventListener]
    public function onDocumentManuallyValidated(DocumentManuallyValidatedEvent $event): void
    {
        if (ManualValidationStatus::REFUSED === $event->validationStatus) {
            return;
        }

        $document = $event->document;

        // Trigger event extraction for accepted document
        $this->logger?->info('Triggering event extraction for manually accepted document', [
            'document_id' => $document->getId(),
            'validated_by' => $event->validatedBy->getId(),
        ]);

        $this->messageBus->dispatch(new TriggerEventExtractionAction(documentId: $document->getId()));

        // Trigger summary generation (checks will be performed in the handler)
        $this->logger?->info('Triggering summary generation for accepted document', [
            'document_id' => $document->getId(),
            'validated_by' => $event->validatedBy->getId(),
        ]);

        $this->messageBus->dispatch(new TriggerDocumentSummaryAction(documentId: $document->getId()));
    }
}
