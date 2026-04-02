<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\ValidationException;
use App\Domain\Document\HtmlMetadata;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
readonly class AddDocumentHandler
{
    public function __construct(
        private CollectTaskGatewayInterface $collectTaskGateway,
        private DocumentGatewayInterface $documentGateway,
        private ValidatorInterface $validator,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(AddDocumentAction $action): Document
    {
        // Validate the document
        $violations = $this->validator->validate($action->document);
        if (\count($violations) > 0) {
            throw ValidationException::validationFailed(
                'Document validation failed: ' . (string) $violations,
                new ValidationFailedException($action->document, $violations)
            );
        }

        // Get the collect task to extract watch file and source information
        $collectTask = $this->collectTaskGateway->get($action->collectTaskId);

        $document = $action->document;

        // Set the watch file and source information
        $document->capitalizeFrom($collectTask);

        // Check if document exists by providerId (for synchronization between raw_result and document_refined_result)
        $existingDocument = null;
        if (null !== $document->getProviderId()) {
            $existingDocument = $this->documentGateway->findByProviderId($document->getProviderId());
        }

        if (null !== $existingDocument) {
            $this->mergeDocumentData($existingDocument, $document);
            $document = $existingDocument;
        }

        // Save to OpenSearch
        $this->documentGateway->save($document);

        // Trigger AI validation after save
        if (null === $existingDocument || null === $document->getAiValidation()) {
            $this->messageBus->dispatch(
                new TriggerDocumentAiValidationAction(documentId: $document->getId()),
                [new DispatchAfterCurrentBusStamp()]
            );
        }

        return $document;
    }

    /**
     * Merge data from new document into existing document.
     * Refined data (from document_refined_result) takes priority over raw data.
     * Placeholder values ('Untitled Document') never overwrite real data.
     */
    private function mergeDocumentData(Document $existing, Document $new): void
    {
        $newTitle = $new->getTitle();
        if ('' !== $newTitle && HtmlMetadata::UNTITLED !== $newTitle && $newTitle !== $existing->getTitle()) {
            $existing->setTitle($newTitle);
        }

        $newExcerpt = $new->getExcerpt();
        $existingExcerpt = $existing->getExcerpt();
        if (
            '' !== $newExcerpt
            && $newExcerpt !== $existingExcerpt
            && (
                '' === $existingExcerpt
                || \strlen($newExcerpt) > \strlen($existingExcerpt)
            )
        ) {
            $existing->setExcerpt($newExcerpt);
        }

        if ('' !== $new->getContent() && $new->getContent() !== $existing->getContent()) {
            if (\strlen($new->getContent()) > \strlen($existing->getContent())) {
                $existing->setContent($new->getContent());
            }
        }

        if ($new->getType() !== $existing->getType()) {
            $existing->setType($new->getType());
        }

        if ($new->getDatePublish() !== $existing->getDatePublish()) {
            $existing->setDatePublish($new->getDatePublish());
        }

        if (null === $existing->getUrl() && null !== $new->getUrl()) {
            $existing->setUrl($new->getUrl());
        }

        if ($new->isCfcRestricted() !== $existing->isCfcRestricted()) {
            $existing->setCfcRestricted($new->isCfcRestricted());
        }

        $existing->setUpdatedAt(new \DateTimeImmutable());
    }
}
