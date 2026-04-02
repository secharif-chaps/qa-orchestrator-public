<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Infrastructure\WatchFile\EventActorsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/watch_files/{watchFileId}/timeline/{eventId}/actors',
            uriVariables: [
                'watchFileId' => new Link(fromClass: WatchFile::class, identifiers: ['id']),
                'eventId' => new Link(fromProperty: 'eventId'),
            ],
            openapi: new Operation(
                summary: 'Get actors for a specific timeline event',
                description: 'Retrieves the actors associated with a specific timeline event, including their current data and status (even if they have been soft deleted).',
                parameters: [
                    new Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000'
                    ),
                    new Parameter(
                        name: 'eventId',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the timeline event',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440001'
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['actor:read'],
                'jsonld_embed_context' => false,
                'skip_null_values' => true,
            ],
            name: 'get_event_actors',
            provider: EventActorsProvider::class,
        ),
    ],
)]
class EventActors
{
    /** @var array<string, mixed> */
    #[Groups(['actor:read'])]
    public array $event;

    /** @var WatchFileActor[] */
    #[Groups(['actor:read'])]
    public array $actors;

    #[Groups(['actor:read'])]
    public int $count;

    /**
     * @param array<string, mixed> $event
     * @param WatchFileActor[]     $actors
     */
    public function __construct(array $event, array $actors)
    {
        $this->event = $event;
        $this->actors = $actors;
        $this->count = \count($actors);
    }
}
