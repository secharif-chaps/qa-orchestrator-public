<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class CollectTaskTokenClaimsMissingException extends CollectException
{
    public static function create(): self
    {
        return new self(
            'Invalid collect task token: Missing required claims (source_id, watch_file_id, collect_task_id).'
        );
    }
}
