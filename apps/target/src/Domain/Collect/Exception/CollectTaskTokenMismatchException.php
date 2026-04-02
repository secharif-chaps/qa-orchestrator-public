<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class CollectTaskTokenMismatchException extends CollectException
{
    public static function invalidSourceId(): self
    {
        return new self('Invalid collect task token: source_id does not match collect task.');
    }

    public static function invalidWatchFileId(): self
    {
        return new self('Invalid collect task token: watch_file_id does not match collect task.');
    }
}
