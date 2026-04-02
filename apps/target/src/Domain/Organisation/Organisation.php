<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;

#[ORM\Entity]
class Organisation
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'string', unique: true)]
    private string $keycloakId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, OrganisationUser>
     */
    #[ORM\OneToMany(targetEntity: OrganisationUser::class, mappedBy: 'organisation')]
    private Collection $organisationUsers;

    public function __construct(string $name, string $keycloakId)
    {
        $this->name = $name;
        $this->keycloakId = $keycloakId;
        $this->createdAt = new \DateTimeImmutable();
        $this->organisationUsers = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getKeycloakId(): string
    {
        return $this->keycloakId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, OrganisationUser>
     */
    public function getOrganisationUsers(): Collection
    {
        return $this->organisationUsers;
    }
}
