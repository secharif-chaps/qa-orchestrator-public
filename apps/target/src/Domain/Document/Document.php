<?php

declare(strict_types=1);

namespace App\Domain\Document;

use ApiPlatform\Elasticsearch\Filter\OrderFilter;
use ApiPlatform\Elasticsearch\Filter\TermFilter;
use ApiPlatform\Elasticsearch\State\Options;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Domain\Actor\Actor;
use App\Domain\Collect\CollectTask;
use App\Domain\Document\Deduplication\DuplicateAttempt;
use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\ScriptDetector;
use App\Domain\Shared\HasWatchFileInterface;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileSecurity;
use App\Infrastructure\Document\BatchManualValidateDocumentProcessor;
use App\Infrastructure\Document\CreateDocumentProcessor;
use App\Infrastructure\Document\DocumentCollectionProvider;
use App\Infrastructure\Document\DocumentProvider;
use App\Infrastructure\Document\DocumentSeenStatusProcessor;
use App\Infrastructure\Document\ManualValidateDocumentProcessor;
use App\Infrastructure\OpenSearch\Filter\CombinedMatchFilter;
use App\Infrastructure\OpenSearch\Filter\DateTimeFilter;
use App\Infrastructure\OpenSearch\Filter\DocumentValidationStatusFilter;
use App\UserInterface\Dto\Document\BatchManualValidateDocumentInputDto;
use App\UserInterface\Dto\Document\BatchManualValidateDocumentOutputDto;
use App\UserInterface\Dto\Document\CreateDocumentInputDto;
use App\UserInterface\Dto\Document\ManualValidateDocumentInputDto;
use App\UserInterface\Dto\Document\ManualValidateDocumentOutputDto;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/watch_files/{watchFileId}/documents',
            uriVariables: [
                'watchFileId' => new Link(
                    fromProperty: 'documents',
                    fromClass: WatchFile::class,
                    description: 'WatchFile id'
                ),
            ],
            openapi: new Model\Operation(
                responses: [
                    '200' => new Model\Response(
                        description: 'Documents collection with facets',
                        content: new \ArrayObject([
                            'application/ld+json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        '@context' => [
                                            'type' => 'string',
                                        ],
                                        '@id' => [
                                            'type' => 'string',
                                        ],
                                        '@type' => [
                                            'type' => 'string',
                                        ],
                                        'member' => [
                                            'type' => 'array',
                                            'description' => 'Array of documents',
                                            'items' => [
                                                'type' => 'object',
                                            ],
                                        ],
                                        'totalItems' => [
                                            'type' => 'integer',
                                            'description' => 'Total number of documents matching the query',
                                        ],
                                        'facets' => [
                                            'type' => 'object',
                                            'description' => 'Aggregated facets for filtering, calculated across all matching documents',
                                            'properties' => [
                                                'actors' => [
                                                    'type' => 'array',
                                                    'description' => 'Actor facets with document counts',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'actor' => [
                                                                'type' => 'object',
                                                                'properties' => [
                                                                    'id' => [
                                                                        'type' => 'string',
                                                                    ],
                                                                    'label' => [
                                                                        'type' => 'string',
                                                                        'nullable' => true,
                                                                    ],
                                                                    'primaryDomain' => [
                                                                        'type' => 'string',
                                                                        'nullable' => true,
                                                                    ],
                                                                ],
                                                            ],
                                                            'count' => [
                                                                'type' => 'integer',
                                                                'description' => 'Number of documents for this actor',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                'sources' => [
                                                    'type' => 'array',
                                                    'description' => 'Source facets with document counts',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'source' => [
                                                                'type' => 'object',
                                                                'properties' => [
                                                                    'id' => [
                                                                        'type' => 'string',
                                                                    ],
                                                                    'name' => [
                                                                        'type' => 'string',
                                                                        'nullable' => true,
                                                                    ],
                                                                    'primaryDomain' => [
                                                                        'type' => 'string',
                                                                        'nullable' => true,
                                                                    ],
                                                                ],
                                                            ],
                                                            'count' => [
                                                                'type' => 'integer',
                                                                'description' => 'Number of documents for this source',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                'domains' => [
                                                    'type' => 'array',
                                                    'description' => 'Domain facets with document counts',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'domain' => [
                                                                'type' => 'string',
                                                            ],
                                                            'count' => [
                                                                'type' => 'integer',
                                                                'description' => 'Number of documents for this domain',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                'statuses' => [
                                                    'type' => 'array',
                                                    'description' => 'Status facets with document counts (includes all possible statuses with 0 count)',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'status' => [
                                                                'type' => 'string',
                                                                'enum' => ['pending', 'validated', 'rejected'],
                                                            ],
                                                            'count' => [
                                                                'type' => 'integer',
                                                                'description' => 'Number of documents with this status',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                ],
                summary: 'Retrieves documents from a watch file with facets',
                description: 'Returns a paginated list of documents collected from the specified watch file, along with aggregated facets for filtering. Facets include counts for actors, sources, domains, and document statuses across all matching documents (not just the current page). Documents can be sorted by various date fields and filtered by watch file ID, status, actor, source, domain, and search terms.',
                parameters: [
                    new Model\Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watch file to retrieve documents from',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
            ),
            provider: DocumentCollectionProvider::class,
            stateOptions: new Options(index: self::INDEX_NAME),
        ),
        new Get(
            openapi: new Model\Operation(
                summary: 'Retrieves a specific document',
                description: 'Returns detailed information about a single document including its content, metadata, processing status, and associated watch file information.',
                parameters: [
                    new Model\Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier of the document to retrieve',
                        required: true,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
            ),
            security: WatchFileSecurity::SECURITY_VIEW,
            provider: DocumentProvider::class,
            stateOptions: new Options(index: self::INDEX_NAME),
        ),
        new Post(
            uriTemplate: '/documents/{id}/mark-seen',
            status: 200,
            openapi: new Model\Operation(
                summary: 'Mark a document as seen by the current user',
                description: <<<'EOT'
                    Records that the authenticated user has viewed this document. This tracking enables features like "unread" document filtering and read/unread status indicators in the UI.

                    Behavior:
                    - If the user has never seen this document, creates a new seen status record
                    - If the user has already seen this document, updates the seen_at timestamp
                    - The document's seen status is tracked per user (each user has their own seen/unseen state)

                    Response:
                    - Returns the full document object with updated seen status for the current user
                    - The seenStatus field will reflect the user's updated view timestamp

                    Errors:
                    - 401: Authentication required
                    - 403: User does not have access to this document's watch file
                    - 404: Document not found
                    EOT
                ,
                parameters: [
                    new Model\Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the document to mark as seen',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        example: '550e8400-e29b-41d4-a716-446655440000',
                    ),
                ],
            ),
            input: false,
            provider: DocumentProvider::class,
            processor: DocumentSeenStatusProcessor::class,
        ),
        new Post(
            uriTemplate: '/documents/{id}/manual-validate',
            openapi: new Model\Operation(
                responses: [
                    '200' => new Model\Response(
                        description: 'Document successfully validated',
                        content: new \ArrayObject([
                            'application/ld+json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                        ],
                                        'document_id' => [
                                            'type' => 'string',
                                        ],
                                        'manual_status' => [
                                            'type' => 'string',
                                        ],
                                        'validated_by' => [
                                            'type' => 'string',
                                            'nullable' => true,
                                        ],
                                        'validated_at' => [
                                            'type' => 'string',
                                            'format' => 'date-time',
                                            'nullable' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '403' => new Model\Response(description: 'Insufficient permissions'),
                    '404' => new Model\Response(description: 'Document not found'),
                ],
                summary: 'Manually validate or refuse a document',
                description: 'Allows a user with edit permissions on the watch file to manually accept, refuse, or mark as uncertain (reset) a document.',
                parameters: [
                    new Model\Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier of the document to validate',
                        required: true,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
                requestBody: new Model\RequestBody(
                    description: 'Validation action',
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'action' => [
                                        'type' => 'string',
                                        'enum' => ['accept', 'refuse'],
                                        'description' => 'The validation action to perform',
                                    ],
                                ],
                                'required' => ['action'],
                            ],
                        ],
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'action' => [
                                        'type' => 'string',
                                        'enum' => ['accept', 'refuse', 'uncertain'],
                                        'description' => 'The validation action to perform (uncertain resets the validation status)',
                                    ],
                                ],
                                'required' => ['action'],
                            ],
                        ],
                    ]),
                ),
            ),
            normalizationContext: [
                'groups' => ['document:manual-validate:output'],
            ],
            denormalizationContext: [
                'groups' => ['document:manual-validate'],
            ],
            validationContext: [
                'groups' => ['document:manual-validate'],
            ],
            input: ManualValidateDocumentInputDto::class,
            output: ManualValidateDocumentOutputDto::class,
            read: false,
            processor: ManualValidateDocumentProcessor::class,
        ),
        new Post(
            uriTemplate: '/documents/batch-manual-validate',
            openapi: new Model\Operation(
                responses: [
                    '200' => new Model\Response(
                        description: 'Documents manually validated or refused.',
                        content: new \ArrayObject([
                            'application/ld+json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'description' => 'Whether the batch operation was successful',
                                        ],
                                        'validated_count' => [
                                            'type' => 'integer',
                                            'description' => 'Number of documents successfully validated',
                                        ],
                                        'failed_count' => [
                                            'type' => 'integer',
                                            'description' => 'Number of documents that failed validation',
                                        ],
                                        'results' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'document_id' => [
                                                        'type' => 'string',
                                                        'format' => 'uuid',
                                                    ],
                                                    'status' => [
                                                        'type' => 'string',
                                                        'enum' => ['success', 'error'],
                                                    ],
                                                    'reason' => [
                                                        'type' => 'string',
                                                        'description' => 'Error reason if status is error',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                    '403' => new Model\Response(description: 'Insufficient permissions.'),
                    '422' => new Model\Response(description: 'Validation error.'),
                ],
                summary: 'Manually validate or refuse multiple documents',
                description: 'Allows a user with edit permissions on the watch files to manually accept or refuse multiple documents in a single operation.',
                requestBody: new Model\RequestBody(
                    description: 'Batch validation action',
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'document_ids' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'string',
                                            'format' => 'uuid',
                                        ],
                                        'description' => 'Array of document UUIDs to validate',
                                    ],
                                    'action' => [
                                        'type' => 'string',
                                        'enum' => ['accept', 'refuse'],
                                        'description' => 'Validation action to apply to all documents',
                                    ],
                                ],
                                'required' => ['document_ids', 'action'],
                            ],
                        ],
                    ]),
                ),
            ),
            normalizationContext: [
                'groups' => ['document:batch-manual-validate:output'],
            ],
            denormalizationContext: [
                'groups' => ['document:batch-manual-validate'],
            ],
            validationContext: [
                'groups' => ['document:batch-manual-validate'],
            ],
            input: BatchManualValidateDocumentInputDto::class,
            output: BatchManualValidateDocumentOutputDto::class,
            read: false,
            processor: BatchManualValidateDocumentProcessor::class,
        ),
        new Post(
            uriTemplate: '/watch_files/{watchFileId}/documents',
            uriVariables: [
                'watchFileId' => new Link(
                    fromProperty: 'documents',
                    fromClass: WatchFile::class,
                    description: 'WatchFile id'
                ),
            ],
            status: 201,
            openapi: new Model\Operation(
                summary: 'Create a document manually from a URL or HTML content',
                description: 'Fetches and parses content from a URL or provided HTML, extracts metadata, and creates a document in the watch file.',
            ),
            normalizationContext: [
                'groups' => ['document:read'],
            ],
            denormalizationContext: [
                'groups' => ['document:create'],
            ],
            validationContext: [
                'groups' => ['document:create'],
            ],
            input: CreateDocumentInputDto::class,
            read: false,
            processor: CreateDocumentProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['document:read'],
    ],
)]
#[ApiFilter(OrderFilter::class, properties: [
    'datePublish' => 'desc',
    'dateCollect' => 'desc',
])]
#[ApiFilter(TermFilter::class, strategy: 'exact', properties: [
    'watchFile.id',
    'status',
    'actor.id',
    'source.id',
    'actor.primaryDomain',
])]
#[ApiFilter(DateTimeFilter::class, properties: ['datePublish', 'dateCollect'])]
#[ApiFilter(CombinedMatchFilter::class, properties: ['title', 'excerpt', 'content'], arguments: [
    'fieldName' => 'search',
])]
#[ApiFilter(DocumentValidationStatusFilter::class)]
class Document implements HasWatchFileInterface
{
    public const string INDEX_NAME = 'document';

    #[ApiProperty(
        description: 'Unique identifier (UUID v4) of the document.',
        identifier: true,
        example: '550e8400-e29b-41d4-a716-446655440000',
    )]
    #[Groups(['document:read', 'document:save'])]
    private string $id;

    #[ApiProperty(
        description: 'Title of the document, typically extracted from the source page (`<title>`, `og:title`, …).',
        example: 'ECB raises key interest rates by 25 basis points',
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.title.not_blank')]
    #[Assert\Length(min: 1, max: 500, minMessage: 'document.title.min_length', maxMessage: 'document.title.max_length')]
    private string $title;

    #[ApiProperty(
        description: 'Short teaser of the document (first paragraph, meta description, or LLM-generated summary). 10-1000 characters.',
        example: 'The European Central Bank announced on Thursday a new hike of its key interest rates, bringing the deposit rate to 4%.',
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.excerpt.not_blank')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'document.excerpt.min_length',
        maxMessage: 'document.excerpt.max_length'
    )]
    private string $excerpt;

    #[ApiProperty(
        description: 'Format of the source document. `html` for web articles, `pdf` for binary attachments.',
        example: 'html',
        openapiContext: [
            'type' => 'string',
            'enum' => ['pdf', 'html'],
        ],
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.type.not_blank')]
    #[Assert\Choice(choices: ['pdf', 'html'], message: 'document.type.invalid_choice')]
    private string $type;

    #[ApiProperty(
        description: 'Original publication date as advertised by the source (article `datePublished`, RSS `pubDate`, …). May be `null` if the source did not expose one.',
        example: '2026-04-30T14:23:51+00:00',
        openapiContext: [
            'type' => 'string',
            'format' => 'date-time',
        ],
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private \DateTimeImmutable $datePublish;

    #[ApiProperty(
        description: 'Server-side timestamp at which the collect pipeline fetched the document (UTC).',
        example: '2026-04-30T14:25:03+00:00',
        openapiContext: [
            'type' => 'string',
            'format' => 'date-time',
        ],
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private \DateTimeImmutable $dateCollect;

    #[ApiProperty(
        description: 'Full extracted body of the document (cleaned text, no markup). Used for full-text search and dedup signals.',
        example: 'The European Central Bank raised its three key interest rates by 25 basis points on Thursday, bringing the deposit rate to 4%, the main refinancing rate to 4.5% and the marginal lending facility to 4.75%. The ECB justifies this new round of monetary tightening by persistent core inflation above the 2% target.',
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.content.not_blank')]
    private string $content;

    #[ApiProperty(
        description: 'Lifecycle status of the document in the collect pipeline (pending, validated, refused, …).',
        example: 'pending',
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.status.not_blank')]
    private DocumentStatus $status;

    #[ApiProperty]
    #[Groups(['document:write', 'document:save'])]
    private ?ValidationReason $validationReason = null;

    #[ApiProperty(
        description: 'ISO 639-1 language code detected on the body. Currently constrained to `en` or `fr` until additional NLP pipelines are wired in.',
        example: 'en',
        openapiContext: [
            'type' => 'string',
            'enum' => ['en', 'fr'],
        ],
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.language.not_blank')]
    #[Assert\Choice(choices: ['en', 'fr'], message: 'document.language.invalid_choice')]
    private string $language;

    #[ApiProperty]
    #[Groups(['document:write', 'document:save'])]
    private bool $isInteresting;

    #[ApiProperty(
        description: 'LLM-generated short insight (≤ 500 chars) explaining why this document is relevant for the watch_file. `null` until the AI validation step has run.',
        example: 'Direct rate decision affecting funding costs for the watched financial group.',
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\Length(max: 500, maxMessage: 'document.insight.max_length')]
    private ?string $insight;

    #[ApiProperty(
        description: 'Public URL where the document was originally published.',
        example: 'https://www.ft.com/content/example-article',
        openapiContext: [
            'type' => 'string',
            'format' => 'uri',
            'nullable' => true,
        ],
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\Url(requireTld: true)]
    private ?string $url = null;

    #[ApiProperty(
        description: 'Whether the document is subject to French CFC (Centre Français d\'exploitation du droit de Copie) restrictions. Drives display-only previews in the UI.',
        example: false,
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private bool $cfcRestricted;

    #[ApiProperty(description: 'IRI of the watch_file this document was collected for.',)]
    #[Groups(['document:read', 'document:save'])]
    protected ?WatchFile $watchFile = null;

    #[ApiProperty(description: 'Source the document was published on (newspaper, blog, RSS feed, …).',)]
    #[Groups(['document:read', 'document:save'])]
    protected ?Source $source = null;

    #[ApiProperty(
        description: 'Actor that authored or is the subject of the document, when one was identified by the collect pipeline.',
    )]
    #[Groups(['document:read', 'document:save'])]
    protected ?Actor $actor = null;

    #[ApiProperty(description: 'User who last modified the document (manual validation, edit, …).',)]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    protected ?User $updatedBy = null;

    #[ApiProperty(description: 'Multilingual summary of the document, generated by the AI summary pipeline.',)]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?Summary $summary = null;

    #[ApiProperty(
        description: 'Lifecycle status of the AI summary generation (`pending`, `processing`, `done`, `failed`).',
        example: 'done',
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?SummaryStatus $summaryStatus = null;

    #[ApiProperty(
        description: 'Timestamp at which the AI summary was generated (UTC). `null` while `summaryStatus` is `pending` or `processing`.',
        example: '2026-04-30T14:26:18+00:00',
        openapiContext: [
            'type' => 'string',
            'format' => 'date-time',
            'nullable' => true,
        ],
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?\DateTimeImmutable $summaryGeneratedAt = null;

    #[ApiProperty(
        description: 'Timestamp of the last write to the document (manual validation, AI summary refresh, …). UTC.',
        example: '2026-04-30T14:35:42+00:00',
        openapiContext: [
            'type' => 'string',
            'format' => 'date-time',
            'nullable' => true,
        ],
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var array<string, array<int, string>>|null
     */
    #[ApiProperty]
    private ?array $highlight = null;

    #[ApiProperty(
        description: 'Result of the AI relevance-check pipeline (status, confidence score, reasoning). `null` until the pipeline has run.',
    )]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?AIValidation $aiValidation = null;

    #[ApiProperty(
        description: 'Whether the **current authenticated user** has already opened this document. Per-user state — different viewers see different values.',
        example: false,
    )]
    #[Groups(['document:read'])]
    private bool $isSeen = false;

    #[ApiProperty(
        description: 'Manual validation verdict applied by a reviewer (`accept`, `refuse`). `null` if no manual review has happened yet.',
        example: 'accept',
    )]
    #[Groups(['document:read', 'document:save'])]
    private ?ManualValidationStatus $manualStatus = null;

    #[ApiProperty(
        description: 'User who applied the manual validation verdict. `null` if `manualStatus` is `null`.',
    )]
    #[Groups(['document:read', 'document:save'])]
    private ?User $validatedBy = null;

    #[ApiProperty(
        description: 'Timestamp at which the manual validation was applied (UTC). `null` if no manual review has happened yet.',
        example: '2026-04-30T14:38:09+00:00',
        openapiContext: [
            'type' => 'string',
            'format' => 'date-time',
            'nullable' => true,
        ],
    )]
    #[Groups(['document:read', 'document:save'])]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ApiProperty]
    #[Groups(['document:save'])]
    private bool $eventsExtracted = false;

    #[ApiProperty]
    #[Groups(['document:save'])]
    private ?\DateTimeImmutable $eventsExtractedAt = null;

    #[ApiProperty]
    #[Groups(['document:save'])]
    private ?\DateTimeImmutable $eventsExtractedStartedAt = null;

    #[Groups(['document:save'])]
    private ?string $providerId = null;

    #[Groups(['document:save'])]
    private ?float $contentRatio = null;

    #[Groups(['document:save'])]
    private ?string $canonicalUrl = null;

    /**
     * Fingerprint bundle computed by the dedup pipeline (TAR-1145, ADR-2026-006).
     * `null` for legacy documents indexed before the feature shipped.
     */
    #[Groups(['document:save'])]
    private ?Fingerprint $fingerprint = null;

    /**
     * Replays of the dedup pipeline that matched **this** document. FIFO-bounded
     * to avoid unbounded growth on viral articles republished by many sources.
     *
     * @var list<DuplicateAttempt>
     */
    #[ApiProperty(
        description: <<<'EOT'
            Audit trail of dedup-pipeline runs that matched this document as the
            **original** (i.e. the one a freshly-collected candidate was matched
            against). Each entry exposes the candidate URL, the collect
            timestamp and the dedup verdict (`outcome`, `matchStage`,
            `similarity`).

            Bounded **FIFO** at 50 entries to keep the trace usable on viral
            articles republished by many sources. Same-URL collisions overwrite
            the existing entry in place — preserves source diversity in the
            "vu sur AFP, Le Monde, Reuters" UX signal.

            Read-only: this list is populated by the dedup pipeline and is not
            writable through the API.
            EOT
        ,
        readable: true,
        writable: false,
    )]
    #[Groups(['document:read', 'document:save'])]
    private array $duplicates = [];

    /**
     * Maximum number of `DuplicateAttempt` entries kept on a single document.
     * Older entries are evicted FIFO when this cap is reached.
     */
    public const int MAX_DUPLICATE_ATTEMPTS = 50;

    public function __construct(
        ?string $id,
        string $title,
        string $excerpt,
        string $type,
        \DateTimeImmutable $datePublish,
        \DateTimeImmutable $dateCollect,
        string $content,
        DocumentStatus $status = DocumentStatus::PENDING,
        ?ValidationReason $validationReason = null,
        string $language = 'en',
        bool $isInteresting = false,
        ?string $insight = null,
        bool $cfcRestricted = false,
        ?string $url = null,
    ) {
        $this->id = $id ?? Uuid::v4()->toString();
        $this->title = $title;
        $this->excerpt = $excerpt;
        $this->type = $type;
        $this->datePublish = $datePublish;
        $this->dateCollect = $dateCollect;
        $this->content = $content;
        $this->status = $status;
        $this->validationReason = $validationReason;
        $this->language = $language;
        $this->isInteresting = $isInteresting;
        $this->insight = $insight;
        $this->cfcRestricted = $cfcRestricted;
        $this->eventsExtracted = false;
        $this->eventsExtractedAt = null;
        $this->url = $url;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt): self
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDatePublish(): \DateTimeImmutable
    {
        return $this->datePublish;
    }

    public function setDatePublish(\DateTimeImmutable $datePublish): self
    {
        $this->datePublish = $datePublish;

        return $this;
    }

    public function getDateCollect(): \DateTimeImmutable
    {
        return $this->dateCollect;
    }

    public function setDateCollect(\DateTimeImmutable $dateCollect): self
    {
        $this->dateCollect = $dateCollect;

        return $this;
    }

    public function hasContent(): bool
    {
        // $content can be uninitialized when Document is hydrated via newInstanceWithoutConstructor() (OpenSearch denormalization)
        // @phpstan-ignore isset.initializedProperty
        return isset($this->content) && '' !== $this->content;
    }

    public function hasDatePublish(): bool
    {
        // $datePublish can be uninitialized when Document is hydrated via newInstanceWithoutConstructor() (OpenSearch denormalization)
        // @phpstan-ignore isset.initializedProperty
        return isset($this->datePublish);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getWordCount(): int
    {
        if (!$this->hasContent()) {
            return 0;
        }

        $content = $this->content;

        // Each CJK character is one lexical unit (no spaces between words in Han/Kana/Hangul scripts).
        // Pattern is shared with `ScriptDetector` — single source of truth for what counts as CJK.
        $cjkCount = (int) preg_match_all(ScriptDetector::CJK_PATTERN, $content);

        $withoutCjk = (string) preg_replace(ScriptDetector::CJK_PATTERN, ' ', $content);

        return $cjkCount + str_word_count($withoutCjk);
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function setStatus(DocumentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getValidationReason(): ?ValidationReason
    {
        return $this->validationReason;
    }

    public function setValidationReason(?ValidationReason $validationReason): self
    {
        $this->validationReason = $validationReason;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function isInteresting(): bool
    {
        return $this->isInteresting;
    }

    public function setIsInteresting(bool $isInteresting): self
    {
        $this->isInteresting = $isInteresting;

        return $this;
    }

    public function getInsight(): ?string
    {
        return $this->insight;
    }

    public function setInsight(?string $insight): self
    {
        $this->insight = $insight;

        return $this;
    }

    public function isCfcRestricted(): bool
    {
        return $this->cfcRestricted;
    }

    public function setCfcRestricted(bool $cfcRestricted): self
    {
        $this->cfcRestricted = $cfcRestricted;

        return $this;
    }

    public function getWatchFile(): ?WatchFile
    {
        return $this->watchFile;
    }

    public function setWatchFile(?WatchFile $watchFile): self
    {
        $this->watchFile = $watchFile;

        return $this;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(?Source $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getActor(): ?Actor
    {
        return $this->actor;
    }

    public function setActor(?Actor $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getSummary(): ?Summary
    {
        return $this->summary;
    }

    public function setSummary(?Summary $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getSummaryStatus(): ?SummaryStatus
    {
        return $this->summaryStatus;
    }

    public function setSummaryStatus(?SummaryStatus $summaryStatus): self
    {
        $this->summaryStatus = $summaryStatus;

        return $this;
    }

    public function getSummaryGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->summaryGeneratedAt;
    }

    public function setSummaryGeneratedAt(?\DateTimeImmutable $summaryGeneratedAt): self
    {
        $this->summaryGeneratedAt = $summaryGeneratedAt;

        return $this;
    }

    public function updateSummary(?Summary $summary, SummaryStatus $status): self
    {
        $this->setSummary($summary);
        $this->setSummaryStatus($status);
        $this->setSummaryGeneratedAt(new \DateTimeImmutable());

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getWatchFileName(): ?string
    {
        return $this->watchFile?->getName();
    }

    /**
     * @return array<string, array<int, string>>|null
     */
    public function getHighlight(): ?array
    {
        return $this->highlight;
    }

    /**
     * @param array<string, array<int, string>>|null $highlight
     */
    public function setHighlight(?array $highlight): self
    {
        $this->highlight = $highlight;

        if (null !== $highlight) {
            foreach ($highlight as $field => $fragments) {
                if ('title' === $field && 1 === \count($fragments)) {
                    $this->title = $fragments[0];
                }
            }
        }

        return $this;
    }

    #[ApiProperty(
        description: 'Whether the OpenSearch highlighter matched the user query in the title. Drives `<mark>` rendering in the UI.',
        example: true,
    )]
    #[Groups(['document:read'])]
    public function isTitleHighlighted(): bool
    {
        return $this->highlight && isset($this->highlight['title']);
    }

    #[ApiProperty(
        description: 'Whether the OpenSearch highlighter matched the user query in the excerpt.',
        example: true,
    )]
    #[Groups(['document:read'])]
    public function isExcerptHighlighted(): bool
    {
        return $this->highlight && isset($this->highlight['excerpt']);
    }

    #[ApiProperty(
        description: 'Whether the OpenSearch highlighter matched the user query in the content body.',
        example: false,
    )]
    #[Groups(['document:read'])]
    public function isContentHighlighted(): bool
    {
        return $this->highlight && isset($this->highlight['content']);
    }

    public function getAiValidation(): ?AIValidation
    {
        return $this->aiValidation;
    }

    public function setAiValidation(?AIValidation $aiValidation): self
    {
        $this->aiValidation = $aiValidation;

        return $this;
    }

    public function updateAiValidation(AIValidation $aiValidation): self
    {
        $this->aiValidation = $aiValidation;

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function capitalizeFrom(CollectTask $collectTask): self
    {
        $this->setWatchFile($collectTask->getWatchFile());
        $this->setSource($collectTask->getSource());
        $this->setActor($collectTask->getSource()->getActor());

        return $this;
    }

    #[ApiProperty(
        description: 'Convenience flag — `true` when `aiValidation.status` is `validated`. Mutually exclusive with the other `aiValidation*` booleans.',
        example: true,
    )]
    #[Groups(['document:read'])]
    public function isAiValidated(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::VALIDATED === $this->aiValidation->status;
    }

    #[ApiProperty(
        description: 'Convenience flag — `true` when `aiValidation.status` is `rejected`.',
        example: false,
    )]
    #[Groups(['document:read'])]
    public function isAiRejected(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::REJECTED === $this->aiValidation->status;
    }

    #[ApiProperty(
        description: 'Convenience flag — `true` when `aiValidation.status` is `uncertain` (LLM confidence below the auto-decision threshold).',
        example: false,
    )]
    #[Groups(['document:read'])]
    public function isAiValidationUncertain(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::UNCERTAIN === $this->aiValidation->status;
    }

    #[ApiProperty(
        description: 'Convenience flag — `true` when `aiValidation.status` is `failed` (transient pipeline error, e.g. LLM timeout).',
        example: false,
    )]
    #[Groups(['document:read'])]
    public function isAiValidationFailed(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::FAILED === $this->aiValidation->status;
    }

    #[ApiProperty(
        description: 'Convenience flag — `true` when `aiValidation.status` is `pending` (queued, not yet processed).',
        example: false,
    )]
    #[Groups(['document:read'])]
    public function isAiValidationPending(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::PENDING === $this->aiValidation->status;
    }

    public function getIsSeen(): bool
    {
        return $this->isSeen;
    }

    public function setIsSeen(bool $isSeen): self
    {
        $this->isSeen = $isSeen;

        return $this;
    }

    public function getManualStatus(): ?ManualValidationStatus
    {
        return $this->manualStatus;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function manuallyValidate(ManualValidationStatus $action, User $validatedBy): self
    {
        $this->manualStatus = $action;
        $this->validatedBy = $validatedBy;
        $this->validatedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function resetManualValidation(): self
    {
        $this->manualStatus = null;
        $this->validatedBy = null;
        $this->validatedAt = null;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    #[ApiProperty(description: 'Convenience flag — `true` when `manualStatus` is `accept`.', example: true,)]
    #[Groups(['document:read'])]
    public function isManuallyAccepted(): bool
    {
        return ManualValidationStatus::ACCEPTED === $this->manualStatus;
    }

    #[ApiProperty(description: 'Convenience flag — `true` when `manualStatus` is `refuse`.', example: false,)]
    #[Groups(['document:read'])]
    public function isManuallyRefused(): bool
    {
        return ManualValidationStatus::REFUSED === $this->manualStatus;
    }

    public function isEventsExtracted(): bool
    {
        return $this->eventsExtracted;
    }

    public function setEventsExtracted(?bool $eventsExtracted): self
    {
        $this->eventsExtracted = $eventsExtracted ?? false;

        return $this;
    }

    public function getEventsExtractedAt(): ?\DateTimeImmutable
    {
        return $this->eventsExtractedAt;
    }

    public function setEventsExtractedAt(?\DateTimeImmutable $eventsExtractedAt): self
    {
        $this->eventsExtractedAt = $eventsExtractedAt;

        return $this;
    }

    public function getEventsExtractedStartedAt(): ?\DateTimeImmutable
    {
        return $this->eventsExtractedStartedAt;
    }

    public function setEventsExtractedStartedAt(?\DateTimeImmutable $eventsExtractedStartedAt): self
    {
        $this->eventsExtractedStartedAt = $eventsExtractedStartedAt;

        return $this;
    }

    public function startEventsExtraction(): self
    {
        $this->eventsExtractedStartedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): self
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function getContentRatio(): ?float
    {
        return $this->contentRatio;
    }

    public function setContentRatio(?float $contentRatio): self
    {
        $this->contentRatio = $contentRatio;

        return $this;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): self
    {
        $this->canonicalUrl = $canonicalUrl;

        return $this;
    }

    public function getFingerprint(): ?Fingerprint
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?Fingerprint $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    /**
     * @return list<DuplicateAttempt>
     */
    public function getDuplicates(): array
    {
        return $this->duplicates;
    }

    /**
     * Record a tentative collect that the dedup pipeline matched against
     * this document. The list is bounded by {@see self::MAX_DUPLICATE_ATTEMPTS} —
     * the oldest entry is evicted when the cap is reached, FIFO-style.
     *
     * Same-URL collisions overwrite the existing entry rather than appending
     * a duplicate one. Recurrent retry-style collects on a single URL would
     * otherwise saturate the bounded list and crowd out the diversity of
     * sources the trace is meant to capture (the "vu sur AFP, Le Monde,
     * Reuters" UX signal).
     *
     * The semantics are intentionally pipeline-agnostic: the caller decides
     * the {@see DuplicateAttempt::$outcome} (`DUPLICATE` / `NEAR_EXACT` /
     * `NEAR_DUPLICATE`) and we record verbatim.
     */
    public function recordDuplicate(DuplicateAttempt $attempt): self
    {
        foreach ($this->duplicates as $i => $existing) {
            if ($existing->url === $attempt->url) {
                $this->duplicates[$i] = $attempt;

                return $this;
            }
        }

        $this->duplicates[] = $attempt;

        $overflow = \count($this->duplicates) - self::MAX_DUPLICATE_ATTEMPTS;
        if ($overflow > 0) {
            $this->duplicates = \array_slice($this->duplicates, $overflow);
        }

        return $this;
    }

    #[Groups(['document:save'])]
    public function getOrganisationId(): ?string
    {
        return $this->watchFile?->getOrganisation()?->getId();
    }
}
