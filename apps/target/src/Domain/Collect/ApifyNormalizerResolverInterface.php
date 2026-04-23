<?php

declare(strict_types=1);

namespace App\Domain\Collect;

interface ApifyNormalizerResolverInterface
{
    public function resolveFor(CollectTask $collectTask): ApifyDocumentNormalizerInterface;
}
