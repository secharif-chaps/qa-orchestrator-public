<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Source;

use Symfony\Component\Serializer\Annotation\Groups;

final readonly class SourceTypesDto
{
    /**
     * @param array<string, int> $types Array of source type values with their counts
     */
    public function __construct(
        #[Groups(['source:read'])]
        public array $types,
    ) {
    }
}
