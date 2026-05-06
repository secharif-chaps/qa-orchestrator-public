<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Source;

use ApiPlatform\Metadata\ApiProperty;
use App\Domain\Source\SourceStatus;
use App\Infrastructure\Shared\Constraint\EnumConstraint;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class BatchChangeSourceStatusInputDto
{
    /**
     * @param array<int, array{id: string, watchFileId?: string}> $sources Array of sources
     */
    public function __construct(
        #[Groups(['source:write'])]
        #[Assert\NotNull(message: 'Sources array is required')]
        #[Assert\All([
            new Assert\Collection([
                'id' => [
                    new Assert\NotBlank(message: 'Source ID cannot be blank'),
                    new Assert\Type('string', message: 'Source ID must be a string'),
                ],
                'watchFileId' => [
                    new Assert\Optional([
                        new Assert\NotBlank(message: 'WatchFile ID cannot be blank'),
                        new Assert\Type('string', message: 'WatchFile ID must be a string'),
                    ]),
                ],
            ]),
        ])]
        #[ApiProperty(
            description: 'Array of sources to update. Each source object must contain the source ID.',
            openapiContext: [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['id'],
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'format' => 'uuid',
                            'description' => 'The unique identifier of the source',
                        ],
                        'watchFileId' => [
                            'type' => 'string',
                            'format' => 'uuid',
                            'description' => 'The watchfile ID (optional, for context)',
                        ],
                    ],
                ],
            ],
        )]
        public ?array $sources = null,
        #[Groups(['source:write'])]
        #[Assert\NotBlank(message: 'Status is required')]
        #[EnumConstraint(enumClass: SourceStatus::class)]
        #[ApiProperty(
            description: 'The new status to apply to all specified sources. Use "active" to enable monitoring or "inactive" to disable it.',
            openapiContext: [
                'type' => 'string',
                'enum' => ['active', 'inactive', 'auto_disabled'],
            ],
        )]
        public ?string $status = null,
    ) {
    }

    public function getStatus(): SourceStatus
    {
        if (null === $this->status) {
            throw new \RuntimeException('Status cannot be null.');
        }

        return SourceStatus::from($this->status);
    }
}
