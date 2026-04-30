<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline\Processor\Enrichment;

use App\Domain\Document\CanonicalUrlExtractor;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PipelinePhase;
use App\Domain\Document\Pipeline\PreSaveDocumentProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Stage 0 of document deduplication: enrich the pipeline context with the
 * normalised canonical URL of the document under processing.
 *
 * Runs in the **ENRICHMENT** phase (priority 210, see {@see PipelinePhase})
 * during the pre-save pipeline so the canonical URL is available to:
 *
 * - the persistence layer that indexes the document on OpenSearch (the
 *   value lands on the indexed payload because pre-save processors mutate
 *   the same context the handler ultimately persists);
 * - downstream deduplication processors (Stage 1+) that compare candidate
 *   documents by their normalised canonical key.
 */
#[AsTaggedItem(priority: 210)]
readonly class CanonicalUrlEnrichmentProcessor implements PreSaveDocumentProcessorInterface
{
    public function __construct(
        private CanonicalUrlExtractor $extractor,
    ) {
    }

    public function process(DocumentPipelineContext $context): DocumentPipelineContext
    {
        $canonical = $this->extractor->extract(
            providerCanonicalUrl: null,
            rawHtml: $context->rawHtml,
            sourceUrl: $context->document->getUrl(),
        );

        if (null === $canonical) {
            return $context;
        }

        return $context->withCanonicalUrl($canonical);
    }

    public function supports(DocumentPipelineContext $context): bool
    {
        if ($context->isHalted) {
            return false;
        }

        // Skip if an upstream provider (or a previous run) already supplied
        // a canonical URL — re-running would just produce the same result.
        if (null !== $context->canonicalUrl) {
            return false;
        }

        // Need at least one input the extractor can chew on.
        return null !== $context->document->getUrl() || null !== $context->rawHtml;
    }
}
