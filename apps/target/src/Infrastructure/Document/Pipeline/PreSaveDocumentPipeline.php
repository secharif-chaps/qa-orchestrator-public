<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline;

use App\Domain\Document\Pipeline\DocumentProcessorInterface;
use App\Domain\Document\Pipeline\PreSaveDocumentPipelineInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The pre-save instance of the document pipeline. Picks every processor
 * tagged `app.document_pre_save_processor` (i.e. every concrete
 * `PreSaveDocumentProcessorInterface` implementation) via Symfony's
 * tagged-iterator wiring, sorted by `#[AsTaggedItem(priority: …)]` in
 * descending order.
 *
 * Application handlers depend on {@see PreSaveDocumentPipelineInterface}
 * (Domain) — the autowiring resolves it to this class because it is the
 * only implementation of the interface.
 */
readonly class PreSaveDocumentPipeline extends DocumentPipeline implements PreSaveDocumentPipelineInterface
{
    /**
     * @param iterable<DocumentProcessorInterface> $processors
     */
    public function __construct(
        #[AutowireIterator('app.document_pre_save_processor')]
        iterable $processors,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($processors, $logger);
    }
}
