<?php

declare(strict_types=1);

namespace App\Domain\WatchFileActivity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\HasRequiredWatchFileInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFileActivity\UserActivityProvider;
use App\Infrastructure\WatchFileActivity\WatchFileActivityProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(columns: ['organisation_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/watch_files/{watchFileId}/activities',
            uriVariables: [
                'watchFileId' => [
                    'from_class' => WatchFile::class,
                    'identifiers' => ['id'],
                ],
            ],
            openapi: new Model\Operation(
                summary: 'Retrieves the activities of a watchfile',
                description: 'Returns a chronological list of activities (file additions, deletions, modifications, permission changes) that occurred within the specified watchfile. Requires the user to have at least read permissions on the watchfile.',
                parameters: [
                    new Model\Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the watchfile to retrieve activities for',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['watch_file_activity:read'],
            ],
            name: 'get_watch_file_activities',
            provider: WatchFileActivityProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/users/{userId}/activities',
            uriVariables: [
                'userId' => [
                    'from_class' => User::class,
                    'identifiers' => ['id'],
                ],
            ],
            openapi: new Model\Operation(
                summary: 'Retrieves the activities of a user',
                description: 'Gets all watchfile-impacting actions performed by the authenticated user: watchfile creation, deletion, moves, sharing changes, and permission modifications.',
                parameters: [
                    new Model\Parameter(
                        name: 'userId',
                        in: 'path',
                        description: 'The unique identifier (UUID) of the user to retrieve activities for',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['watch_file_activity:read'],
            ],
            name: 'get_user_activities',
            provider: UserActivityProvider::class,
        ),
    ]
)]
class WatchFileActivity implements HasRequiredWatchFileInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watch_file_activity:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: WatchFile::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(name: 'watch_file_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['watch_file_activity:read'])]
    private WatchFile $watchFile;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'watchFileActivities')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['watch_file_activity:read'])]
    private User $user;

    #[ORM\Column(enumType: WatchFileActivityActionType::class)]
    #[Groups(['watch_file_activity:read'])]
    private WatchFileActivityActionType $actionType;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file_activity:read'])]
    private array $actionData;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['watch_file_activity:read'])]
    private \DateTime $createdAt;

    #[ORM\ManyToOne(targetEntity: Organisation::class)]
    #[ORM\JoinColumn(name: 'organisation_id', referencedColumnName: 'id', nullable: false)]
    private Organisation $organisation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'impersonator_id', referencedColumnName: 'id', nullable: true)]
    private ?User $impersonator = null;

    /**
     * @param array<string, mixed> $actionData
     */
    public function __construct(
        WatchFile $watchFile,
        User $user,
        WatchFileActivityActionType $actionType,
        array $actionData,
        Organisation $organisation,
        ?User $impersonator = null,
        ?\DateTime $createdAt = null,
    ) {
        $this->id = (string) Uuid::v4();
        $this->watchFile = $watchFile;
        $this->user = $user;
        $this->actionType = $actionType;
        $this->actionData = $actionData;
        $this->organisation = $organisation;
        $this->impersonator = $impersonator;
        $this->createdAt = $createdAt ?? new \DateTime();
    }

    public function getOrganisation(): Organisation
    {
        return $this->organisation;
    }

    public function getImpersonator(): ?User
    {
        return $this->impersonator;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getActionType(): WatchFileActivityActionType
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
}
