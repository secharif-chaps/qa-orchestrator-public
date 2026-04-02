<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\EventSubscriber;

use App\Application\Document\UpdateDocumentSeenAction;
use App\Domain\Document\Event\DocumentManuallyValidatedEvent;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Exception\DocumentWithoutWatchFileException;
use App\Domain\User\UserNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

readonly class MarkDocumentAsSeenOnValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DocumentManuallyValidatedEvent::class => 'onDocumentManuallyValidated',
        ];
    }

    public function onDocumentManuallyValidated(DocumentManuallyValidatedEvent $event): void
    {
        $documentId = $event->document->getId();
        $userId = $event->validatedBy->getId();

        if (null === $userId) {
            $this->logger?->warning('Cannot mark document as seen: missing user ID', [
                'document_id' => $documentId,
            ]);

            return;
        }

        try {
            $markSeenAction = UpdateDocumentSeenAction::forCurrentUser(
                documentId: Uuid::fromString($documentId),
                currentUserId: Uuid::fromString($userId)
            );

            $this->messageBus->dispatch($markSeenAction);

            $this->logger?->debug('Document marked as seen after validation', [
                'document_id' => $documentId,
                'user_id' => $userId,
                'validation_status' => $event->validationStatus->value,
            ]);
        } catch (DocumentNotFoundException|UserNotFoundException|DocumentWithoutWatchFileException $e) {
            $this->logger?->warning('Failed to mark document as seen after validation', [
                'document_id' => $documentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
        }
    }
}
