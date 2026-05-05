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
 * duplicate, then either dispatches `RunPostSavePipelineAction` on the bus
 * (default) or runs the post-save pipeline inline ({@see $sync}).
 *
 * Distinct from `CreateDocument*` which model the *initial creation* of a
 * Document from a URL or raw HTML payload (manual ingress only); ingestion
 * is the unified downstream step both manual and automated paths converge to.
 */
readonly class IngestDocumentAction
{
    /**
     * @param bool $sync when `true`, the handler runs the post-save pipeline
     *                   inline instead of dispatching it to the bus. Used by
     *                   the `document:create --sync` CLI flow where the
     *                   caller wants the full pipeline to complete before
     *                   the command returns (testing, debug, one-off
     *                   ingest). Production async flows leave it `false`.
     */
    public function __construct(
        public string $collectTaskId,
        public Document $document,
        public bool $sync = false,
    ) {
    }
}
