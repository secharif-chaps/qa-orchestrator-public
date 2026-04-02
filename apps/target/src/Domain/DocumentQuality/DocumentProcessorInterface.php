<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

interface DocumentProcessorInterface
{
    public function process(ProcessingContext $context): ProcessingContext;

    public function supports(ProcessingContext $context): bool;
}
