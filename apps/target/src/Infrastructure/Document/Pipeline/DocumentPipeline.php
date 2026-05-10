<?php

declare(strict_types=1);

namespace App\Infrastructure\Document\Pipeline;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\DocumentPipelineInterface;
use App\Domain\Document\Pipeline\DocumentProcessorInterface;
use App\Domain\WatchFile\WatchFile;
use Psr\Log\LoggerInterface;

/**
 * Generic document pipeline implementation.
 *
 * The class is *not* tied to a specific phase: it orchestrates whatever
 * processors are injected. Two thin subclasses ({@see PreSaveDocumentPipeline}
 * and {@see PostSaveDocumentPipeline}) wire phase-specific tagged iterators
 * via `#[AutowireIterator]`, which keeps the DI configuration in PHP rather
 * than spreading it across `services.yaml`.
 */
readonly class DocumentPipeline implements DocumentPipelineInterface
{
    /**
     * @param iterable<DocumentProcessorInterface> $processors
     */
    public function __construct(
        private iterable $processors,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function process(
        Document $document,
        WatchFile $watchFile,
        ?string $collectTaskId = null,
        ?string $provider = null,
    ): DocumentPipelineContext {
        $context = new DocumentPipelineContext(
            document: $document,
            watchFile: $watchFile,
            collectTaskId: $collectTaskId,
            provider: $provider,
        );

        foreach ($this->processors as $processor) {
            if (!$processor->supports($context)) {
                $this->logger?->debug('Pipeline processor skipped', [
                    'processor' => $processor::class,
                    'document_id' => $document->getId(),
                ]);

                continue;
            }

            $startAt = microtime(true);
            $this->logger?->info('Pipeline "{processor}" processor started', [
                'processor' => $processor::class,
                'document_id' => $document->getId(),
                'signals_before' => array_keys($context->signals),
                'start_at' => $startAt,
            ]);

            $context = $processor->process($context);

            $this->logger?->info('Pipeline "{processor}" processor finished, took {duration} seconds', [
                'processor' => $processor::class,
                'document_id' => $document->getId(),
                'signals' => array_keys($context->signals),
                'duration' => microtime(true) - $startAt,
            ]);

            if ($context->isHalted) {
                $this->logger?->info('Pipeline halted by processor', [
                    'processor' => $processor::class,
                    'document_id' => $document->getId(),
                    'reason_fr' => $context->haltReason?->fr,
                    'reason_en' => $context->haltReason?->en,
                ]);

                break;
            }
        }

        return $context;
    }
}
