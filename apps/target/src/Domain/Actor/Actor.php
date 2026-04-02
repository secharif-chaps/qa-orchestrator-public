<?php

declare(strict_types=1);

namespace App\Domain\Actor;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantAwareInterface;
use App\Domain\Source\Source;
use App\Infrastructure\WatchFile\ActorSourceProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['organisation_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/watch_files/{watchFileId}/actors/{actorId}/sources',
            openapi: new Operation(
                summary: 'Get sources for a specific actor in a watch file',
                description: <<<'EOT'
                    Retrieve all sources associated with a specific actor within a watch file. This endpoint returns all sources that are linked to the actor in the specified watch file.

                    Available ordering:
                    - name: Order by source name
                    - type: Order by source type
                    - primaryDomain: Order by source primary domain
                    - createdAt: Order by source creation date
                    EOT
                ,
                parameters: [
                    new Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        description: 'WatchFile identifier',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Parameter(
                        name: 'actorId',
                        in: 'path',
                        description: 'Actor identifier',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Parameter(
                        name: 'order[name]',
                        in: 'query',
                        description: 'Order by source name',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['asc', 'desc'],
                            'default' => 'asc',
                        ],
                    ),
                    new Parameter(
                        name: 'order[type]',
                        in: 'query',
                        description: 'Order by source type',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['asc', 'desc'],
                            'default' => 'asc',
                        ],
                    ),
                    new Parameter(
                        name: 'order[primaryDomain]',
                        in: 'query',
                        description: 'Order by source primary domain',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['asc', 'desc'],
                            'default' => 'asc',
                        ],
                    ),
                    new Parameter(
                        name: 'order[createdAt]',
                        in: 'query',
                        description: 'Order by source creation date',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['asc', 'desc'],
                            'default' => 'asc',
                        ],
                    ),
                ],
            ),
            normalizationContext: [
                'groups' => ['source:read'],
            ],
            provider: ActorSourceProvider::class,
        ),
    ],
)]
class Actor implements TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm', 'document:save'])]
    private ?string $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm', 'source:read', 'document:save', 'source_group:read'])]
    private string $label;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    #[Assert\NotBlank]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm', 'document:save'])]
    private ?string $primaryDomain = null;

    /**
     * @var Collection<int, Source>
     */
    #[ORM\OneToMany(targetEntity: Source::class, mappedBy: 'actor')]
    private Collection $sources;

    #[ORM\Column]
    #[Groups(['actor:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['actor:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: Organisation::class)]
    #[ORM\JoinColumn(name: 'organisation_id', referencedColumnName: 'id', nullable: false)]
    private Organisation $organisation;

    public function __construct(string $label, Organisation $organisation)
    {
        $this->label = $label;
        $this->organisation = $organisation;
        $this->sources = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getOrganisation(): Organisation
    {
        return $this->organisation;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPrimaryDomain(): ?string
    {
        return $this->primaryDomain;
    }

    public function setPrimaryDomain(?string $primaryDomain): self
    {
        if (null === $primaryDomain) {
            $this->primaryDomain = null;

            return $this;
        }

        // should be only a domain name, not a full URL
        \Webmozart\Assert\Assert::notContains($primaryDomain, '/');
        $this->primaryDomain = $primaryDomain;

        return $this;
    }

    /**
     * @return Collection<int, Source>
     */
    public function getSources(): Collection
    {
        return $this->sources;
    }

    public function addSource(Source $source): self
    {
        if (!$this->sources->contains($source)) {
            $this->sources->add($source);
            $source->setActor($this);
        }

        return $this;
    }

    public function removeSource(Source $source): self
    {
        if ($this->sources->removeElement($source)) {
            // set the owning side to null (unless already changed)
            if ($source->getActor() === $this) {
                $source->setActor(null);
            }
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

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
