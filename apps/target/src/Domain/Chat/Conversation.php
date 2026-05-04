<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Domain\Agent\AgentExecution;
use App\Domain\Shared\HasRequiredWatchFileInterface;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileSecurity;
use App\Infrastructure\Chat\CancelConversationProcessor;
use App\Infrastructure\WatchFile\ConversationMessageProcessor;
use App\UserInterface\Dto\Chat\UserMessageDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/conversations/{id}/messages',
            openapi: new Operation(
                summary: 'Add a message to a conversation',
                description: <<<'EOT'
                    Send a new user message to an existing conversation with the AI assistant.

                    The message will be processed by the AI agent and a response will be generated asynchronously.
                    Real-time updates about the conversation state and AI responses can be received via Mercure subscriptions.

                    Request body:
                    - content (string, required): The message text to send to the AI assistant

                    Response:
                    - Returns the updated conversation object with the new message added
                    - The conversation state may change to 'processing' while the AI generates a response

                    Errors:
                    - 400: Invalid request body (missing or empty content)
                    - 401: Authentication required
                    - 403: User does not have access to this conversation
                    - 404: Conversation not found
                    EOT
                ,
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the conversation',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
                requestBody: new RequestBody(
                    description: 'The message to send to the conversation',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['content'],
                                'properties' => [
                                    'content' => [
                                        'type' => 'string',
                                        'description' => 'The message text to send to the AI assistant',
                                        'example' => 'What are the latest news about this topic?',
                                        'minLength' => 1,
                                    ],
                                ],
                            ],
                        ],
                    ]),
                    required: true,
                ),
            ),
            security: WatchFileSecurity::SECURITY_EDIT,
            normalizationContext: [
                'groups' => ['conversation:read'],
            ],
            denormalizationContext: [
                'groups' => ['message:write'],
            ],
            input: UserMessageDto::class,
            processor: ConversationMessageProcessor::class,
        ),
        new Post(
            uriTemplate: '/conversations/{id}/cancel',
            status: Response::HTTP_OK,
            input: false,
            normalizationContext: [
                'groups' => ['conversation:read'],
            ],
            processor: CancelConversationProcessor::class,
        ),
    ]
)]
class Conversation implements HasRequiredWatchFileInterface
{
    /** @var list<string> */
    public const array SUPPORTED_LANGUAGES = ['fr', 'en'];
    public const string FALLBACK_LANGUAGE = 'en';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['conversation:read', 'conversation:llm'])]
    private ?string $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['conversation:read', 'conversation:write', 'conversation:llm'])]
    private ?string $title = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['conversation:read', 'conversation:write', 'conversation:llm'])]
    private WatchFile $watchFile;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['conversation:read', 'conversation:llm'])]
    private ?array $metadata = null;

    #[ORM\Column(length: 50, enumType: ConversationState::class, options: [
        'default' => 'idle',
    ])]
    #[Groups(['conversation:read'])]
    private ConversationState $state = ConversationState::Idle;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(
        targetEntity: Message::class,
        mappedBy: 'conversation',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy([
        'createdAt' => 'ASC',
    ])]
    #[Groups(['conversation:llm'])]
    private Collection $messages;

    #[ORM\Column]
    #[Groups(['conversation:read', 'conversation:llm'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['conversation:read', 'conversation:llm'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 2, options: [
        'default' => 'en',
    ])]
    #[Groups(['conversation:read', 'conversation:write'])]
    #[Assert\Choice(choices: self::SUPPORTED_LANGUAGES)]
    private string $language = self::FALLBACK_LANGUAGE;

    #[ORM\OneToOne(targetEntity: AgentExecution::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AgentExecution $agentExecution = null;

    public function __construct(WatchFile $watchFile)
    {
        $this->watchFile = $watchFile;
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }

    public function setWatchFile(WatchFile $watchFile): self
    {
        $this->watchFile = $watchFile;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getState(): ConversationState
    {
        return $this->state;
    }

    public function setState(ConversationState $state): self
    {
        $this->state = $state;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getConversation() === $this) {
                $message->setConversation(null);
            }
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAgentExecution(): ?AgentExecution
    {
        return $this->agentExecution;
    }

    public function linkAgentExecution(AgentExecution $agentExecution): void
    {
        $this->agentExecution = $agentExecution;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function unlinkAgentExecution(): void
    {
        $this->agentExecution = null;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
