<?php

declare(strict_types=1);

namespace App\Domain\Document\Pipeline;

/**
 * Marker interface for processors that run **after** the document has
 * been persisted to OpenSearch, in an asynchronous worker driven by
 * {@see \App\Application\Document\Pipeline\RunPostSavePipelineAction}.
 *
 * Post-save processors typically compute quality {@see \App\Domain\DocumentQuality\Signal}s,
 * detect fuzzy duplicates, or trigger heavier analysis that the
 * ingest critical path should not bear.
 */
interface PostSaveDocumentProcessorInterface extends DocumentProcessorInterface
{
}
