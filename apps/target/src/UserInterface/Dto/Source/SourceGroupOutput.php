<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Source;

use Symfony\Component\Serializer\Annotation\Groups;

readonly class SourceGroupOutput
{
    /**
     * @param SourceGroup[]      $groups
     * @param array<string, int> $summary
     */
    public function __construct(
        #[Groups(['source_group:read'])]
        public array $groups,
        #[Groups(['source_group:read'])]
        public array $summary = [],
    ) {
    }
}
