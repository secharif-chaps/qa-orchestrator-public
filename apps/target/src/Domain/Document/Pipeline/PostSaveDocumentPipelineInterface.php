<?php

declare(strict_types=1);

namespace App\Domain\Document\Pipeline;

/**
 * Marker interface for the post-save document pipeline (asynchronous,
 * runs after the document has been persisted via
 * {@see \App\Domain\Document\DocumentGatewayInterface::save()}).
 *
 * Lets `Application` handlers depend on `Domain` instead of the concrete
 * `Infrastructure` implementation while still distinguishing the two
 * pipeline phases at the type level.
 */
interface PostSaveDocumentPipelineInterface extends DocumentPipelineInterface
{
}
