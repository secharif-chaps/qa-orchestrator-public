<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline\Processor\Deduplication;

use App\Domain\Document\CanonicalUrlExtractor;
use App\Domain\Document\Deduplication\DuplicateAttempt;
use App\Domain\Document\Deduplication\DuplicateDetectorInterface;
use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Deduplication\DuplicateResult;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Fingerprinting\DocumentFingerprintComputer;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PipelinePhase;
use App\Domain\Document\Pipeline\PreSaveDocumentProcessorInterface;
use App\Domain\Shared\TranslatedText;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Pre-save processor that runs the full four-stage dedup pipeline
 * (ADR-2026-006, TAR-1146): compute the candidate's {@see Fingerprint},
 * probe the index via {@see DuplicateDetectorInterface::detect()}, and:
 *
 * - UNIQUE → pose the fingerprint on the document so the save layer
 *            indexes it; pipeline continues normally.
 * - match  → record a {@see DuplicateAttempt} on the matched original
 *            (axe A trace), persist the original and halt the pipeline
 *            with `duplicateOf` set so the surrounding handler skips
 *            the save (axe B reported out of MVP scope).
 *
 * Phase {@see PipelinePhase::DEDUPLICATION} (priority 150). Runs after
 * the canonical-URL enrichment (200-299) so stage 0 has its input.
 */
#[AsTaggedItem(priority: 150)]
class DuplicateDetectionPreSaveProcessor implements PreSaveDocumentProcessorInterface
{
    public function __construct(
        private readonly DocumentFingerprintComputer $fingerprintComputer,
        private readonly DuplicateDetectorInterface $duplicateDetector,
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly CanonicalUrlExtractor $canonicalUrlExtractor,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $document = $context->document;
        // Empty content → no shingles, no useful fingerprint. Skip the
        // computation but still let the detector run stage 0 against the
        // canonical URL (a doc with a canonical tag but no body is a
        // legit deduplication input). The title is forwarded so stage 4
        // (title fallback) has a signature to probe with.
        $fingerprint = '' !== $document->getContent()
            ? $this->fingerprintComputer->compute($document->getContent(), $document->getTitle())
            : null;

        // Prefer the canonical URL already set on the document (typically
        // by an upstream collector that read it from the page) over the
        // one re-extracted by the enrichment processor — the upstream
        // value is canonical-tag-derived, the enrichment fallback is
        // URL-only (less authoritative). Pass it through the extractor's
        // canonicaliser so the lookup form matches what the indexed
        // documents store (lowercased, IDN-encoded, no tracking params,
        // no trailing slash on non-root paths).
        $rawCanonicalUrl = $document->getCanonicalUrl() ?? $context->canonicalUrl;
        $canonicalUrl = null !== $rawCanonicalUrl
            ? $this->canonicalUrlExtractor->canonicalize($rawCanonicalUrl)
            : null;

        $result = $this->duplicateDetector->detect(
            fingerprint: $fingerprint,
            canonicalUrl: $canonicalUrl,
            excludeDocumentId: $document->getId(),
        );

        if (DuplicateOutcome::UNIQUE === $result->outcome) {
            // Pose the fingerprint only on the UNIQUE path so it's indexed
            // with the document. Match paths reject the candidate; mutating
            // it would leak a stale fingerprint to any caller that
            // inspects the doc after a halt.
            if (null !== $fingerprint) {
                $document->setFingerprint($fingerprint);
            }

            return $context;
        }

        // DuplicateResult invariants guarantee these on a match outcome —
        // throw rather than `assert` so the contract holds in production
        // (asserts are disabled by default in prod).
        $originalId = $result->originalDocumentId;
        $stage = $result->stage;
        if (null === $originalId || null === $stage) {
            throw new \LogicException(
                'DuplicateResult with non-UNIQUE outcome must carry originalDocumentId and stage.',
            );
        }

        $this->recordAttemptOnOriginal($context, $result, $originalId, $stage);

        return $context
            ->withDuplicateOf($originalId)
            ->withHalt(new TranslatedText(
                fr: \sprintf(
                    'Document détecté comme doublon (%s) du document %s.',
                    $result->outcome->value,
                    $originalId
                ),
                en: \sprintf(
                    'Document detected as duplicate (%s) of document %s.',
                    $result->outcome->value,
                    $originalId
                ),
            ));
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        if ($context->isHalted) {
            return false;
        }

        // Need at least one input the detector can probe with: a body
        // (stages 1-3 via fingerprint) or a canonical URL (stage 0).
        // Skipping when both are absent avoids producing an all-zero
        // fingerprint that would collide with every other empty doc.
        $hasContent = '' !== $context->document->getContent();
        $hasCanonicalUrl = null !== $context->document->getCanonicalUrl()
            || null !== $context->canonicalUrl;

        return $hasContent || $hasCanonicalUrl;
    }

    /**
     * Persist a {@see DuplicateAttempt} on the matched original.
     *
     * **Audit-trace gaps are reported at `error` level** (not `warning`):
     * a halted dedup match without a recorded attempt is silent data loss
     * — operations dashboards filter on `error` and won't surface a
     * `warning` lost in noise. The pipeline still halts on these paths
     * (the load-bearing decision is the verdict, not the trace), but the
     * log entry must be loud enough to alert.
     */
    private function recordAttemptOnOriginal(
        DocumentPipelineContext $context,
        DuplicateResult $result,
        string $originalId,
        DuplicateMatchStage $stage,
    ): void {
        if (null === $context->collectTaskId || null === $context->provider) {
            $this->logger?->error(
                'DuplicateDetectionPreSaveProcessor: audit trace lost — missing collect-task metadata, recordDuplicate skipped',
                [
                    'document_id' => $context->document->getId(),
                    'original_id' => $originalId,
                    'has_collect_task_id' => null !== $context->collectTaskId,
                    'has_provider' => null !== $context->provider,
                    'outcome' => $result->outcome->value,
                    'stage' => $stage->value,
                ],
            );

            return;
        }

        // Raw-HTML pastes legitimately have no URL (CLI `--html-file`, API
        // `html` payload). Persist the audit trail anyway — the URL field
        // on the persisted DuplicateAttempt is nullable for exactly this
        // case, and recordDuplicate() handles null-URL entries distinctly
        // (no overwrite, FIFO cap takes care of growth).
        $url = $context->document->getUrl();

        try {
            $original = $this->documentGateway->get($originalId);
        } catch (\Throwable $e) {
            // Original disappeared between detection and recording. Halt
            // the pipeline anyway — recording is best-effort, the verdict
            // is the load-bearing decision.
            $this->logger?->error(
                'DuplicateDetectionPreSaveProcessor: audit trace lost — matched original could not be loaded',
                [
                    'original_id' => $originalId,
                    'document_id' => $context->document->getId(),
                    'outcome' => $result->outcome->value,
                    'stage' => $stage->value,
                    'error' => $e->getMessage(),
                ],
            );

            return;
        }

        $original->recordDuplicate(new DuplicateAttempt(
            url: $url,
            watchFileId: $context->watchFile->getId(),
            collectTaskId: $context->collectTaskId,
            sourceId: $context->document->getSource()?->getId(),
            provider: $context->provider,
            collectedAt: $context->document->getDateCollect(),
            outcome: $result->outcome,
            matchStage: $stage,
            similarity: $result->similarity,
        ));

        // TODO(TAR-1147+): switch to a partial OpenSearch `_update` with a
        // painless script appending to `duplicates` (see
        // `DocumentOpenSearchGateway::markEventsAsExtracted` for the
        // pattern). The current full reindex risks lost-update races on
        // hot articles (e.g. AFP wires reposted by 100 outlets/min) —
        // two concurrent matches read the same `duplicates` array, each
        // appends one attempt, last writer wins.
        $this->documentGateway->save($original);
    }
}
