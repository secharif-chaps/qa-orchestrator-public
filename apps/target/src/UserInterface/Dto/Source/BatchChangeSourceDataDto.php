<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Source;

/**
 * Represents individual source data for batch operations.
 */
class BatchChangeSourceDataDto
{
    public function __construct(
        public string $id,
        public ?string $watchFileId = null,
    ) {
    }
}
