<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\Chat\Content\MessageContent;
use App\Domain\Chat\Content\TextContent;
use App\Domain\Shared\CreatedByInterface;
use App\Domain\Shared\CreatedByTrait;
use App\Domain\Shared\HasWatchFileInterface;
use App\Domain\Shared\UpdatedByInterface;
use App\Domain\Shared\UpdatedByTrait;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Chat\MessageCollectionProvider;
use App\Infrastructure\Chat\RetryMessageProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['status'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/conversations/{id}/messages',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'messages',
                    fromClass: Conversation::class,
                    description: 'Conversation id'
                ),
            ],
            paginationViaCursor: [
                [
                    'field' => 'cursor',
                    'direction' => 'DESC',
                ],
            ],
            openapi: new Operation(
                summary: 'Get messages for a specific conversation',
                description: <<<'EOT'
                    Retrieve all messages associated with a conversation using cursor-based pagination. Messages are ordered by creation date (newest first).

                    Available filters:
                    - createdAt: Date filter on message creation date
                      - createdAt[after]: Messages created after the specified date (inclusive)
                      - createdAt[before]: Messages created before the specified date (inclusive)
                      - createdAt[strictly_after]: Messages created after the specified date (exclusive)
                      - createdAt[strictly_before]: Messages created before the specified date (exclusive)
                    - id: Range filter on message ID

                    Pagination:
                    - Uses cursor-based pagination for efficient navigation through large message collections
                    - Cursor is based on creation timestamp (microseconds since Unix epoch)
                    - Default items per page: 30
                    - Maximum items per page: 50
                    - Can be customized with itemsPerPage parameter
                    EOT
            ),
            paginationType: 'cursor',
            paginationMaximumItemsPerPage: 50,
            paginationPartial: true,
            order: [
                'cursor' => 'DESC',
            ],
            normalizationContext: [
                'groups' => ['message:read'],
            ],
            name: 'get_collection_messages',
            provider: MessageCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/messages/{id}/retry',
            openapi: new Operation(
                summary: 'Retry sending a failed message',
                description: <<<'EOT'
                    Retry sending a message that previously failed. Only messages in 'error' status can be retried.
                    The retry count is incremented and the message status is set to 'pending'.
                    Maximum retry attempts: 3.

                    Security: Requires WATCH_FILE_EDIT permission on the parent WatchFile (validated via Conversation).
                    EOT
                ,
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the message to retry',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['message:read'],
            ],
            input: false,
            name: 'retry_message',
            processor: RetryMessageProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['message:read'],
    ],
    denormalizationContext: [
        'groups' => ['message:write'],
    ],
)]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(RangeFilter::class, properties: ['cursor'])]
#[ApiFilter(OrderFilter::class, properties: [
    'createdAt' => 'DESC',
    'cursor' => 'DESC',
])]
class Message implements CreatedByInterface, UpdatedByInterface, HasWatchFileInterface
{
    use CreatedByTrait;
    use UpdatedByTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['message:read', 'message:llm'])]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['message:read', 'message:llm'])]
    private ?Conversation $conversation = null;

    #[ORM\Column(length: 50, enumType: MessageRole::class)]
    #[Assert\Choice(callback: [MessageRole::class, 'cases'])]
    #[Groups(['message:read', 'message:write', 'message:llm'])]
    private MessageRole $role = MessageRole::User;

    #[ORM\Column(length: 50, enumType: MessageStatus::class, options: [
        'default' => 'sent',
    ])]
    #[Groups(['message:read'])]
    private MessageStatus $status = MessageStatus::Sent;

    #[ORM\Column(type: 'integer', options: [
        'default' => 0,
    ])]
    #[Groups(['message:read'])]
    private int $retryCount = 0;

    /**
     * @var Collection<int, MessageContent>
     */
    #[ORM\OneToMany(
        targetEntity: MessageContent::class,
        mappedBy: 'message',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy([
        'createdAt' => 'ASC',
    ])]
    #[Groups(['message:read', 'conversation:llm'])]
    private Collection $contents;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['message:read', 'message:llm'])]
    private ?array $metadata = null;

    #[ORM\Column]
    #[Groups(['message:read', 'message:llm'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'bigint')]
    private int $cursor;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['message:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['message:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['message:read'])]
    private ?User $updatedBy = null;

    public function __construct(?Conversation $conversation = null)
    {
        $this->contents = new ArrayCollection();
        $this->setCreatedAt(new \DateTimeImmutable());
        $this->conversation = $conversation;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): self
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getWatchFile(): ?WatchFile
    {
        return $this->conversation?->getWatchFile();
    }

    public function getRole(): MessageRole
    {
        return $this->role;
    }

    public function setRole(MessageRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getStatus(): MessageStatus
    {
        return $this->status;
    }

    public function setStatus(MessageStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): self
    {
        $this->retryCount = $retryCount;

        return $this;
    }

    public function incrementRetryCount(): self
    {
        ++$this->retryCount;

        return $this;
    }

    /**
     * @return Collection<int, MessageContent>
     */
    public function getContents(): Collection
    {
        return $this->contents;
    }

    public function addContent(MessageContent $content): self
    {
        if (!$this->contents->contains($content)) {
            $this->contents->add($content);
            $content->setMessage($this);
        }

        return $this;
    }

    public function setTextContent(string $text): self
    {
        $this->contents->clear();
        $this->addContent(new TextContent($text));

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        // Calculate microseconds since Unix epoch for numeric cursor value
        // This maintains ordering based on creation date and works with range filters
        $this->cursor = (int) ($createdAt->getTimestamp() * 1000000 + (int) $createdAt->format('u'));

        return $this;
    }

    public function getCursor(): int
    {
        return $this->cursor;
    }

    #[Groups(['conversation:llm'])]
    public function getSource(): string
    {
        return match ($this->getRole()) {
            MessageRole::Model => 'AI',
            MessageRole::User => 'USER',
            MessageRole::System, MessageRole::SystemError => 'SYSTEM',
        };
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
