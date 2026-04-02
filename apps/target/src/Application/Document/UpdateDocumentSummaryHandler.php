<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Exception\UpdateDocumentSummaryException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateDocumentSummaryHandler
{
    public function __construct(
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(UpdateDocumentSummaryAction $action): void
    {
        $this->logger?->info('UpdateDocumentSummaryHandler: Processing document summary', [
            'documentId' => $action->documentId,
            'hasSummary' => $action->summary?->hasContent() ?? false,
            'hasError' => null !== $action->summaryError,
            'summaryStatus' => $action->summaryStatus->value,
        ]);

        $summaryStatus = $action->summaryStatus;

        try {
            $document = $this->documentGateway->get($action->documentId);
        } catch (DocumentNotFoundException $e) {
            $this->logger?->warning('UpdateDocumentSummaryHandler: Document not found, skipping update', [
                'documentId' => $action->documentId,
                'error' => $e->getMessage(),
            ]);

            return;
        } catch (\Throwable $e) {
            $this->logger?->error('UpdateDocumentSummaryHandler: Unexpected error while retrieving document', [
                'documentId' => $action->documentId,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            throw new UpdateDocumentSummaryException(\sprintf(
                'Failed to retrieve document %s: %s',
                $action->documentId,
                $e->getMessage()
            ), 0, $e);
        }
        $document->updateSummary($action->summary, $summaryStatus);

        try {
            $this->documentGateway->save($document);
        } catch (\Throwable $e) {
            $this->logger?->error('UpdateDocumentSummaryHandler: Failed to save document', [
                'documentId' => $action->documentId,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            throw new UpdateDocumentSummaryException(\sprintf(
                'Failed to save document %s: %s',
                $action->documentId,
                $e->getMessage()
            ), 0, $e);
        }

        $this->logger?->info('UpdateDocumentSummaryHandler: Processing completed', [
            'documentId' => $action->documentId,
            'summary_status' => $summaryStatus->value,
        ]);
    }
}
