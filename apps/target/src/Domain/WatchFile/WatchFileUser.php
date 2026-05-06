<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use ApiPlatform\Metadata\ApiProperty;
use App\Domain\Shared\CreatedByInterface;
use App\Domain\Shared\CreatedByTrait;
use App\Domain\Shared\HasWatchFileInterface;
use App\Domain\Shared\UpdatedByInterface;
use App\Domain\Shared\UpdatedByTrait;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Webmozart\Assert\Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['watch_file_id', 'user_id'])]
class WatchFileUser implements CreatedByInterface, UpdatedByInterface, HasWatchFileInterface
{
    use CreatedByTrait;
    use UpdatedByTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watch_file_user:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'watchFileUsers')]
    #[ORM\JoinColumn(name: 'watch_file_id', nullable: false)]
    private ?WatchFile $watchFile = null;

    #[ORM\ManyToOne(inversedBy: 'watchFileUsers')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    #[Groups(['watch_file_user:read'])]
    private ?User $user = null;

    #[ORM\Column(enumType: WatchFileUserRole::class)]
    #[Groups(['watch_file_user:read'])]
    #[ApiProperty(
        example: WatchFileUserRole::EDITOR->value,
        openapiContext: [
            'description' => 'The role of the user in the watchfile. This defines the permissions the user has within the watchfile.',
            'type' => 'string',
            'enum' => ['editor', 'viewer', 'owner'],
        ],
    )]
    private ?WatchFileUserRole $role = null;

    #[ORM\Column]
    #[Groups(['watch_file_user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['watch_file_user:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['watch_file_user:read'])]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['watch_file_user:read'])]
    private ?User $updatedBy = null;

    public function __construct(?WatchFile $watchFile, ?User $user, ?WatchFileUserRole $role)
    {
        if (null !== $watchFile) {
            $this->watchFile = $watchFile;
        }

        if (null !== $user) {
            $this->user = $user;
        }

        if (null !== $role) {
            $this->role = $role;
        }

        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->createdBy = null;
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): ?WatchFileUserRole
    {
        return $this->role;
    }

    public function setRole(?WatchFileUserRole $role): self
    {
        $this->role = $role;

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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        Assert::greaterThanEq($updatedAt, $this->createdAt, 'UpdatedAt must be greater than or equal to CreatedAt');

        $this->updatedAt = $updatedAt;

        return $this;
    }
}
