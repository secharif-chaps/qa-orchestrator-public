<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Source;

use ApiPlatform\Metadata\ApiProperty;
use App\Domain\Source\SourceStatus;
use App\Infrastructure\Shared\Constraint\EnumConstraint;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

readonly class ChangeSourceStatusDto
{
    public function __construct(
        #[Groups(['source:write'])]
        #[Assert\NotBlank]
        #[EnumConstraint(enumClass: SourceStatus::class)]
        #[ApiProperty(
            description: 'The new status for the source. Use "active" to enable monitoring, "inactive" to disable it manually, or "auto_disabled" for system-disabled sources.',
            openapiContext: [
                'type' => 'string',
                'enum' => ['active', 'inactive', 'auto_disabled'],
            ],
        )]
        public string $status,
    ) {
    }

    public function getStatus(): SourceStatus
    {
        return SourceStatus::from($this->status);
    }
}
