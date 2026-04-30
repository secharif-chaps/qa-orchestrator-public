<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline;

use App\Domain\Document\Pipeline\DocumentProcessorInterface;
use App\Domain\Document\Pipeline\PostSaveDocumentPipelineInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The post-save instance of the document pipeline. Picks every processor
 * tagged `app.document_post_save_processor` (i.e. every concrete
 * `PostSaveDocumentProcessorInterface` implementation) via Symfony's
 * tagged-iterator wiring, sorted by `#[AsTaggedItem(priority: …)]` in
 * descending order.
 *
 * Application handlers depend on {@see PostSaveDocumentPipelineInterface}
 * (Domain) — the autowiring resolves it to this class because it is the
 * only implementation of the interface.
 */
readonly class PostSaveDocumentPipeline extends DocumentPipeline implements PostSaveDocumentPipelineInterface
{
    /**
     * @param iterable<DocumentProcessorInterface> $processors
     */
    public function __construct(
        #[AutowireIterator('app.document_post_save_processor')]
        iterable $processors,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($processors, $logger);
    }
}
