<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorType;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\DocumentQuality\QualityConfig;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantAwareInterface;
use App\Domain\Shared\CreatedByInterface;
use App\Domain\Shared\CreatedByTrait;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Infrastructure\WatchFile\ActorTypesProvider;
use App\Infrastructure\WatchFile\ApiFilter\IncludeArchivedFilter;
use App\Infrastructure\WatchFile\ApiFilter\OnlyFavoritesFilter;
use App\Infrastructure\WatchFile\ApiFilter\UserAccessibleWatchFileFilter;
use App\Infrastructure\WatchFile\ApiFilter\WatchFileOrderFilter;
use App\Infrastructure\WatchFile\BatchChangeActorStatusProcessor;
use App\Infrastructure\WatchFile\BatchChangeSourceStatusProcessor;
use App\Infrastructure\WatchFile\ChangeActorSourcesStatusProcessor;
use App\Infrastructure\WatchFile\ChangeWatchFileStatusProcessor;
use App\Infrastructure\WatchFile\EventSourcesProvider;
use App\Infrastructure\WatchFile\FavoriteWatchFileProcessor;
use App\Infrastructure\WatchFile\LastWatchFileConversationProvider;
use App\Infrastructure\WatchFile\SourceTypesProvider;
use App\Infrastructure\WatchFile\UpdateWatchFileProcessor;
use App\Infrastructure\WatchFile\WatchFileCollectionProvider;
use App\Infrastructure\WatchFile\WatchFileConversationProcessor;
use App\Infrastructure\WatchFile\WatchFileProcessor;
use App\Infrastructure\WatchFile\WatchFileProvider;
use App\Infrastructure\WatchFile\WatchFileUserProcessor;
use App\Infrastructure\WatchFile\WatchFileUserProvider;
use App\Infrastructure\WatchFileActivity\WatchFileHistoryProvider;
use App\UserInterface\Dto\Actor\ActorTypesDto;
use App\UserInterface\Dto\Actor\BatchChangeActorStatusInputDto;
use App\UserInterface\Dto\Actor\BatchChangeActorStatusOutputDto;
use App\UserInterface\Dto\Actor\ChangeActorStatusInputDto;
use App\UserInterface\Dto\Actor\ChangeActorStatusOutputDto;
use App\UserInterface\Dto\Chat\UserMessageDto;
use App\UserInterface\Dto\Source\BatchChangeSourceStatusInputDto;
use App\UserInterface\Dto\Source\BatchChangeSourceStatusOutputDto;
use App\UserInterface\Dto\Source\SourceTypesDto;
use App\UserInterface\Dto\WatchFile\ShareWatchFileInputDto;
use App\UserInterface\Dto\WatchFileActivity\GroupedWatchFileActivityDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['name'])]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['updated_at'])]
#[ORM\Index(columns: ['organisation_id'])]
#[ApiResource(
    operations: [
        new Get(
            openapi: new Operation(
                summary: 'Get a watch file by ID',
                description: 'Retrieves a specific watch file by its unique identifier. Only watch files accessible to the authenticated user are returned.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file to retrieve',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000',
                    ),
                ],
            ),
            security: WatchFileSecurity::SECURITY_VIEW,
            filters: [UserAccessibleWatchFileFilter::class],
            name: 'get_watch_file',
            provider: WatchFileProvider::class
        ),
        new GetCollection(
            openapi: new Operation(
                summary: 'Get collection of watch files',
                description: 'Retrieves a paginated list of watch files accessible to the authenticated user. Results can be filtered and ordered using various query parameters.'
            ),
            paginationEnabled: true,
            filters: [
                UserAccessibleWatchFileFilter::class,
                WatchFileOrderFilter::class,
                OnlyFavoritesFilter::class,
                IncludeArchivedFilter::class,
            ],
            provider: WatchFileCollectionProvider::class,
        ),
        new Post(
            openapi: new Operation(
                summary: 'Create a new watch file with conversation',
                description: 'Creates a new watch file with empty required fields and automatically creates a conversation with the provided message. The watch file will be created in draft status and assigned to the authenticated user.',
            ),
            denormalizationContext: [
                'groups' => ['message:write'],
            ],
            input: UserMessageDto::class,
            processor: WatchFileProcessor::class,
        ),
        new Patch(
            openapi: new Operation(
                summary: 'Update a watch file',
                description: 'Updates specific fields of an existing watch file. Only provided fields will be updated.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file to update',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000',
                    ),
                ],
            ),
            security: WatchFileSecurity::SECURITY_EDIT,
            processor: UpdateWatchFileProcessor::class,
        ),
        new Post(
            uriTemplate: '/watch_files/{id}/status/{status}',
            openapi: new Operation(
                summary: 'Change watch file status',
                description: 'Updates the status of a specific watch file. This operation allows changing the watch file state between enabled, archived, or draft. Status changes affect the watch file\'s visibility and accessibility within the application.',
                parameters: [
                    new Parameter(
                        name: 'status',
                        in: 'path',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'enum' => [
                                WatchFileStatus::ARCHIVED->value,
                                WatchFileStatus::ENABLED->value,
                                WatchFileStatus::DRAFT->value,
                            ],
                        ],
                        examples: new \ArrayObject([
                            WatchFileStatus::ENABLED->value => [
                                'summary' => 'Enable watch file',
                                'description' => 'Makes the watch file visible and accessible to users',
                                'value' => WatchFileStatus::ENABLED->value,
                            ],
                            WatchFileStatus::ARCHIVED->value => [
                                'summary' => 'Archive watch file',
                                'description' => 'Sets the watch file to archived mode, making it read-only and hidden from active lists',
                                'value' => WatchFileStatus::ARCHIVED->value,
                            ],
                            WatchFileStatus::DRAFT->value => [
                                'summary' => 'Mark as draft',
                                'description' => 'Sets the watch file to draft mode for modifications',
                                'value' => WatchFileStatus::DRAFT->value,
                            ],
                        ])
                    ),
                ],
            ),
            input: false,
            processor: ChangeWatchFileStatusProcessor::class,
        ),
        new Post(
            uriTemplate: '/watch_files/{watchFileId}/actors/{actorId}/status',
            types: ['collection'],
            uriVariables: ['watchFileId', 'actorId'],
            openapi: new Operation(
                summary: 'Change actor sources status',
                description: <<<'EOT'
                    This API route allows to modify the status of actor sources within a "watchFile".

                    The required parameters are:
                    - 'watchFileId': the unique identifier of the "watchFile".
                    - 'actorId': the unique identifier of the actor.

                    The payLoad allows to:
                    - specify the list of uuid of the sources we want to manipulate (sourceIds).
                    - if the list is empty, no sources of the actor will be candidate for a change
                    - specify the new status of the actor (status).
                    No source outside of this list will be modified.

                    - When an "actor" is set to "inactive" status, the sources in the "active" status will be set to "auto_disabled", the other sources will remain unchanged.
                    - When an "actor" is set to "active" status, all sources in the "auto_disabled" status will be set back to "active"
                    EOT
                ,
                parameters: [
                    new Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Parameter(
                        name: 'actorId',
                        in: 'path',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
            ),
            normalizationContext: ['actor:read'],
            denormalizationContext: ['actor:write'],
            input: ChangeActorStatusInputDto::class,
            output: ChangeActorStatusOutputDto::class,
            read: false,
            provider: null,
            processor: ChangeActorSourcesStatusProcessor::class,
        ),
        new Post(
            uriTemplate: '/watch_files/{watchFileId}/actors/batch-change-status',
            uriVariables: ['watchFileId'],
            openapi: new Operation(
                summary: 'Batch change actor status',
                description: 'Changes the status of multiple actors and all their sources in a single request',
                parameters: [
                    new Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
            ),
            normalizationContext: ['actor:read'],
            denormalizationContext: ['actor:write'],
            input: BatchChangeActorStatusInputDto::class,
            output: BatchChangeActorStatusOutputDto::class,
            read: false,
            processor: BatchChangeActorStatusProcessor::class,
        ),
        new Post(
            uriTemplate: '/watch_files/{watchFileId}/sources/batch-change-status',
            uriVariables: ['watchFileId'],
            openapi: new Operation(
                summary: 'Batch change source status',
                description: 'Changes the status of multiple sources in a single request',
                parameters: [
                    new Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
            ),
            normalizationContext: ['source:read'],
            denormalizationContext: ['source:write'],
            input: BatchChangeSourceStatusInputDto::class,
            output: BatchChangeSourceStatusOutputDto::class,
            read: false,
            processor: BatchChangeSourceStatusProcessor::class,
        ),
        new Get(
            uriTemplate: '/watch_files/{watchFileId}/actor-types',
            uriVariables: ['watchFileId'],
            openapi: new Operation(
                summary: 'Get actor types with counts',
                description: 'Retrieves all actor types linked to the watch file with their counts. Optionally filter by status and/or actor name.',
                parameters: [
                    new Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Parameter(
                        name: 'status',
                        in: 'query',
                        description: 'Filter by actor status (active or inactive)',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['active', 'inactive'],
                        ],
                    ),
                    new Parameter(
                        name: 'name',
                        in: 'query',
                        description: 'Filter by actor name (case-insensitive partial match on actor label)',
                        required: false,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['actor:read'],
            ],
            output: ActorTypesDto::class,
            provider: ActorTypesProvider::class,
        ),
        new Get(
            uriTemplate: '/watch_files/{watchFileId}/source-types',
            uriVariables: ['watchFileId'],
            openapi: new Operation(
                summary: 'Get source types with counts',
                description: 'Retrieves all source types linked to the watch file with their counts. Optionally filter by status and/or name.',
                parameters: [
                    new Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Parameter(
                        name: 'status',
                        in: 'query',
                        description: 'Filter by source status (active or inactive)',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['active', 'inactive'],
                        ],
                    ),
                    new Parameter(
                        name: 'name',
                        in: 'query',
                        description: 'Filter by source name (case-insensitive partial match on name or primary domain)',
                        required: false,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['source:read'],
            ],
            output: SourceTypesDto::class,
            provider: SourceTypesProvider::class,
        ),
        new Post(
            uriTemplate: '/watch_files/{id}/conversations',
            openapi: new Operation(
                summary: 'Create a new conversation in watch file',
                description: 'Creates a new conversation within the specified watch file. The conversation will be initialized with the provided user message.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file where the conversation will be created',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000'
                    ),
                ],
            ),
            description: 'Create a new conversation',
            normalizationContext: [
                'groups' => ['conversation:read'],
            ],
            denormalizationContext: [
                'groups' => ['message:write'],
            ],
            security: WatchFileSecurity::SECURITY_EDIT,
            input: UserMessageDto::class,
            output: Conversation::class,
            processor: WatchFileConversationProcessor::class,
        ),
        new Get(
            uriTemplate: '/watch_files/{id}/conversations/last',
            openapi: new Operation(
                summary: 'Get the last conversation for a watch file',
                description: 'Retrieves the most recent conversation from the specified watch file. If no conversations exist, returns null.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file to get the last conversation from',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000'
                    ),
                ],
            ),
            description: 'Get the last conversation for a watch file',
            normalizationContext: [
                'groups' => ['conversation:read'],
            ],
            security: WatchFileSecurity::SECURITY_EDIT,
            output: Conversation::class,
            provider: LastWatchFileConversationProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/watch_files/{id}/share',
            openapi: new Operation(
                summary: 'Get watch file sharing details',
                description: 'Retrieves the list of users who have access to the specified watch file, including their permission levels and sharing details.',
            ),
            description: 'Retrieve the list of users with access to the watch file',
            normalizationContext: [
                'groups' => ['watch_file_user:read'],
            ],
            filters: [UserAccessibleWatchFileFilter::class],
            provider: WatchFileUserProvider::class
        ),
        new Post(
            uriTemplate: '/watch_files/{id}/share',
            openapi: new Operation(
                summary: 'Share watch file with users',
                description: 'Shares the specified watch file with one or more users, granting them access according to the defined permission level.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file to share',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000'
                    ),
                ],
            ),
            description: 'Share the watch file with other users',
            normalizationContext: [
                'groups' => ['watch_file_user:read'],
            ],
            denormalizationContext: [
                'groups' => ['watch_file_user:write'],
            ],
            input: ShareWatchFileInputDto::class,
            processor: WatchFileUserProcessor::class
        ),
        new Delete(
            uriTemplate: '/watch_files/{id}/share/{watchFileUserId}',
            uriVariables: [
                'id' => [
                    'from_class' => WatchFile::class,
                    'identifiers' => ['id'],
                ],
                'watchFileUserId' => [
                    'from_class' => WatchFileUser::class,
                    'identifiers' => ['id'],
                ],
            ],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(
                summary: 'Remove watch file sharing access',
                description: 'Removes sharing access for a specific user from the watch file. This will revoke all permissions the user had on the watch file.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file to remove access from',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000',
                    ),
                    new Parameter(
                        name: 'watchFileUserId',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file user relationship to remove',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '123e4567-e89b-12d3-a456-426614174000',
                    ),
                ],
            ),
            description: 'Remove share access for a user',
            output: false,
            provider: WatchFileUserProcessor::class,
            processor: WatchFileUserProcessor::class
        ),
        new Post(
            uriTemplate: '/watch_files/{id}/favorite',
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(
                summary: 'Add watch file to favorites',
                description: 'Adds the specified watch file to the authenticated user\'s favorites list. Favorited watch files appear at the top of watch file lists and can be filtered separately.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file to add to favorites',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000'
                    ),
                ],
            ),
            description: 'Pin the watch file as a favorite',
            filters: [UserAccessibleWatchFileFilter::class],
            input: false,
            output: false,
            read: true,
            processor: FavoriteWatchFileProcessor::class,
        ),
        new Delete(
            uriTemplate: '/watch_files/{id}/favorite',
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(
                summary: 'Remove watch file from favorites',
                description: 'Removes the specified watch file from the authenticated user\'s favorites list. The watch file remains accessible but will no longer be marked as favorite.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file to remove from favorites',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000'
                    ),
                ],
            ),
            description: 'Unpin the watch file from favorites',
            filters: [UserAccessibleWatchFileFilter::class],
            output: false,
            read: true,
            processor: FavoriteWatchFileProcessor::class,
        ),
        new Get(
            uriTemplate: '/watch_files/{id}/history',
            openapi: new Operation(
                summary: 'Get watch file history',
                description: 'Retrieves the detailed history of all events that occurred for the specified watch file, grouped by day.',
                parameters: [
                    new Parameter(
                        name: 'id',
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
                        name: 'page',
                        in: 'query',
                        description: 'Page number for pagination (default: 1)',
                        required: false,
                        schema: [
                            'type' => 'integer',
                            'minimum' => 1,
                            'default' => 1,
                        ],
                        example: 1
                    ),
                    new Parameter(
                        name: 'itemsPerPage',
                        in: 'query',
                        description: 'Number of events per page (default: 30, max: 100)',
                        required: false,
                        schema: [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 100,
                            'default' => 30,
                        ],
                        example: 30
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['grouped_watch_file_activity:read', 'watch_file_activity:read'],
                'jsonld_embed_context' => false,
                'skip_null_values' => true,
            ],
            filters: [UserAccessibleWatchFileFilter::class],
            output: GroupedWatchFileActivityDto::class,
            name: 'get_watch_file_history',
            provider: WatchFileHistoryProvider::class,
        ),
        new Get(
            uriTemplate: '/watch_files/{watchFileId}/timeline/{eventId}/sources',
            uriVariables: [
                'watchFileId' => new Link(fromProperty: 'id', fromClass: self::class),
                'eventId' => new Link(description: 'Timeline event ID'),
            ],
            openapi: new Operation(
                tags: ['Sources'],
                responses: [
                    '200' => new Model\Response(
                        description: 'Event sources retrieved successfully',
                        content: new \ArrayObject([
                            'application/ld+json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'event' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => [
                                                    'type' => 'string',
                                                    'format' => 'uuid',
                                                ],
                                                'type' => [
                                                    'type' => 'string',
                                                    'example' => 'WATCHFILE_SOURCE_ADDED',
                                                ],
                                                'timestamp' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                ],
                                                'user' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'id' => [
                                                            'type' => 'string',
                                                            'format' => 'uuid',
                                                        ],
                                                        'name' => [
                                                            'type' => 'string',
                                                        ],
                                                        'email' => [
                                                            'type' => 'string',
                                                            'format' => 'email',
                                                        ],
                                                    ],
                                                ],
                                                'context' => [
                                                    'type' => 'string',
                                                ],
                                            ],
                                        ],
                                        'sources' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'id' => [
                                                        'type' => 'string',
                                                        'format' => 'uuid',
                                                    ],
                                                    'name' => [
                                                        'type' => 'string',
                                                    ],
                                                    'description' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'fr' => [
                                                                'type' => 'string',
                                                            ],
                                                            'en' => [
                                                                'type' => 'string',
                                                            ],
                                                        ],
                                                        'nullable' => true,
                                                    ],
                                                    'domain' => [
                                                        'type' => 'string',
                                                        'nullable' => true,
                                                    ],
                                                    'type' => [
                                                        'type' => 'string',
                                                        'nullable' => true,
                                                    ],
                                                    'status' => [
                                                        'type' => 'string',
                                                    ],
                                                    'url' => [
                                                        'type' => 'string',
                                                        'nullable' => true,
                                                    ],
                                                    'created_at' => [
                                                        'type' => 'string',
                                                        'format' => 'date-time',
                                                    ],
                                                    'updated_at' => [
                                                        'type' => 'string',
                                                        'format' => 'date-time',
                                                    ],
                                                    'is_deleted' => [
                                                        'type' => 'boolean',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'count' => [
                                            'type' => 'integer',
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                    '404' => new Model\Response(description: 'WatchFile or Event not found'),
                    '403' => new Model\Response(description: 'Access denied'),
                ],
                summary: 'Get event sources',
                description: 'Retrieves sources associated with a specific timeline event for modal display.',
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
                        example: '660e8400-e29b-41d4-a716-446655440001'
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['source:read'],
                'jsonld_embed_context' => false,
                'skip_null_values' => true,
            ],
            filters: [UserAccessibleWatchFileFilter::class],
            output: EventSources::class,
            name: 'get_event_sources',
            provider: EventSourcesProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['watch_file:read'],
    ],
    denormalizationContext: [
        'groups' => ['watch_file:write'],
    ],
)]
#[ApiFilter(OnlyFavoritesFilter::class)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'ipartial',
])]
class WatchFile implements CreatedByInterface, TenantAwareInterface
{
    use CreatedByTrait;
    public const int MAX_WATCHFILE_USERS = 50;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watch_file:read', 'watch_file:llm', 'document:save'])]
    #[ApiProperty(
        description: 'The unique identifier of the watch file, generated as a UUID.',
        example: '550e8400-e29b-41d4-a716-446655440000',
        openapiContext: [
            'type' => 'string',
            'format' => 'uuid',
        ],
    )]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    #[Groups([
        'watch_file:read',
        'watch_file:write',
        'watch_file:llm',
        'watch_file_activity:read',
        'document:save',
    ])]
    #[ApiProperty(
        description: 'The name of the watch file, used to identify it in lists and searches.',
        example: 'Market Research Project',
        openapiContext: [
            'type' => 'string',
            'maxLength' => 255,
        ],
    )]
    private string $name;

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    #[Groups(['watch_file:read', 'watch_file:write', 'watch_file:llm'])]
    #[ApiProperty(
        description: 'Indicates whether the title was manually set by the user (true) or automatically generated (false).',
        example: false,
        openapiContext: [
            'type' => 'boolean',
        ],
    )]
    private bool $titleManuallySetByUser = false;

    #[ORM\Column(type: 'text')]
    #[Groups(['watch_file:llm'])]
    private string $userObjective;

    #[ORM\Column(type: 'translated_text', nullable: true)]
    #[Groups(['watch_file:read', 'watch_file:write', 'watch_file:llm'])]
    private ?TranslatedText $referenceSubject = null;

    /**
     * LLM-optimized version of the reference subject (English only).
     * This field is NOT exposed in the API - it's used internally for LLM validation.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?string $referenceSubjectLlm = null;

    #[ORM\Column(type: 'string', enumType: WatchFileState::class)]
    private WatchFileState $state;

    #[ORM\Column(type: 'string', length: 50, nullable: true, enumType: MonitoringType::class)]
    #[Groups(['watch_file:llm'])]
    private ?MonitoringType $monitoringType = null;

    #[ORM\OneToMany(targetEntity: StrategicQuestion::class, mappedBy: 'watchFile', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy([
        'createdAt' => 'DESC',
    ])]
    #[Groups(['watch_file:llm'])]
    /**
     * @var Collection<int, StrategicQuestion>
     */
    private Collection $strategicQuestions;

    #[ORM\OneToMany(targetEntity: AnalysisResult::class, mappedBy: 'watchFile', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy([
        'createdAt' => 'DESC',
    ])]
    #[Groups(['watch_file:llm'])]
    /**
     * @var Collection<int, AnalysisResult>
     */
    private Collection $analysisResults;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['watch_file:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'watchFiles')]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['watch_file:read', 'watch_file:llm'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: WatchFileActor::class, mappedBy: 'watchFile', cascade: ['persist', 'remove'])]
    #[Groups(['watch_file:llm'])]
    /**
     * @var Collection<int, WatchFileActor>
     */
    private Collection $watchFileActors;

    #[ORM\OneToMany(targetEntity: WatchFileUser::class, mappedBy: 'watchFile', cascade: ['persist', 'remove'])]
    /**
     * @var Collection<int, WatchFileUser>
     */
    private Collection $watchFileUsers;

    /**
     * Virtual property to count the number of users who have access to this watch file.
     * By default, it is set to 1 (for the owner), but it should be updated based on the actual count of watch file users.
     * This property is used to provide quick access to the number of users.
     */
    #[ApiProperty(
        description: 'The number of users who have access to this watch file.',
        example: 5,
        openapiContext: [
            'type' => 'integer',
        ],
    )]
    #[Groups(['watch_file:read'])]
    private int $watchFileUsersCount = 1;

    /**
     * @var Collection<int, UserFavoriteWatchFile>
     */
    #[ORM\OneToMany(targetEntity: UserFavoriteWatchFile::class, mappedBy: 'watchFile', cascade: ['persist', 'remove'])]
    private Collection $userFavorites;

    /**
     * Virtual property that indicates whether the current user has this watch file as a favorite.
     * This property is computed at runtime and depends on the current user context.
     */
    #[Groups(['watch_file:read'])]
    #[ApiProperty(
        description: 'Indicates whether the current authenticated user has this watch file marked as favorite.',
        example: true,
        openapiContext: [
            'type' => 'boolean',
        ],
    )]
    private bool $isFavorite = false;

    #[Groups(['watch_file:read'])]
    #[ApiProperty(
        description: 'Indicates whether the current authenticated user has edit access to this watch file.',
        example: true,
        openapiContext: [
            'type' => 'boolean',
        ],
    )]
    private bool $userEditable = false;

    /**
     * @var Collection<int, Source>
     */
    #[ORM\OneToMany(targetEntity: Source::class, mappedBy: 'watchFile', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy([
        'createdAt' => 'DESC',
    ])]
    private Collection $sources;

    #[ORM\Column(type: 'string', enumType: WatchFileStatus::class, options: [
        'default' => WatchFileStatus::DRAFT,
    ])]
    #[Groups(['watch_file:read'])]
    #[Assert\Choice(callback: [WatchFileStatus::class, 'cases'])]
    private WatchFileStatus $status = WatchFileStatus::DRAFT;

    /**
     * @var Collection<int, WatchFileActivity>
     */
    #[ORM\OneToMany(targetEntity: WatchFileActivity::class, mappedBy: 'watchFile')]
    private Collection $activities;

    #[ORM\ManyToOne(targetEntity: Organisation::class)]
    #[ORM\JoinColumn(name: 'organisation_id', referencedColumnName: 'id', nullable: false)]
    private Organisation $organisation;

    public function __construct(
        string $name,
        string $userObjective,
        Organisation $organisation,
        ?User $createdBy = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->name = $name;
        $this->userObjective = $userObjective;
        $this->organisation = $organisation;
        $this->state = WatchFileState::NEW;
        $this->status = WatchFileStatus::DRAFT;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->createdBy = $createdBy;
        $this->updatedAt = new \DateTimeImmutable();
        $this->strategicQuestions = new ArrayCollection();
        $this->analysisResults = new ArrayCollection();
        $this->watchFileActors = new ArrayCollection();
        $this->watchFileUsers = new ArrayCollection();
        $this->userFavorites = new ArrayCollection();
        $this->sources = new ArrayCollection();
        $this->activities = new ArrayCollection();
    }

    public function getOrganisation(): Organisation
    {
        return $this->organisation;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTitleManuallySetByUser(bool $titleManuallySetByUser): self
    {
        $this->titleManuallySetByUser = $titleManuallySetByUser;

        return $this;
    }

    public function isTitleManuallySetByUser(): bool
    {
        return $this->titleManuallySetByUser;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUserObjective(): string
    {
        return $this->userObjective;
    }

    public function getReferenceSubject(): ?TranslatedText
    {
        return $this->referenceSubject;
    }

    public function setReferenceSubject(?TranslatedText $referenceSubject): self
    {
        $this->referenceSubject = $referenceSubject;

        return $this;
    }

    public function getReferenceSubjectLlm(): ?string
    {
        return $this->referenceSubjectLlm;
    }

    public function setReferenceSubjectLlm(?string $referenceSubjectLlm): self
    {
        $this->referenceSubjectLlm = $referenceSubjectLlm;

        return $this;
    }

    public function getState(): WatchFileState
    {
        return $this->state;
    }

    public function getMonitoringType(): ?MonitoringType
    {
        return $this->monitoringType;
    }

    public function setMonitoringType(?MonitoringType $monitoringType): self
    {
        $this->monitoringType = $monitoringType;

        return $this;
    }

    /**
     * @return Collection<StrategicQuestion>
     */
    public function getStrategicQuestions(): Collection
    {
        return $this->strategicQuestions;
    }

    /**
     * @return Collection<AnalysisResult>
     */
    public function getAnalysisResults(): Collection
    {
        return $this->analysisResults;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, WatchFileActor>
     */
    /**
     * @return Collection<int, WatchFileActor>
     */
    public function getWatchFileActors(): Collection
    {
        /** @var array<int, WatchFileActor> $actors */
        $actors = $this->watchFileActors->toArray();

        // Sort by actor label using French collation (case-insensitive, accent-sensitive)
        usort($actors, function (WatchFileActor $a, WatchFileActor $b): int {
            $collator = new \Collator('fr_FR');
            $collator->setStrength(\Collator::SECONDARY);

            $labelA = $a->getActor()
->getLabel();
            $labelB = $b->getActor()
->getLabel();

            $result = $collator->compare($labelA, $labelB);

            return false === $result ? 0 : $result;
        });

        return new ArrayCollection($actors);
    }

    public function addWatchFileActor(WatchFileActor $watchFileActor): self
    {
        if (!$this->watchFileActors->contains($watchFileActor)) {
            $this->watchFileActors->add($watchFileActor);
            $watchFileActor->setWatchFile($this);
        }

        return $this;
    }

    public function removeWatchFileActor(WatchFileActor $watchFileActor): self
    {
        $this->watchFileActors->removeElement($watchFileActor);

        return $this;
    }

    public function addActor(
        Actor $actor,
        ActorType $type,
        ?TranslatedText $explanation,
        float $score,
        ?Message $message,
    ): self {
        $watchFileActor = new WatchFileActor($actor, $this);
        $watchFileActor->setType($type);
        $watchFileActor->setExplanation($explanation);
        $watchFileActor->setScore($score);
        $watchFileActor->setAddedByMessage($message);

        $this->addWatchFileActor($watchFileActor);

        return $this;
    }

    public function removeActor(Actor $actor): self
    {
        $watchFileActor = $this->watchFileActors
            ->filter(static fn (WatchFileActor $fa) => $fa->getActor() === $actor)
            ->first()
        ;

        if ($watchFileActor) {
            $this->removeWatchFileActor($watchFileActor);
        }

        return $this;
    }

    public function analyzeNeeds(AnalysisResult $analysisResult): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::NEEDS_ANALYZED);

        $this->addAnalysisResult($analysisResult);
        $this->state = WatchFileState::NEEDS_ANALYZED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param list<StrategicQuestion> $strategicQuestions
     */
    public function generateQuestions(array $strategicQuestions): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::QUESTIONS_GENERATED);

        foreach ($strategicQuestions as $strategicQuestion) {
            $this->strategicQuestions->add($strategicQuestion);
        }

        $this->state = WatchFileState::QUESTIONS_GENERATED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function searchQueryInProgress(): bool
    {
        if (WatchFileState::QUESTIONS_GENERATED !== $this->getState()) {
            return false;
        }

        return $this->getStrategicQuestions()
            ->filter(static function (StrategicQuestion $strategicQuestion) {
                return null === $strategicQuestion->getSearchQueriesGeneratedAt();
            })->count() > 0
        ;
    }

    public function searchQueryGenerated(): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::SEARCH_QUERY_GENERATED);

        $this->state = WatchFileState::SEARCH_QUERY_GENERATED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function searchResultsRetrieved(): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::SEARCH_RESULTS_RETRIEVED);

        $this->state = WatchFileState::SEARCH_RESULTS_RETRIEVED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function filterUrls(): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::FILTER_URLS);

        $this->state = WatchFileState::FILTER_URLS;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function temporalFraming(): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::TEMPORAL_FRAMING);

        $this->state = WatchFileState::TEMPORAL_FRAMING;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function actorsDetected(): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::ACTORS_DETECTED);

        $this->state = WatchFileState::ACTORS_DETECTED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function sourcesDetected(): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::SOURCES_DETECTED);

        $this->state = WatchFileState::SOURCES_DETECTED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function detectMonitoringType(MonitoringType $type): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::MONITORING_TYPE_DETECTED);

        $this->monitoringType = $type;
        $this->state = WatchFileState::MONITORING_TYPE_DETECTED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function referenceSubjectDetected(): void
    {
        $this->state->throwIfInvalidTransition(WatchFileState::REFERENCE_SUBJECT_DETECTED);

        $this->state = WatchFileState::REFERENCE_SUBJECT_DETECTED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function fail(): void
    {
        $this->state = WatchFileState::FAILED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addAnalysisResult(AnalysisResult $analysisResult): void
    {
        if (!$this->analysisResults->contains($analysisResult)) {
            $this->analysisResults->add($analysisResult);
        }
    }

    /**
     * @return Collection<int, Source>
     */
    public function getSources(): Collection
    {
        return $this->sources;
    }

    /**
     * Sources exposed to LLM/agent payloads — excludes internal types (manual, etc.).
     * Uses Doctrine Criteria for SQL-level filtering (no full collection hydration).
     *
     * @return Collection<int, Source>
     */
    #[Groups(['watch_file:llm'])]
    public function getVisibleSources(): Collection
    {
        return $this->sources->matching(
            Criteria::create()->where(Criteria::expr()->neq('type', SourceType::MANUAL))
        );
    }

    public function addSource(Source $source): self
    {
        if (!$this->sources->contains($source)) {
            $this->sources->add($source);
            $source->setWatchFile($this);
        }

        return $this;
    }

    public function removeSource(Source $source): self
    {
        if ($this->sources->removeElement($source)) {
            if ($source->getWatchFile() === $this) {
                $source->setWatchFile(null);
            }
        }

        return $this;
    }

    public function isWorkflowStarted(): bool
    {
        return WatchFileState::NEW !== $this->state;
    }

    /**
     * @return Collection<int, WatchFileActivity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function getStatus(): WatchFileStatus
    {
        return $this->status;
    }

    public function setStatus(WatchFileStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return Collection<int, WatchFileUser>
     */
    public function getWatchFileUsers(): Collection
    {
        return $this->watchFileUsers;
    }

    public function setWatchFileUsersCount(int $watchFileUsersCount): int
    {
        return $this->watchFileUsersCount = $watchFileUsersCount;
    }

    public function getWatchFileUsersCount(): ?int
    {
        return $this->watchFileUsersCount;
    }

    public function addWatchFileUser(WatchFileUser $watchFileUser): self
    {
        if (!$this->watchFileUsers->contains($watchFileUser)) {
            $this->watchFileUsers->add($watchFileUser);
            $watchFileUser->setWatchFile($this);
        }

        return $this;
    }

    public function removeWatchFileUser(WatchFileUser $watchFileUser): self
    {
        if ($this->watchFileUsers->removeElement($watchFileUser)) {
            if ($watchFileUser->getWatchFile() === $this) {
                $watchFileUser->setWatchFile(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserFavoriteWatchFile>
     */
    public function getUserFavorites(): Collection
    {
        return $this->userFavorites;
    }

    public function addUserFavorite(UserFavoriteWatchFile $userFavorite): self
    {
        if (!$this->userFavorites->contains($userFavorite)) {
            $this->userFavorites->add($userFavorite);
            $userFavorite->setWatchFile($this);
        }

        return $this;
    }

    public function removeUserFavorite(UserFavoriteWatchFile $userFavorite): self
    {
        if ($this->userFavorites->removeElement($userFavorite)) {
            if ($userFavorite->getWatchFile() === $this) {
                $userFavorite->setWatchFile(null);
            }
        }

        return $this;
    }

    /**
     * Sets the isFavorite property for the current user context.
     * This method should be called by a data transformer or serializer context.
     */
    public function setIsFavorite(bool $isFavorite): self
    {
        $this->isFavorite = $isFavorite;

        return $this;
    }

    public function getIsFavorite(): bool
    {
        return $this->isFavorite;
    }

    /**
     * Sets the userEditable property for the current user context.
     * This method should be called by a data transformer or serializer context.
     */
    public function setUserEditable(bool $userEditable): self
    {
        $this->userEditable = $userEditable;

        return $this;
    }

    public function getUserEditable(): bool
    {
        return $this->userEditable;
    }

    public function isActive(): bool
    {
        return WatchFileStatus::ENABLED === $this->status;
    }

    public function getQualityConfig(): QualityConfig
    {
        // TODO: should be persisted in the watchfile table in a json, not no completes decisions on the personnalisation by watchfile was made.Once it s done this fonction should be modified to override a default behaviour ( organisation or target)
        return new QualityConfig();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function onPrePersist(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
