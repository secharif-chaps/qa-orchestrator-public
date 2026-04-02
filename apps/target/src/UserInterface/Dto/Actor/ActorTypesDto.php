<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Actor;

use Symfony\Component\Serializer\Annotation\Groups;

final readonly class ActorTypesDto
{
    /**
     * @param array<int, array{type: string, count: int}> $types
     */
    public function __construct(
        #[Groups(['actor:read'])]
        public array $types,
    ) {
    }
}
