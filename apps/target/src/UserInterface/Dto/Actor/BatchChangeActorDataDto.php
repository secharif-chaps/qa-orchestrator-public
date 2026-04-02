<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Actor;

/**
 * Represents individual actor data for batch operations.
 */
class BatchChangeActorDataDto
{
    /**
     * @param string[] $sourceIds
     */
    public function __construct(
        public string $id,
        public array $sourceIds = [],
        public ?string $watchFileId = null,
    ) {
    }
}
