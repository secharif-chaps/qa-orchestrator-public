<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\Exception\InvalidSummaryStateException;
use App\Domain\Document\Summary;
use App\Domain\Document\SummaryStatus;

readonly class UpdateDocumentSummaryAction
{
    public SummaryStatus $summaryStatus;

    public function __construct(
        public string $documentId,
        public ?Summary $summary = null,
        public ?string $summaryError = null,
    ) {
        if (null !== $this->summaryError && $this->summary?->hasContent()) {
            throw new InvalidSummaryStateException(\sprintf(
                'Invalid summary state for document %s: both summary and error provided',
                $this->documentId
            ));
        }

        $this->summaryStatus = $this->resolveSummaryStatus();
    }

    private function resolveSummaryStatus(): SummaryStatus
    {
        if (null !== $this->summaryError) {
            return SummaryStatus::FAILED;
        }

        if ($this->summary?->hasContent()) {
            return SummaryStatus::COMPLETED;
        }

        return SummaryStatus::PENDING;
    }
}
