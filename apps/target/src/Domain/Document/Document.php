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

    #[ApiProperty(identifier: true)]
    #[Groups(['document:read', 'document:save'])]
    private string $id;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.title.not_blank')]
    #[Assert\Length(min: 1, max: 500, minMessage: 'document.title.min_length', maxMessage: 'document.title.max_length')]
    private string $title;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.excerpt.not_blank')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'document.excerpt.min_length',
        maxMessage: 'document.excerpt.max_length'
    )]
    private string $excerpt;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.type.not_blank')]
    #[Assert\Choice(choices: ['pdf', 'html'], message: 'document.type.invalid_choice')]
    private string $type;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private \DateTimeImmutable $datePublish;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private \DateTimeImmutable $dateCollect;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.content.not_blank')]
    private string $content;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.status.not_blank')]
    private DocumentStatus $status;

    #[ApiProperty]
    #[Groups(['document:write', 'document:save'])]
    private ?ValidationReason $validationReason = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\NotBlank(message: 'document.language.not_blank')]
    #[Assert\Choice(choices: ['en', 'fr'], message: 'document.language.invalid_choice')]
    private string $language;

    #[ApiProperty]
    #[Groups(['document:write', 'document:save'])]
    private bool $isInteresting;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\Length(max: 500, maxMessage: 'document.insight.max_length')]
    private ?string $insight;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    #[Assert\Url(requireTld: true)]
    private ?string $url = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private bool $cfcRestricted;

    #[ApiProperty]
    #[Groups(['document:read', 'document:save'])]
    protected ?WatchFile $watchFile = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:save'])]
    protected ?Source $source = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:save'])]
    protected ?Actor $actor = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    protected ?User $updatedBy = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?Summary $summary = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?SummaryStatus $summaryStatus = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?\DateTimeImmutable $summaryGeneratedAt = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var array<string, array<int, string>>|null
     */
    #[ApiProperty]
    private ?array $highlight = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    private ?AIValidation $aiValidation = null;

    #[ApiProperty]
    #[Groups(['document:read'])]
    private bool $isSeen = false;

    #[Groups(['document:read', 'document:save'])]
    private ?ManualValidationStatus $manualStatus = null;

    #[ApiProperty]
    #[Groups(['document:read', 'document:save'])]
    private ?User $validatedBy = null;

    #[ApiProperty]
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

        // Each CJK character is one lexical unit (no spaces between words in Han/Kana/Hangul scripts)
        $cjkCount = (int) preg_match_all('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $content);

        $withoutCjk = (string) preg_replace('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', ' ', $content);

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

    #[Groups(['document:read'])]
    public function isTitleHighlighted(): bool
    {
        return $this->highlight && isset($this->highlight['title']);
    }

    #[Groups(['document:read'])]
    public function isExcerptHighlighted(): bool
    {
        return $this->highlight && isset($this->highlight['excerpt']);
    }

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

    #[Groups(['document:read'])]
    public function isAiValidated(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::VALIDATED === $this->aiValidation->status;
    }

    #[Groups(['document:read'])]
    public function isAiRejected(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::REJECTED === $this->aiValidation->status;
    }

    #[Groups(['document:read'])]
    public function isAiValidationUncertain(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::UNCERTAIN === $this->aiValidation->status;
    }

    #[Groups(['document:read'])]
    public function isAiValidationFailed(): bool
    {
        return null !== $this->aiValidation && AiValidationStatus::FAILED === $this->aiValidation->status;
    }

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

    #[Groups(['document:read'])]
    public function isManuallyAccepted(): bool
    {
        return ManualValidationStatus::ACCEPTED === $this->manualStatus;
    }

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

    #[Groups(['document:save'])]
    public function getOrganisationId(): ?string
    {
        return $this->watchFile?->getOrganisation()?->getId();
    }
}
