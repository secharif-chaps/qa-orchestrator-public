<?php

declare(strict_types=1);

namespace App\Domain\Source;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\Actor\Actor;
use App\Domain\Chat\Message;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Shared\HasRequiredWatchFileInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileSecurity;
use App\Infrastructure\Doctrine\Filter\ActiveSourceFilter;
use App\Infrastructure\WatchFile\Source\ChangeSourceStatusProcessor;
use App\Infrastructure\WatchFile\WatchFileGroupedSourceProvider;
use App\Infrastructure\WatchFile\WatchFileSourceProvider;
use App\UserInterface\Dto\Source\ChangeSourceStatusDto;
use App\UserInterface\Dto\Source\SourceGroupOutput;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'watch_file_source_uniq', columns: ['watch_file_id', 'type', 'url'])]
#[ORM\Index(columns: ['name'])]
#[ORM\Index(columns: ['primary_domain'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/watch_files/{watchFileId}/sources',
            uriVariables: [
                'watchFileId' => new Link(fromProperty: 'sources', fromClass: WatchFile::class, description: 'WatchFile id'),
            ],
            openapi: new Operation(
                summary: 'Get sources for a specific watch file',
                description: <<<'EOT'
                    Retrieve all sources associated with a watch file. Sources can be filtered by name, type, domain, and status.

                    Available filters:
                    - name: Partial case-insensitive search on source name
                    - type: Exact match filter on source type
                    - primaryDomain: Partial case-insensitive search on source primary domain
                    - isActive: Boolean filter for active status (true for active sources, false for inactive/auto-disabled sources)

                    Available ordering:
                    - name: Order by source name
                    - type: Order by source type
                    - primaryDomain: Order by source primary domain
                    - createdAt: Order by source creation date
                    - active: Order by source active status
                    EOT
            ),
            shortName: 'WatchFile',
            provider: WatchFileSourceProvider::class
        ),
        new Get(
            uriTemplate: '/watch_files/{watchFileId}/sources/grouped',
            uriVariables: [
                'watchFileId' => new Link(fromProperty: 'sources', fromClass: WatchFile::class, description: 'WatchFile id'),
            ],
            openapi: new Operation(
                summary: 'Get sources for a specific watch file grouped by type',
                description: <<<'EOT'
                    Retrieve all active sources associated with a watch file grouped by type.
                    Only sources with ACTIVE status are returned. Sources with INACTIVE or AUTO_DISABLED status are hidden.

                    Search functionality:
                    - search: Search term for filtering sources by name or domain (minimum 2 characters)
                    EOT
                ,
                parameters: [
                    new Parameter(
                        name: 'search',
                        in: 'query',
                        description: 'Search term to filter sources by name or domain (minimum 2 characters)',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'minLength' => 2,
                        ],
                    ),
                ],
            ),
            shortName: 'WatchFile',
            normalizationContext: [
                'groups' => ['source_group:read'],
            ],
            output: SourceGroupOutput::class,
            provider: WatchFileGroupedSourceProvider::class
        ),
        new Post(
            uriTemplate: '/watch_files/{watchFileId}/sources/{sourceId}/change-status',
            uriVariables: [
                'watchFileId' => new Link(fromProperty: 'sources', fromClass: WatchFile::class),
                'sourceId' => new Link(fromClass: Source::class, identifiers: ['id']),
            ],
            openapi: new Operation(
                summary: 'Change source status',
                description: <<<'EOT'
                    Changes the activation status of a source within a watch file.

                    Request body structure:
                    {
                        "status": "enabled"|"disabled" (string, required) - The desired status for the source
                    }

                    Response:
                    Returns the updated source object with the new status.
                    - 200: Source status successfully changed
                    - 400: Invalid status value or missing required fields
                    - 404: Source or watch file not found
                    - 403: Insufficient permissions to modify source

                    Example:
                    POST /watch_files/{watchFileId}/sources/{sourceId}/change-status
                    {
                        "status": "enabled"
                    }
                    EOT
                ,
                parameters: [
                    new Parameter(
                        'watchFileId',
                        'path',
                        description: 'The ID of the watch file containing the source',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Parameter(
                        'sourceId',
                        'path',
                        description: 'The ID of the source to change status',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
            ),
            shortName: 'WatchFile',
            security: WatchFileSecurity::SECURITY_EDIT,
            input: ChangeSourceStatusDto::class,
            processor: ChangeSourceStatusProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['source:read'],
    ],
    denormalizationContext: [
        'groups' => ['source:write'],
    ],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'ipartial',
    'primaryDomain' => 'ipartial',
    'type' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'name' => null,
    'type' => null,
    'primaryDomain' => null,
    'createdAt' => null,
])]
#[ApiFilter(ActiveSourceFilter::class)]
class Source implements HasRequiredWatchFileInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([
        'watch_file:read',
        'watch_file:llm',
        'source:read',
        'document:read',
        'source_group:read',
        'actor:read',
        'document:save',
    ])]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups([
        'watch_file:read',
        'watch_file:llm',
        'source:read',
        'document:read',
        'source_group:read',
        'document:save',
    ])]
    private string $name;

    #[ORM\Column(type: 'translated_text')]
    #[Assert\NotBlank]
    #[Groups(['watch_file:llm', 'source:read', 'source_group:read'])]
    private TranslatedText $description;

    #[ORM\Column(type: 'string', enumType: SourceType::class)]
    #[Assert\NotBlank]
    #[Groups(['watch_file:llm', 'source:read'])]
    private SourceType $type;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    #[Groups(['watch_file:llm', 'source:read', 'document:read', 'source_group:read'])]
    private string $url;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['watch_file:llm', 'source:read', 'document:read', 'document:save', 'source_group:read'])]
    private string $primaryDomain;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?string $query = null;

    #[ORM\Column(type: 'translated_text')]
    #[Assert\NotBlank]
    #[Groups(['watch_file:llm', 'source:read'])]
    private TranslatedText $relevance;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['watch_file:llm'])]
    /**
     * @var array<string, mixed>|null
     */
    private ?array $parameters = null;

    #[ORM\ManyToOne(targetEntity: WatchFile::class, inversedBy: 'sources')]
    #[ORM\JoinColumn(nullable: false)]
    private ?WatchFile $watchFile = null;

    #[ORM\Column(type: 'string', enumType: SourceStatus::class, options: [
        'default' => SourceStatus::ACTIVE,
    ])]
    #[Groups(['watch_file:llm', 'source:read', 'source_group:read'])]
    #[Assert\Choice(callback: [SourceStatus::class, 'cases'])]
    private SourceStatus $status = SourceStatus::ACTIVE;

    #[ORM\ManyToOne(inversedBy: 'sources')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['watch_file:llm', 'source:read', 'source_group:read'])]
    private ?Actor $actor = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Message $addedByMessage = null;

    /**
     * @var Collection<int, SourceActivity>
     */
    #[ORM\OneToMany(targetEntity: SourceActivity::class, mappedBy: 'source')]
    private Collection $activities;

    #[ORM\Column]
    #[Groups(['watch_file:llm', 'source:read', 'source_group:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['watch_file:llm', 'source:read', 'source_group:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(options: [
        'default' => CollectStatus::STOPPED->value,
    ])]
    #[Groups(['watch_file:llm', 'source:read', 'source_group:read'])]
    private CollectStatus $collectStatus = CollectStatus::STOPPED;

    /**
     * @param array<string, mixed>|null $parameters
     */
    public function __construct(
        string $name,
        TranslatedText $description,
        SourceType $type,
        string $url,
        string $primaryDomain,
        TranslatedText $relevance,
        ?Actor $actor,
        ?WatchFile $watchFile = null,
        ?string $query = null,
        ?array $parameters = null,
        ?Message $addedByMessage = null,
        CollectStatus $collectStatus = CollectStatus::STOPPED,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->url = $url;
        $this->primaryDomain = $primaryDomain;
        $this->query = $query;
        $this->relevance = $relevance;
        $this->parameters = $parameters;
        $this->watchFile = $watchFile;
        $this->actor = $actor;
        $this->addedByMessage = $addedByMessage;
        $this->activities = new ArrayCollection();
        $this->collectStatus = $collectStatus;
    }

    public function getId(): string
    {
        if (null === $this->id) {
            throw new \LogicException('The source ID is not initialized yet.');
        }

        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): TranslatedText
    {
        return $this->description;
    }

    public function getType(): SourceType
    {
        return $this->type;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPrimaryDomain(): string
    {
        return $this->primaryDomain;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function getRelevance(): TranslatedText
    {
        return $this->relevance;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function hasParameter(string $key): bool
    {
        return null !== $this->parameters && \array_key_exists($key, $this->parameters);
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        return null !== $this->parameters && \array_key_exists($key, $this->parameters)
            ? $this->parameters[$key]
            : $default;
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }

    public function setWatchFile(?WatchFile $watchFile): self
    {
        $this->watchFile = $watchFile;

        return $this;
    }

    #[Groups(['watch_file:llm'])]
    public function getStatus(): SourceStatus
    {
        return $this->status;
    }

    public function setStatus(SourceStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getActor(): ?Actor
    {
        return $this->actor;
    }

    public function setActor(?Actor $actor): self
    {
        $this->actor = $actor;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    #[Groups(['watch_file:llm', 'source:read'])]
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAddedByMessage(): ?Message
    {
        return $this->addedByMessage;
    }

    public function setAddedByMessage(?Message $addedByMessage): self
    {
        $this->addedByMessage = $addedByMessage;

        return $this;
    }

    public function activate(): void
    {
        $this->status = SourceStatus::ACTIVE;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->status = SourceStatus::INACTIVE;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateQuery(?string $query): void
    {
        $this->query = $query;
    }

    public function updateParameters(?array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @return Collection<int, SourceActivity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCollectStatus(): CollectStatus
    {
        return $this->collectStatus;
    }

    public function setCollectStatus(CollectStatus $collectStatus): void
    {
        $this->collectStatus = $collectStatus;
    }

    public function updateCollectStatus(CollectTask $collectTask): void
    {
        $this->collectStatus = match ($collectTask->getStatus()) {
            CollectTaskStatus::CREATED, CollectTaskStatus::RUNNING, CollectTaskStatus::QUEUED => CollectStatus::RUNNING,
            CollectTaskStatus::COMPLETED, CollectTaskStatus::CANCELLED => CollectStatus::STOPPED,
            CollectTaskStatus::FAILED => CollectStatus::ERROR,
        };
    }
}
