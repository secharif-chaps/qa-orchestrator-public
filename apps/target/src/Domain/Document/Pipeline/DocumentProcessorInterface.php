<?php

declare(strict_types=1);

namespace App\Domain\Document\Pipeline;

/**
 * A single step of a document pipeline.
 *
 * Concrete processors do not implement this interface directly: they
 * implement either {@see PreSaveDocumentProcessorInterface} or
 * {@see PostSaveDocumentProcessorInterface}, which are tagged separately
 * in the dependency-injection container so each pipeline can pick its
 * own subset of processors.
 */
interface DocumentProcessorInterface
{
    public function process(DocumentPipelineContext $context): DocumentPipelineContext;

    public function supports(DocumentPipelineContext $context): bool;
}
