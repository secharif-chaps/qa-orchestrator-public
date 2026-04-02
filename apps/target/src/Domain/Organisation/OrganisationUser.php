<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['organisation_id', 'user_id'])]
class OrganisationUser
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'organisationUsers')]
    #[ORM\JoinColumn(name: 'organisation_id', nullable: false)]
    private Organisation $organisation;

    #[ORM\ManyToOne(inversedBy: 'organisationUsers')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\Column(enumType: OrganisationRole::class)]
    private OrganisationRole $role;

    #[ORM\Column(enumType: MembershipType::class)]
    private MembershipType $membershipType;

    #[ORM\Column]
    private \DateTimeImmutable $syncedAt;

    public function __construct(
        Organisation $organisation,
        User $user,
        OrganisationRole $role,
        MembershipType $membershipType,
    ) {
        $this->organisation = $organisation;
        $this->user = $user;
        $this->role = $role;
        $this->membershipType = $membershipType;
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOrganisation(): Organisation
    {
        return $this->organisation;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRole(): OrganisationRole
    {
        return $this->role;
    }

    public function setRole(OrganisationRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getMembershipType(): MembershipType
    {
        return $this->membershipType;
    }

    public function setMembershipType(MembershipType $membershipType): self
    {
        $this->membershipType = $membershipType;

        return $this;
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): self
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }
}
