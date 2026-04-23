<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class ApifyDatasetNotFoundException extends CollectException
{
    public static function withId(string $datasetId): self
    {
        return new self(\sprintf('Apify dataset "%s" not found or metadata unavailable', $datasetId));
    }
}
