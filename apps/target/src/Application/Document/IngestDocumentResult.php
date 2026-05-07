<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;

/**
 * Outcome of an {@see IngestDocumentAction} dispatch — returned by
 * {@see IngestDocumentHandler} so callers that observe the result via
 * `HandledStamp` (typically the `document:create --sync` CLI flow) can
 * display the full pipeline verdict.
 *
 * - `$document` is always the post-pipeline view of the document. On
 *   the duplicate path it is the candidate that was rejected; on the
 *   unique path it is the persisted (and post-save reloaded) entity.
 * - `$preSaveContext` carries every {@see Signal} the pre-save pipeline
 *   collected, plus the dedup verdict (`duplicateOf`, `isHalted`,
 *   `haltReason`). `null` only when the pipeline was skipped (legacy
 *   pre-save path: document arrived without a watch_file).
 *
 * Provider-id merge events (raw vs refined Apify variants colliding on the
 * same providerId) are no longer surfaced through this DTO — the handler
 * emits a structured log line instead, which the CLI's `ConsoleLogger`
 * renders inline.
 *
 * Bus callers that do not consume the return value are unaffected — the
 * messenger envelope discards it.
 */
readonly class IngestDocumentResult
{
    public function __construct(
        public Document $document,
        public ?DocumentPipelineContext $preSaveContext = null,
    ) {
    }

    public function isDuplicate(): bool
    {
        return null !== $this->preSaveContext?->duplicateOf;
    }

    public function duplicateOf(): ?string
    {
        return $this->preSaveContext?->duplicateOf;
    }
}
