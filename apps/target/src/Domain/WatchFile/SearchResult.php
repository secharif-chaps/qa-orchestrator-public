<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
class SearchResult
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watch_file:llm'])]
    private ?string $id = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['watch_file:llm'])]
    private string $title;

    #[ORM\Column(type: 'text')]
    #[Groups(['watch_file:llm'])]
    private string $description;

    #[ORM\Column(type: 'string')]
    #[Groups(['watch_file:llm'])]
    private string $url;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?string $content = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['watch_file:llm'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?int $qualityScore = null;

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    #[Groups(['watch_file:llm'])]
    private bool $isSelected = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?int $selectionRank = null;

    #[ORM\ManyToOne(targetEntity: SearchQuery::class, inversedBy: 'searchResults')]
    #[ORM\JoinColumn(nullable: false)]
    private SearchQuery $searchQuery;

    public function __construct(
        string $title,
        string $description,
        string $url,
        SearchQuery $searchQuery,
        ?string $content = null,
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->url = $url;
        $this->content = $content;
        $this->searchQuery = $searchQuery;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSearchQuery(): SearchQuery
    {
        return $this->searchQuery;
    }

    public function setSearchQuery(SearchQuery $searchQuery): void
    {
        $this->searchQuery = $searchQuery;
    }

    public function getQualityScore(): ?int
    {
        return $this->qualityScore;
    }

    public function isSelected(): bool
    {
        return $this->isSelected;
    }

    public function getSelectionRank(): ?int
    {
        return $this->selectionRank;
    }

    public function updateScoring(
        ?int $qualityScore = null,
        ?bool $isSelected = null,
        ?int $selectionRank = null,
    ): void {
        if (null !== $qualityScore) {
            $this->qualityScore = $qualityScore;
        }

        if (null !== $isSelected) {
            $this->isSelected = $isSelected;
            // If unselecting, automatically clear selectionRank
            if (false === $isSelected) {
                $this->selectionRank = null;
            }
        }

        // Update selectionRank only if explicitly provided and isSelected is true
        if (null !== $selectionRank && (null === $isSelected || true === $isSelected)) {
            $this->selectionRank = $selectionRank;
        }
    }
}
