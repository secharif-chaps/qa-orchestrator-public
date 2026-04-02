<?php

declare(strict_types=1);

namespace App\Domain\SourceActivity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\Domain\Shared\HasRequiredWatchFileInterface;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\SourceActivity\SourceActivityProvider;
use App\UserInterface\Dto\SourceActivity\GroupedSourceActivityDto;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(columns: ['created_at'])]
#[ORM\Index(columns: ['action_type'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/sources/{sourceId}/history',
            uriVariables: [
                'sourceId' => [
                    'from_class' => Source::class,
                    'identifiers' => ['id'],
                ],
            ],
            openapi: new Model\Operation(
                summary: 'Retrieves the detailed history of a source grouped by day',
                description: 'Returns a detailed chronological history of all events that occurred for the specified source, grouped by day.',
                parameters: [
                    new Model\Parameter(
                        name: 'sourceId',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the source to retrieve history for',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'page',
                        in: 'query',
                        description: 'Page number for pagination',
                        required: false,
                        schema: [
                            'type' => 'integer',
                            'default' => 1,
                            'minimum' => 1,
                        ],
                    ),
                    new Model\Parameter(
                        name: 'itemsPerPage',
                        in: 'query',
                        description: 'Number of items per page',
                        required: false,
                        schema: [
                            'type' => 'integer',
                            'default' => 20,
                            'minimum' => 1,
                            'maximum' => 100,
                        ],
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['grouped_source_activity:read', 'source_activity:read'],
            ],
            security: 'is_granted("SOURCE_ACTIVITY_VIEW")',
            output: GroupedSourceActivityDto::class,
            provider: SourceActivityProvider::class,
        ),
    ]
)]
class SourceActivity implements HasRequiredWatchFileInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['source_activity:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Source::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(name: 'source_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['source_activity:read'])]
    private Source $source;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['source_activity:read'])]
    private ?User $user;

    #[ORM\Column(enumType: SourceActivityActionType::class)]
    #[Groups(['source_activity:read'])]
    private SourceActivityActionType $actionType;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', name: 'action_data')]
    #[Groups(['source_activity:read'])]
    private array $actionData;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['source_activity:read'])]
    private \DateTime $createdAt;

    /**
     * @param array<string, mixed> $actionData
     */
    public function __construct(
        Source $source,
        ?User $user,
        SourceActivityActionType $actionType,
        array $actionData,
    ) {
        $this->id = (string) Uuid::v4();
        $this->source = $source;
        $this->user = $user;
        $this->actionType = $actionType;
        $this->actionData = $actionData;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getActionType(): SourceActivityActionType
    {
        return $this->actionType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionData(): array
    {
        return $this->actionData;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getWatchFile(): WatchFile
    {
        return $this->source->getWatchFile();
    }
}
