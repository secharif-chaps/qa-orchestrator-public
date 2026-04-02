<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Chat\Message;
use App\Domain\Shared\HasRequiredWatchFileInterface;
use App\Domain\Shared\TranslatedText;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class StrategicQuestion implements HasRequiredWatchFileInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watch_file:llm'])]
    private ?string $id = null;

    #[ORM\Column(type: 'translated_text')]
    #[Groups(['watch_file:llm'])]
    private TranslatedText $question;

    #[ORM\Column(type: 'translated_text')]
    #[Groups(['watch_file:llm'])]
    private TranslatedText $context;

    #[ORM\Column(type: 'string', enumType: MonitoringType::class)]
    #[Groups(['watch_file:llm'])]
    private MonitoringType $monitoringDimension;

    #[ORM\Column(type: 'integer')]
    #[Groups(['watch_file:llm'])]
    private int $priority;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['watch_file:llm'])]
    private string $expectedOutputType;

    #[ORM\ManyToOne(targetEntity: WatchFile::class, inversedBy: 'strategicQuestions')]
    #[ORM\JoinColumn(nullable: false)]
    private WatchFile $watchFile;

    #[ORM\Column()]
    #[Groups(['watch_file:llm'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?\DateTimeImmutable $searchQueriesGeneratedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?\DateTimeImmutable $SearchQueriesExecutedAt = null;

    #[ORM\OneToMany(targetEntity: SearchQuery::class, mappedBy: 'strategicQuestion', cascade: ['persist', 'remove'])]
    #[Groups(['watch_file:llm'])]
    private Collection $searchQueries;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Message $addedByMessage = null;

    public function __construct(
        TranslatedText $question,
        TranslatedText $context,
        MonitoringType $monitoringDimension,
        int $priority,
        string $expectedOutputType,
        WatchFile $watchFile,
        ?Message $addedByMessage = null,
    ) {
        $this->question = $question;
        $this->context = $context;
        $this->monitoringDimension = $monitoringDimension;
        $this->priority = $priority;
        $this->expectedOutputType = $expectedOutputType;
        $this->watchFile = $watchFile;
        $this->addedByMessage = $addedByMessage;
        $this->searchQueries = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getQuestion(): TranslatedText
    {
        return $this->question;
    }

    public function getContext(): TranslatedText
    {
        return $this->context;
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }

    public function getMonitoringDimension(): MonitoringType
    {
        return $this->monitoringDimension;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getExpectedOutputType(): string
    {
        return $this->expectedOutputType;
    }

    public function getAddedByMessage(): ?Message
    {
        return $this->addedByMessage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSearchQueries(): Collection
    {
        return $this->searchQueries;
    }

    public function addSearchQuery(SearchQuery $searchQuery): void
    {
        if (!$this->searchQueries->contains($searchQuery)) {
            $this->searchQueries->add($searchQuery);
        }
    }

    public function removeSearchQuery(SearchQuery $searchQuery): void
    {
        $this->searchQueries->removeElement($searchQuery);
    }

    public function markSearchQueriesAsGenerated(): void
    {
        $this->searchQueriesGeneratedAt = new \DateTimeImmutable();
    }

    public function getSearchQueriesGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->searchQueriesGeneratedAt;
    }

    public function markSearchQueriesAsExecuted(): void
    {
        $this->SearchQueriesExecutedAt = new \DateTimeImmutable();
    }

    public function getSearchQueriesExecutedAt(): ?\DateTimeImmutable
    {
        return $this->SearchQueriesExecutedAt;
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
}
