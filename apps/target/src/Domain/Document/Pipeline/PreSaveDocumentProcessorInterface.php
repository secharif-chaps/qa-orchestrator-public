<?php

declare(strict_types=1);

namespace App\Domain\Document\Pipeline;

/**
 * Marker interface for processors that run **before** the document is
 * persisted to OpenSearch. Pre-save processors typically:
 *
 * - enrich the document in place (e.g. resolve a canonical URL,
 *   compute fingerprints) so the value lands in the indexed document;
 * - detect exact duplicates and short-circuit the save by setting
 *   {@see DocumentPipelineContext::$duplicateOf}.
 *
 * Pre-save processors must remain fast and synchronous because they
 * are executed inside the ingest handler before persistence.
 */
interface PreSaveDocumentProcessorInterface extends DocumentProcessorInterface
{
}
