<?php

declare(strict_types=1);

namespace App\Application\Document\Pipeline;

use App\Application\Document\TriggerDocumentAiValidationAction;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\DocumentQuality\QualityDecision;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Reacts to {@see PostSavePipelineCompletedEvent} to trigger AI validation
 * for documents whose quality decision is acceptable (i.e. not `LOW_QUALITY`
 * and not `REJECTED`). Skips silently when the document already carries an
 * AI validation result or when the pipeline halted with a rejection.
 */
#[AsEventListener(event: PostSavePipelineCompletedEvent::class)]
readonly class TriggerAiValidationAfterPostSavePipelineEventListener
{
    public function __construct(
        private DocumentGatewayInterface $documentGateway,
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(PostSavePipelineCompletedEvent $event): void
    {
        if (QualityDecision::LOW_QUALITY->value === $event->decision
            || QualityDecision::REJECTED->value === $event->decision) {
            $this->logger?->info('Skipping AI validation for low-quality/rejected document', [
                'document_id' => $event->documentId,
                'decision' => $event->decision,
            ]);

            return;
        }

        $document = $this->documentGateway->get($event->documentId);

        if (null !== $document->getAiValidation()) {
            return;
        }

        $this->logger?->info('Triggering AI validation after post-save pipeline', [
            'document_id' => $event->documentId,
            'decision' => $event->decision,
            'overall_score' => $event->overallScore,
        ]);

        $this->messageBus->dispatch(
            new TriggerDocumentAiValidationAction(documentId: $event->documentId),
            [new DispatchAfterCurrentBusStamp()],
        );
    }
}
