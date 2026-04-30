<?php

declare(strict_types=1);

namespace App\Domain\Document\Pipeline;

/**
 * Marker interface for the pre-save document pipeline (synchronous,
 * runs before {@see \App\Domain\Document\DocumentGatewayInterface::save()}).
 *
 * Lets `Application` handlers depend on `Domain` instead of the concrete
 * `Infrastructure` implementation while still distinguishing the two
 * pipeline phases at the type level.
 */
interface PreSaveDocumentPipelineInterface extends DocumentPipelineInterface
{
}
