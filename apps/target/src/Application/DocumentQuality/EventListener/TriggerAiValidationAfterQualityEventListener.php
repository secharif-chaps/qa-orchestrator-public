<?php

declare(strict_types=1);

namespace App\Application\DocumentQuality\EventListener;

use App\Application\Document\TriggerDocumentAiValidationAction;
use App\Application\DocumentQuality\Event\DocumentQualityProcessedEvent;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\DocumentQuality\QualityDecision;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsEventListener(event: DocumentQualityProcessedEvent::class)]
readonly class TriggerAiValidationAfterQualityEventListener
{
    public function __construct(
        private DocumentGatewayInterface $documentGateway,
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(DocumentQualityProcessedEvent $event): void
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

        $this->logger?->info('Triggering AI validation after quality scoring', [
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
