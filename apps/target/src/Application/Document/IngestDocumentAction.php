<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\Document;

/**
 * Bus message dispatched whenever a fully-built {@see Document} arrives at
 * the system boundary — from a collect provider (Apify, Bakus), the manual
 * API endpoint, or the `document:create` CLI command.
 *
 * The receiving {@see IngestDocumentHandler} runs the pre-save pipeline
 * (enrichment + exact-match dedup), persists the document if it is not a
 * duplicate, then dispatches `RunPostSavePipelineAction` for the
 * asynchronous post-save pipeline.
 *
 * Distinct from `CreateDocument*` which model the *initial creation* of a
 * Document from a URL or raw HTML payload (manual ingress only); ingestion
 * is the unified downstream step both manual and automated paths converge to.
 */
readonly class IngestDocumentAction
{
    public function __construct(
        public string $collectTaskId,
        public Document $document,
    ) {
    }
}
