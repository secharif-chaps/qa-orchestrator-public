<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Actor;

use App\Domain\Actor\Actor;
use App\Domain\Source\Source;
use Symfony\Component\Serializer\Annotation\Groups;

class ChangeActorStatusOutputDto
{
    /**
     * @param Source[] $sources
     */
    public function __construct(
        #[Groups(['actor:read'])]
        public Actor $actor,
        #[Groups(['actor:read'])]
        /** @var Source[] */
        public array $sources,
    ) {
    }
}
