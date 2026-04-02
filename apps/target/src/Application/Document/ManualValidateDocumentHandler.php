<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentValidation;
use App\Domain\Document\DocumentValidationGatewayInterface;
use App\Domain\Document\Event\DocumentManuallyValidatedEvent;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\ManualValidationStatus;
use App\Domain\User\UserGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
readonly class ManualValidateDocumentHandler
{
    public function __construct(
        private DocumentGatewayInterface $documentGateway,
        private DocumentValidationGatewayInterface $documentValidationGateway,
        private UserGatewayInterface $userGateway,
        private EventDispatcherInterface $eventDispatcher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ManualValidateDocumentAction $action): void
    {
        try {
            $document = $this->documentGateway->get($action->documentId);
        } catch (DocumentNotFoundException $e) {
            $this->logger?->error('Document not found for manual validation', [
                'document_id' => $action->documentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $validatedBy = $this->userGateway->get($action->validatedByUserId);

        if (ManualValidationStatus::UNCERTAIN === $action->action) {
            $document->resetManualValidation();
        } else {
            $document->manuallyValidate($action->action, $validatedBy);
        }

        $this->documentGateway->save($document);

        $documentValidation = new DocumentValidation(
            Uuid::fromString($action->documentId),
            $validatedBy,
            $action->action,
        );
        $this->documentValidationGateway->save($documentValidation);

        $this->logger?->info('Document manual validation status changed', [
            'document_id' => $action->documentId,
            'action' => $action->action->value,
            'validated_by' => $validatedBy->getId(),
        ]);

        $this->eventDispatcher->dispatch(
            new DocumentManuallyValidatedEvent($document, $action->action, $validatedBy)
        );
    }
}
