<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class ApifyDatasetFetchException extends CollectException
{
    public static function forItems(string $datasetId, int $offset, string $reason): self
    {
        return new self(\sprintf(
            'Failed to fetch items from Apify dataset "%s" at offset %d: %s',
            $datasetId,
            $offset,
            $reason
        ));
    }
}
