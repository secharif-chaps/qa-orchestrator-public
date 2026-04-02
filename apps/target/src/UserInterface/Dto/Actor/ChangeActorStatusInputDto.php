<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Actor;

use App\Domain\Actor\ActorStatus;
use App\Infrastructure\Shared\Constraint\EnumConstraint;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ChangeActorStatusInputDto
{
    /**
     * @param string[] $sourceIds
     */
    public function __construct(
        #[Groups(['actor:write'])]
        #[Assert\NotNull(message: 'New status is required')]
        #[EnumConstraint(enumClass: ActorStatus::class)]
        public ?string $status = null,

        #[Groups(['actor:write'])]
        #[Assert\All([
            new Assert\NotBlank(message: 'Source ID cannot be blank'),
            new Assert\Type('string', message: 'Source ID must be a string'),
        ])]
        public array $sourceIds = [],
    ) {
    }

    public function getStatus(): ActorStatus
    {
        return ActorStatus::from($this->status ?? '');
    }
}
