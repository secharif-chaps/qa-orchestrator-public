<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use ApiPlatform\Doctrine\Orm\Filter\BackedEnumFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorType;
use App\Domain\Chat\Message;
use App\Domain\Shared\HasRequiredWatchFileInterface;
use App\Domain\Shared\TranslatedText;
use App\Infrastructure\WatchFile\ApiFilter\ActorTypeFilter;
use App\Infrastructure\WatchFile\ApiFilter\WatchFileActorOrderFilter;
use App\Infrastructure\WatchFile\WatchFileActorProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Webmozart\Assert\Assert as WebmozartAssert;

#[ORM\Entity]
#[ApiResource(
    uriTemplate: '/watch_files/{id}/actors',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(fromProperty: 'watchFileActors', fromClass: WatchFile::class, description: 'WatchFile id'),
    ],
    normalizationContext: [
        'groups' => ['actor:read'],
    ],
    openapi: new Model\Operation(
        summary: 'Get actors associated with a watch file',
        description: <<<'EOT'
            Returns the list of actors associated with a specific watch file.

            Available filters:
            - type: Filter by actor type (e.g. type=competitor)
            - type[]: Filter by multiple actor types (e.g. type[]=company&type[]=competitor)
            - actor.label: Partial case-insensitive search on actor label (e.g. actor.label=google)
            - status: Exact match filter on the actor status (e.g. status=inactive)
            - score: Range filter on actor relevance score
              - score[gt]: Greater than (e.g. score[gt]=0.5)
              - score[gte]: Greater than or equal
              - score[lt]: Less than
              - score[lte]: Less than or equal

            Available ordering:
            - score: Order by relevance score
            - type: Order by actor type
            - actor.label: Order by actor label

            Use order[] parameter to specify sort direction, e.g. order[score]=desc
            EOT
        ,
    ),
    provider: WatchFileActorProvider::class,
)]
#[ApiFilter(ActorTypeFilter::class)]
#[ApiFilter(SearchFilter::class, properties: [
    'actor.label' => 'ipartial',
])]
#[ApiFilter(BackedEnumFilter::class, properties: ['status'])]
#[ApiFilter(RangeFilter::class, properties: ['score'])]
#[ApiFilter(WatchFileActorOrderFilter::class)]
class WatchFileActor implements HasRequiredWatchFileInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm'])]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'watchFileActors')]
    #[ORM\JoinColumn(nullable: false)]
    private WatchFile $watchFile;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm'])]
    private Actor $actor;

    #[ORM\Column(type: 'string', enumType: ActorType::class)]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm'])]
    #[Assert\Choice(callback: [ActorType::class, 'cases'])]
    private ?ActorType $type = null;

    #[ORM\Column]
    #[Groups(['watch_file:llm'])]
    private ?float $score = null;

    #[ORM\Column(type: 'translated_text', nullable: true)]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm'])]
    private ?TranslatedText $explanation = null;

    #[ORM\Column]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Message $addedByMessage = null;

    #[ORM\Column(type: 'string', enumType: ActorStatus::class, options: [
        'default' => ActorStatus::ACTIVE,
    ])]
    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm'])]
    #[Assert\Choice(callback: [ActorStatus::class, 'cases'])]
    private ActorStatus $status = ActorStatus::ACTIVE;

    /**
     * Virtual property populated by WatchFileActorSourcesCountEnricher.
     * Contains the count of sources for this actor within the watchfile context.
     */
    #[Groups(['actor:read'])]
    private ?int $sourcesCount = null;

    public function __construct(Actor $actor, WatchFile $watchFile)
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->actor = $actor;
        $this->watchFile = $watchFile;
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getActor(): Actor
    {
        return $this->actor;
    }

    public function setActor(Actor $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getType(): ?ActorType
    {
        return $this->type;
    }

    public function setType(ActorType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        WebmozartAssert::greaterThanEq($score, 0, 'Score must be greater than or equal to 0');
        WebmozartAssert::lessThanEq($score, 100, 'Score must be less than or equal to 100');

        $this->score = $score;

        return $this;
    }

    #[Groups(['actor:read', 'watch_file:read', 'watch_file:llm'])]
    public function getExplanations(): ?TranslatedText
    {
        return $this->explanation;
    }

    public function setExplanation(?TranslatedText $explanation): self
    {
        $this->explanation = $explanation;

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

    public function getAddedByMessage(): ?Message
    {
        return $this->addedByMessage;
    }

    public function setAddedByMessage(?Message $addedByMessage): self
    {
        $this->addedByMessage = $addedByMessage;

        return $this;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        WebmozartAssert::greaterThanEq(
            $updatedAt,
            $this->createdAt,
            'UpdatedAt must be greater than or equal to CreatedAt'
        );

        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getStatus(): ActorStatus
    {
        return $this->status;
    }

    public function setStatus(ActorStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function getSourcesCount(): ?int
    {
        return $this->sourcesCount;
    }

    public function setSourcesCount(?int $sourcesCount): self
    {
        $this->sourcesCount = $sourcesCount;

        return $this;
    }
}
