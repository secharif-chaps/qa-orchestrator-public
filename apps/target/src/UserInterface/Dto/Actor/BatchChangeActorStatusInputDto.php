<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Actor;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class BatchChangeActorStatusInputDto
{
    /**
     * @param array<int, array{id: string, sourceIds: list<string>, watchFileId?: string}> $actors Array of actors with their source IDs
     */
    public function __construct(
        #[Groups(['actor:write'])]
        #[Assert\NotBlank(message: 'Actors array is required')]
        #[Assert\All([
            new Assert\Collection([
                'id' => [
                    new Assert\NotBlank(message: 'Actor ID cannot be blank'),
                    new Assert\Type('string', message: 'Actor ID must be a string'),
                ],
                'watchFileId' => [
                    new Assert\Optional([
                        new Assert\NotBlank(message: 'WatchFile ID cannot be blank'),
                        new Assert\Type('string', message: 'WatchFile ID must be a string'),
                    ]),
                ],
                'sourceIds' => [
                    new Assert\Type('array', message: 'Source IDs must be an array'),
                    new Assert\All([new Assert\Type('string', message: 'Source ID must be a string')]),
                ],
            ]),
        ])]
        #[Assert\Count(min: 1, minMessage: 'At least one actor is required')]
        #[ApiProperty(
            description: 'Array of actors to update with their associated source IDs. Each actor object must contain the actor ID and an array of source IDs to enable for that actor.',
            openapiContext: [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['id', 'sourceIds'],
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'format' => 'uuid',
                            'description' => 'The unique identifier of the actor',
                        ],
                        'watchFileId' => [
                            'type' => 'string',
                            'format' => 'uuid',
                            'description' => 'The watch file ID (optional, for context)',
                        ],
                        'sourceIds' => [
                            'type' => 'array',
                            'description' => 'List of source IDs to enable for this actor',
                            'items' => [
                                'type' => 'string',
                                'format' => 'uuid',
                            ],
                        ],
                    ],
                ],
            ],
        )]
        public array $actors = [],
    ) {
    }
}
